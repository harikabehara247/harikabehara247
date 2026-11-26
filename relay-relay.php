<?php
/* =====================================================================
 * H View UPI Payment Relay: server side (r90, 26 Nov 2026)
 * The owner's phone (H View Relay app) forwards SBI credit SMS and UPI-app
 * "received" notifications. A greeting or plan opens only when a real credit
 * with the order's exact amount arrives.
 *
 *   relay_install($pdo)                    tables
 *   relay_reserve($pdo, kind, ref_id, code, rupees, utr=null)  -> unique amount (paise)
 *   relay_handle($pdo, $headers, $raw)     POST /api/pay-relay  -> [http_code, array]
 *   relay_parse($body, $sender)            -> ['paise'=>int|null,'ref'=>?,'payer'=>?,'ignore'=>bool]
 *   relay_status($pdo, $code)              -> pending row (for checkout polling)
 *   relay_match_manual($pdo, $msgId, $pendingId)  admin one-click match
 * Unlock hooks: $GLOBALS['RELAY_UNLOCK'][kind] = function(PDO $pdo, array $pending, array $msg): bool
 * Settings: pay_relay_secret, pay_relay_on ('1'), pay_relay_ping (json)
 * ===================================================================== */
if (defined('HV_RELAY')) return; define('HV_RELAY', 1);

const RELAY_WINDOW_MIN = 30;      /* order waits this long for its payment */
const RELAY_GRACE_MIN = 120;      /* a credit may arrive this late (phone offline) and still match */
const RELAY_SKEW = 300;           /* signature timestamp window, seconds */
const RELAY_DUP_MIN = 10;         /* SMS and notification for the same amount within this = one payment */

