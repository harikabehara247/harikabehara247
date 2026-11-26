<?php
/* =====================================================================
 * H View × PhonePe Payment Gateway (Standard Checkout v2), r91, 26 Nov 2026
 * Merchant: Night Out TTS Pvt Ltd. Keys live in hv_settings (admin › Payment relay › PhonePe).
 *   pp_on, pp_env (production|sandbox), pp_client_id, pp_client_version, pp_client_secret,
 *   pp_hook_user, pp_hook_pass, pp_token (cached json)
 * Flow: start → PhonePe checkout page → return (order-status check) and webhook → unlock.
 * An order opens ONLY when PhonePe's order-status API (or a signed webhook) says COMPLETED
 * for the full amount.
 * ===================================================================== */
if (defined('HV_PHONEPE')) return; define('HV_PHONEPE', 1);
require_once __DIR__ . '/relay-hooks.php';

function pp_on(PDO $pdo) { return relay_set($pdo, 'pp_on', '0') === '1' && relay_set($pdo, 'pp_client_id', '') !== '' && relay_set($pdo, 'pp_client_secret', '') !== ''; }
function pp_hosts(PDO $pdo) {
    if (getenv('HV_PP_MOCK')) { $m = rtrim(getenv('HV_PP_MOCK'), '/'); return [$m, $m]; }
    return relay_set($pdo, 'pp_env', 'production') === 'sandbox'
        ? ['https://api-preprod.phonepe.com/apis/pg-sandbox', 'https://api-preprod.phonepe.com/apis/pg-sandbox']
        : ['https://api.phonepe.com/apis/identity-manager', 'https://api.phonepe.com/apis/pg'];
}
function pp_install(PDO $pdo) {
    static $done = false; if ($done) return; $done = true; relay_install($pdo);
    $my = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $ai = $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT'; $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_pg_orders (id $ai, moid VARCHAR(63) NOT NULL, kind VARCHAR(20) NOT NULL, ref_id INT NOT NULL DEFAULT 0, code VARCHAR(40) NOT NULL, paise INT NOT NULL, state VARCHAR(16) NOT NULL DEFAULT 'CREATED', pg_order VARCHAR(80) NULL, txn VARCHAR(80) NULL, back VARCHAR(300) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE (moid))$tail");
}
function pp_http($method, $url, array $headers, $body = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $j = json_decode((string)$raw, true);
    return [$code, is_array($j) ? $j : [], $err ?: ($code >= 400 ? mb_substr((string)$raw, 0, 300) : '')];
}
/* O-Bearer token, cached until a minute before it expires */
function pp_token(PDO $pdo, $force = false) {
    $c = json_decode((string)relay_set($pdo, 'pp_token', '{}'), true) ?: [];
    if (!$force && !empty($c['t']) && (int)($c['exp'] ?? 0) > time() + 60) return [$c['t'], ''];
    [$auth] = pp_hosts($pdo);
    [$code, $j, $err] = pp_http('POST', $auth . '/v1/oauth/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'client_id' => relay_set($pdo, 'pp_client_id', ''), 'client_version' => relay_set($pdo, 'pp_client_version', '1'),
        'client_secret' => relay_set($pdo, 'pp_client_secret', ''), 'grant_type' => 'client_credentials']));
    if ($code !== 200 || empty($j['access_token'])) return [null, 'PhonePe login failed (' . $code . '): ' . ($j['message'] ?? $j['code'] ?? $err)];
    $exp = (int)($j['expires_at'] ?? 0); if ($exp > 1e11) $exp = (int)($exp / 1000); if ($exp <= time()) $exp = time() + 1800;
    relay_put($pdo, 'pp_token', json_encode(['t' => $j['access_token'], 'exp' => $exp]));
    return [$j['access_token'], ''];
}
/* create a PhonePe checkout for one H View order; returns [redirectUrl|null, error] */
function pp_start(PDO $pdo, $kind, $refId, $code, $paise, $back, $message) {
    pp_install($pdo);
    [$tok, $err] = pp_token($pdo); if (!$tok) return [null, $err];
    $moid = preg_replace('/[^A-Za-z0-9_-]/', '', $code) . '-' . strtoupper(bin2hex(random_bytes(3)));
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO hv_pg_orders (moid, kind, ref_id, code, paise, back, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$moid, $kind, (int)$refId, $code, (int)$paise, mb_substr((string)$back, 0, 300), $now, $now]);
    [, $pg] = pp_hosts($pdo);
    $ret = 'https://hview.in/api/phonepe?a=return&o=' . rawurlencode($moid);
    $req = ['merchantOrderId' => $moid, 'amount' => (int)$paise, 'expireAfter' => 1200, 'metaInfo' => ['udf1' => $kind, 'udf2' => $code],
            'paymentFlow' => ['type' => 'PG_CHECKOUT', 'message' => mb_substr($message, 0, 60), 'merchantUrls' => ['redirectUrl' => $ret]]];
    [$hc, $j, $e] = pp_http('POST', $pg . '/checkout/v2/pay', ['Content-Type: application/json', 'Authorization: O-Bearer ' . $tok], json_encode($req));
    if ($hc === 401) { [$tok] = pp_token($pdo, true); if ($tok) [$hc, $j, $e] = pp_http('POST', $pg . '/checkout/v2/pay', ['Content-Type: application/json', 'Authorization: O-Bearer ' . $tok], json_encode($req)); }
    if ($hc !== 200 || empty($j['redirectUrl'])) { $pdo->prepare("UPDATE hv_pg_orders SET state='ERROR', updated_at=? WHERE moid=?")->execute([$now, $moid]); return [null, 'PhonePe could not start the payment (' . $hc . '): ' . ($j['message'] ?? $j['code'] ?? $e)]; }
    $pdo->prepare('UPDATE hv_pg_orders SET pg_order=?, state=?, updated_at=? WHERE moid=?')->execute([$j['orderId'] ?? null, $j['state'] ?? 'PENDING', $now, $moid]);
    return [$j['redirectUrl'], ''];
}
/* ask PhonePe for the truth and apply it; returns the stored state */
function pp_check(PDO $pdo, $moid) {
    pp_install($pdo);
    $s = $pdo->prepare('SELECT * FROM hv_pg_orders WHERE moid=?'); $s->execute([$moid]); $o = $s->fetch(PDO::FETCH_ASSOC); if (!$o) return null;
    if ($o['state'] === 'COMPLETED') return $o;
    [$tok] = pp_token($pdo); if (!$tok) return $o;
    [, $pg] = pp_hosts($pdo);
    [$hc, $j] = pp_http('GET', $pg . '/checkout/v2/order/' . rawurlencode($moid) . '/status?details=false', ['Content-Type: application/json', 'Authorization: O-Bearer ' . $tok]);
    if ($hc !== 200 || empty($j['state'])) return $o;
    $state = strtoupper((string)$j['state']); $amt = (int)($j['amount'] ?? 0);
    $txn = ''; if (!empty($j['paymentDetails'][0]['transactionId'])) $txn = (string)$j['paymentDetails'][0]['transactionId'];
    if ($state === 'COMPLETED' && $amt < (int)$o['paise']) $state = 'SHORT';
    return pp_apply($pdo, $o, $state, $txn, (string)($j['orderId'] ?? ''));
}
function pp_apply(PDO $pdo, array $o, $state, $txn = '', $pgOrder = '') {
    $now = gmdate('Y-m-d H:i:s');
    $u = $pdo->prepare("UPDATE hv_pg_orders SET state=?, txn=COALESCE(NULLIF(?,''), txn), pg_order=COALESCE(NULLIF(?,''), pg_order), updated_at=? WHERE id=? AND state<>'COMPLETED'"); $u->execute([$state, $txn, $pgOrder, $now, $o['id']]);
    $o['state'] = $state;
    if ($state === 'COMPLETED' && $u->rowCount() === 1) {
        $fn = $GLOBALS['RELAY_UNLOCK'][$o['kind']] ?? null; $ok = false;
        $pend = ['id' => 0, 'kind' => $o['kind'], 'ref_id' => (int)$o['ref_id'], 'code' => $o['code'], 'paise' => (int)$o['paise']];
        $msg = ['id' => 0, 'paise' => (int)$o['paise'], 'ref' => null, 'payer' => 'PhonePe PG ' . ($txn ?: $o['moid'])];
        if (is_callable($fn)) { try { $ok = (bool)$fn($pdo, $pend, $msg); } catch (Throwable $e) { error_log('pp unlock: ' . $e->getMessage()); } }
        $pdo->prepare("UPDATE hv_relay_pending SET status='paid', paid_at=? WHERE code=? AND status IN ('pending','expired')")->execute([$now, $o['code']]);
        try { if (function_exists('relay_notify')) relay_notify($pdo, $pend, $msg, $ok); } catch (Throwable $e) {}
    }
    return $o;
}
/* webhook: Authorization header = sha256(username:password) */
function pp_webhook(PDO $pdo, array $h, $raw) {
    pp_install($pdo);
    $hl = []; foreach ($h as $k => $v) $hl[strtolower($k)] = (string)$v;
    $user = relay_set($pdo, 'pp_hook_user', ''); $pass = relay_set($pdo, 'pp_hook_pass', '');
    if ($user === '' || $pass === '') return [503, ['ok' => false]];
    $got = strtolower(trim(preg_replace('/^(SHA256|Bearer|Basic)\s+/i', '', $hl['authorization'] ?? '')));
    if (!hash_equals(hash('sha256', $user . ':' . $pass), $got)) return [401, ['ok' => false]];
    $j = json_decode($raw, true); if (!is_array($j)) return [400, ['ok' => false]];
    $p = $j['payload'] ?? []; $moid = (string)($p['merchantOrderId'] ?? $p['originalMerchantOrderId'] ?? '');
    if ($moid === '') return [200, ['ok' => true]];
    /* never trust the webhook body alone: confirm with the order-status API */
    $o = pp_check($pdo, $moid);
    return [200, ['ok' => true, 'state' => $o['state'] ?? null]];
}