function relay_now() { return gmdate('Y-m-d H:i:s'); }
function relay_my(PDO $pdo) { return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'; }
function relay_set(PDO $pdo, $k, $d = '') { if (relay_my($pdo)) { $s = $pdo->prepare('SELECT setting_value FROM hv_settings WHERE setting_key=?'); $s->execute([$k]); $v = $s->fetchColumn(); return ($v === false || $v === null) ? $d : $v; } try { $s = $pdo->prepare('SELECT v FROM hv_relay_kv WHERE k=?'); $s->execute([$k]); $v = $s->fetchColumn(); return $v === false ? $d : $v; } catch (Throwable $e) { return $d; } }
function relay_put(PDO $pdo, $k, $v) { if (relay_my($pdo)) { $pdo->prepare('INSERT INTO hv_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()')->execute([$k, (string)$v]); return; } $pdo->prepare('DELETE FROM hv_relay_kv WHERE k=?')->execute([$k]); $pdo->prepare('INSERT INTO hv_relay_kv (k,v) VALUES (?,?)')->execute([$k, (string)$v]); }

function relay_install(PDO $pdo) {
    static $done = false; if ($done) return; $done = true;
    $my = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $ai = $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_relay_msgs (id $ai, msg_id VARCHAR(80) NOT NULL, device VARCHAR(64) NOT NULL DEFAULT '', source VARCHAR(10) NOT NULL DEFAULT '', sender VARCHAR(80) NOT NULL DEFAULT '', body TEXT, paise INT NULL, ref VARCHAR(20) NULL, payer VARCHAR(80) NULL, received_at BIGINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'unmatched', pending_id INT NULL, note VARCHAR(190) NULL, UNIQUE (msg_id))$tail");
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_relay_pending (id $ai, kind VARCHAR(20) NOT NULL, ref_id INT NOT NULL DEFAULT 0, code VARCHAR(40) NOT NULL, paise INT NOT NULL, utr VARCHAR(20) NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending', msg_row INT NULL, paid_at DATETIME NULL)$tail");
    if (!$my) $pdo->exec("CREATE TABLE IF NOT EXISTS hv_relay_kv (k VARCHAR(64) PRIMARY KEY, v TEXT)$tail");
    try { $pdo->exec('CREATE INDEX ix_relay_pend ON hv_relay_pending (status, paise)'); } catch (Throwable $e) {}
    try { $pdo->exec('CREATE INDEX ix_relay_code ON hv_relay_pending (code)'); } catch (Throwable $e) {}
}

/* ---------------------------------------------------------------- parsing (SBI-FORMATS.md) */
function relay_parse($body, $sender = '') {
    $t = (string)$body; $out = ['paise' => null, 'ref' => null, 'payer' => null, 'ignore' => false];
    /* SBI Card "payment received" is a card bill payment, never a customer payment */
    if (preg_match('/^[A-Z]{2}-SBICRD/i', (string)$sender) || preg_match('/\bSBI\s*Card\b/i', $t)) { $out['ignore'] = true; return $out; }
    /* first amount = the credit (a later "Avl Bal INR ..." is the balance) */
    $amtRe = '/(?:\bRs\.?|\bINR|₹)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)|(?:credited\s+(?:by|with)|credit\s+of)\s+(?:Rs\.?\s*|INR\s*|₹\s*)?([0-9][0-9,]*(?:\.[0-9]{1,2})?)/iu';
    if (preg_match_all($amtRe, $t, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $m) {
            $num = (isset($m[1]) && $m[1][0] !== '') ? $m[1][0] : (isset($m[2]) ? $m[2][0] : '');
            if ($num === '') continue;
            $before = mb_strtolower(substr($t, max(0, $m[0][1] - 14), 14));
            if (strpos($before, 'bal') !== false) continue;           /* skip "Avl Bal INR ..." */
            $out['paise'] = (int)round(((float)str_replace(',', '', $num)) * 100); break;
        }
    }
    if (preg_match('/(?:Ref\s*no|Refno|UPI\s*Ref(?:\s*No)?|RRN|UTR|IMPS\s*Ref\s*no|Ref)\D{0,6}(\d{12})/i', $t, $m)) $out['ref'] = $m[1];
    elseif (preg_match('/UPI\/CR\/(\d{12})\//i', $t, $m)) $out['ref'] = $m[1];
    if (preg_match('/(?:by\s+transfer\s+from|trf\s+from|received\s+from|from)\s+([A-Za-z][A-Za-z .]{1,40}?)(?=\s+Ref|\s*\.|\s*\(|\s*-|\s*$|\n)/i', $t, $m)) $out['payer'] = trim($m[1]);
    elseif (preg_match('/^\s*([A-Za-z][A-Za-z .]{1,40}?)\s+(?:paid you|sent you)/im', $t, $m)) $out['payer'] = trim($m[1]);
    elseif (preg_match('/UPI\/CR\/\d{12}\/([A-Za-z .]{2,40})/i', $t, $m)) $out['payer'] = trim($m[1]);
    return $out;
}

/* ---------------------------------------------------------------- unique amounts */
function relay_expire(PDO $pdo) { $pdo->prepare("UPDATE hv_relay_pending SET status='expired' WHERE status='pending' AND expires_at < ?")->execute([gmdate('Y-m-d H:i:s', time() - RELAY_GRACE_MIN * 60)]); }
/* returns the amount in paise the customer must pay, unique among open orders (₹49.01 … ₹49.99) */
function relay_reserve(PDO $pdo, $kind, $refId, $code, $rupees, $utr = null) {
    relay_install($pdo); relay_expire($pdo);
    $s = $pdo->prepare("SELECT paise FROM hv_relay_pending WHERE code=? AND kind=? AND status='pending' ORDER BY id DESC LIMIT 1"); $s->execute([$code, $kind]);
    if (($p = $s->fetchColumn()) !== false) { $pdo->prepare("UPDATE hv_relay_pending SET expires_at=? WHERE code=? AND kind=? AND status='pending'")->execute([gmdate('Y-m-d H:i:s', time() + RELAY_WINDOW_MIN * 60), $code, $kind]); return (int)$p; }
    $base = (int)round($rupees) * 100;
    $u = $pdo->prepare("SELECT paise FROM hv_relay_pending WHERE status='pending' AND paise BETWEEN ? AND ?"); $u->execute([$base, $base + 99]);
    $used = array_flip(array_map('intval', $u->fetchAll(PDO::FETCH_COLUMN)));
    /* also avoid amounts that arrived recently without a match (a late payer for an expired order) */
    $r = $pdo->prepare("SELECT paise FROM hv_relay_msgs WHERE status='unmatched' AND paise BETWEEN ? AND ? AND created_at > ?"); $r->execute([$base, $base + 99, gmdate('Y-m-d H:i:s', time() - 6 * 3600)]);
    foreach ($r->fetchAll(PDO::FETCH_COLUMN) as $x) $used[(int)$x] = 1;
    $free = []; for ($i = 1; $i <= 99; $i++) if (!isset($used[$base + $i])) $free[] = $base + $i;
    if (!$free) return null;
    $paise = $free[random_int(0, count($free) - 1)];
    $pdo->prepare('INSERT INTO hv_relay_pending (kind, ref_id, code, paise, utr, created_at, expires_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$kind, (int)$refId, (string)$code, $paise, $utr ?: null, relay_now(), gmdate('Y-m-d H:i:s', time() + RELAY_WINDOW_MIN * 60)]);
    return $paise;
}
function relay_status(PDO $pdo, $code) { relay_install($pdo); $s = $pdo->prepare('SELECT * FROM hv_relay_pending WHERE code=? ORDER BY id DESC LIMIT 1'); $s->execute([$code]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function relay_set_utr(PDO $pdo, $code, $utr) { relay_install($pdo); $pdo->prepare("UPDATE hv_relay_pending SET utr=? WHERE code=? AND status='pending'")->execute([$utr, $code]); }

/* ---------------------------------------------------------------- the endpoint */
function relay_handle(PDO $pdo, array $h, $raw) {
    relay_install($pdo);
    $hl = []; foreach ($h as $k => $v) $hl[strtolower($k)] = is_array($v) ? (string)reset($v) : (string)$v;
    $secret = (string)relay_set($pdo, 'pay_relay_secret', '');
    if ($secret === '' || relay_set($pdo, 'pay_relay_on', '1') === '0') return [503, ['ok' => false, 'error' => 'relay off']];
    $ts = $hl['x-hv-timestamp'] ?? ''; $sig = strtolower($hl['x-hv-signature'] ?? ''); $dev = $hl['x-hv-device'] ?? '';
    if (!ctype_digit($ts) || abs(time() - (int)$ts) > RELAY_SKEW) return [401, ['ok' => false, 'error' => 'timestamp']];
    if (!hash_equals(hash_hmac('sha256', $ts . '.' . $raw, $secret), $sig)) return [401, ['ok' => false, 'error' => 'signature']];
    $b = json_decode($raw, true); if (!is_array($b)) return [400, ['ok' => false, 'error' => 'json']];
    if ((string)($b['device'] ?? '') !== $dev) return [400, ['ok' => false, 'error' => 'device mismatch']];
    $src = (string)($b['source'] ?? '');
    if ($src === 'ping') { relay_put($pdo, 'pay_relay_ping', json_encode(['at' => time(), 'name' => mb_substr((string)($b['name'] ?? ''), 0, 60), 'queue' => (int)($b['queue'] ?? 0), 'device' => $dev])); return [200, ['ok' => true]]; }
    if (!in_array($src, ['sms', 'notif', 'test'], true)) return [400, ['ok' => false, 'error' => 'source']];
    $mid = substr((string)($b['msg_id'] ?? ''), 0, 80); if ($mid === '') return [400, ['ok' => false, 'error' => 'msg_id']];
    $body = mb_substr((string)($b['body'] ?? ''), 0, 1000); $sender = mb_substr((string)($b['sender'] ?? ''), 0, 80);
    $rcv = (int)($b['received_at'] ?? 0); if ($rcv <= 0) $rcv = time() * 1000;
    $p = relay_parse($body, $sender);
    $st = $src === 'test' ? 'test' : ($p['ignore'] ? 'ignored' : ($p['paise'] ? 'unmatched' : 'noamount'));
    try {
        $pdo->prepare('INSERT INTO hv_relay_msgs (msg_id, device, source, sender, body, paise, ref, payer, received_at, created_at, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$mid, $dev, $src, $sender, $body, $p['paise'], $p['ref'], $p['payer'] ? mb_substr($p['payer'], 0, 80) : null, $rcv, relay_now(), $st]);
    } catch (Throwable $e) { return [409, ['ok' => true, 'duplicate' => true]]; }
    $row = (int)$pdo->lastInsertId();
    relay_put($pdo, 'pay_relay_ping', json_encode(array_merge((array)json_decode((string)relay_set($pdo, 'pay_relay_ping', '{}'), true), ['at' => time(), 'device' => $dev])));
    if ($st !== 'unmatched') return [200, ['ok' => true, 'matched' => null]];
    return [200, ['ok' => true, 'matched' => relay_try_match($pdo, $row)]];
}

/* match one stored credit to one open order; returns the order code or null */
function relay_try_match(PDO $pdo, $rowId) {
    $s = $pdo->prepare('SELECT * FROM hv_relay_msgs WHERE id=?'); $s->execute([$rowId]); $m = $s->fetch(PDO::FETCH_ASSOC); if (!$m || !$m['paise']) return null;
    $at = (int)floor($m['received_at'] / 1000);
    /* the same payment seen twice (SMS + app notification): attach to the order already paid by its twin */
    $tw = $pdo->prepare("SELECT x.id, x.pending_id, x.source FROM hv_relay_msgs x WHERE x.id<>? AND x.paise=? AND x.source<>? AND x.source IN ('sms','notif') AND x.status IN ('matched','unmatched') AND ABS(x.received_at - ?) <= ? ORDER BY x.id LIMIT 1");
    $tw->execute([$rowId, $m['paise'], $m['source'], $m['received_at'], RELAY_DUP_MIN * 60000]);
    if ($t = $tw->fetch(PDO::FETCH_ASSOC)) {
        $pdo->prepare("UPDATE hv_relay_msgs SET status='twin', pending_id=?, note=? WHERE id=?")->execute([$t['pending_id'], 'same payment as #' . $t['id'], $rowId]);
        if ($t['pending_id'] && $m['ref']) $pdo->prepare('UPDATE hv_relay_msgs SET ref=COALESCE(ref, ?) WHERE id=?')->execute([$m['ref'], $t['id']]);
        if ($t['pending_id']) { $c = $pdo->prepare('SELECT code FROM hv_relay_pending WHERE id=?'); $c->execute([$t['pending_id']]); return $c->fetchColumn() ?: null; }
        return null;
    }
    $q = $pdo->prepare("SELECT * FROM hv_relay_pending WHERE status='pending' AND paise=? AND created_at <= ? AND expires_at >= ? ORDER BY id");
    $q->execute([$m['paise'], gmdate('Y-m-d H:i:s', $at + 120), gmdate('Y-m-d H:i:s', $at - RELAY_GRACE_MIN * 60)]);
    $c = array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC), function ($o) use ($m) { return !$o['utr'] || !$m['ref'] || $o['utr'] === $m['ref']; }));
    if (count($c) !== 1) { if (count($c) > 1) $pdo->prepare("UPDATE hv_relay_msgs SET note='several orders with this amount: match by hand' WHERE id=?")->execute([$rowId]); return null; }
    return relay_mark_paid($pdo, $c[0], $m) ? $c[0]['code'] : null;
}
function relay_mark_paid(PDO $pdo, array $o, array $m) {
    $u = $pdo->prepare("UPDATE hv_relay_pending SET status='paid', msg_row=?, paid_at=? WHERE id=? AND status='pending'"); $u->execute([$m['id'], relay_now(), $o['id']]);
    if ($u->rowCount() !== 1) return false;
    $pdo->prepare("UPDATE hv_relay_msgs SET status='matched', pending_id=? WHERE id=?")->execute([$o['id'], $m['id']]);
    $ok = true; $fn = $GLOBALS['RELAY_UNLOCK'][$o['kind']] ?? null;
    if (is_callable($fn)) { try { $ok = (bool)$fn($pdo, $o, $m); } catch (Throwable $e) { $ok = false; } }
    if (!$ok) $pdo->prepare("UPDATE hv_relay_msgs SET note='paid, but unlocking failed: open it by hand' WHERE id=?")->execute([$m['id']]);
    if (function_exists('relay_notify')) { try { relay_notify($pdo, $o, $m, $ok); } catch (Throwable $e) {} }
    return true;
}
function relay_match_manual(PDO $pdo, $msgRow, $pendingId) {
    relay_install($pdo);
    $s = $pdo->prepare('SELECT * FROM hv_relay_msgs WHERE id=?'); $s->execute([(int)$msgRow]); $m = $s->fetch(PDO::FETCH_ASSOC);
    $o = $pdo->prepare("SELECT * FROM hv_relay_pending WHERE id=? AND status IN ('pending','expired')"); $o->execute([(int)$pendingId]); $o = $o->fetch(PDO::FETCH_ASSOC);
    if (!$m || !$o || !in_array($m['status'], ['unmatched', 'noamount'], true)) return false;
    if ($o['status'] === 'expired') $pdo->prepare("UPDATE hv_relay_pending SET status='pending' WHERE id=?")->execute([$o['id']]);
    $o['status'] = 'pending';
    return relay_mark_paid($pdo, $o, $m);
}
