<?php
/* =====================================================================
 * H View payment relay: site hooks (r90). Loaded by the endpoint, the ix and
 * ac checkouts and the admin page. Unlocks reuse the admin "Verify" logic.
 * ===================================================================== */
require_once __DIR__ . '/relay.php';
if (!function_exists('setting_value')) { function setting_value($pdo, $key, $default = '') { try { $s = $pdo->prepare('SELECT setting_value FROM hv_settings WHERE setting_key=?'); $s->execute([$key]); $v = $s->fetchColumn(); return ($v === false || $v === null) ? $default : $v; } catch (Throwable $e) { return $default; } } }
if (defined('HV_RELAY_HOOKS')) return; define('HV_RELAY_HOOKS', 1);
function relay_on(PDO $pdo) { return relay_set($pdo, 'pay_relay_on', '0') === '1' && relay_set($pdo, 'pay_relay_secret', '') !== ''; }
function relay_rupees($paise) { return number_format($paise / 100, 2, '.', ''); }

/* ₹49 single-greeting unlock (hv_ix_orders, package 'unlock') */
$GLOBALS['RELAY_UNLOCK']['ix_unlock'] = function (PDO $pdo, array $p, array $m) {
    $q = $pdo->prepare("SELECT * FROM hv_ix_orders WHERE id=? LIMIT 1"); $q->execute([(int)$p['ref_id']]); $o = $q->fetch(PDO::FETCH_ASSOC); if (!$o) return false;
    $now = date('Y-m-d H:i:s'); $ref = $m['ref'] ?: ($o['upi_ref'] ?: null);
    $pdo->prepare("UPDATE hv_ix_orders SET payment_status='verified', status='paid', upi_ref=COALESCE(upi_ref, ?), upi_ref_at=COALESCE(upi_ref_at, ?), verified_by='bank relay', verified_at=?, updated_at=? WHERE id=?")->execute([$ref, $now, $now, $now, $o['id']]);
    if (preg_match('/wish:([A-Za-z0-9]{10})/', (string)$o['requirements'], $wm)) $pdo->prepare("UPDATE hv_ix_experiences SET status='active' WHERE token=? AND status='awaiting'")->execute([$wm[1]]);
    try { $pdo->prepare('INSERT INTO hv_ix_order_log (order_id, action, detail, user_name, created_at) VALUES (?,?,?,?,?)')->execute([$o['id'], 'payment received (bank relay)', '₹' . relay_rupees($m['paise']) . ($m['ref'] ? ' · Ref ' . $m['ref'] : '') . ($m['payer'] ? ' · from ' . $m['payer'] : '') . ' · greeting opened', 'bank relay', $now]); } catch (Throwable $e) {}
    return true;
};
/* membership plans (hv_member_upgrades) */
$GLOBALS['RELAY_UNLOCK']['plan'] = function (PDO $pdo, array $p, array $m) {
    $root = dirname(__DIR__);
    if (!function_exists('mp_confirm')) { foreach (['/ac-core/lib.php', '/ac-core/mp.php'] as $f) if (is_file($root . $f)) require_once $root . $f; }
    if (!function_exists('mp_confirm')) return false;
    $q = $pdo->prepare('SELECT * FROM hv_member_upgrades WHERE id=?'); $q->execute([(int)$p['ref_id']]); $o = $q->fetch(PDO::FETCH_ASSOC); if (!$o) return false;
    if ($m['ref'] && empty($o['upi_ref'])) { try { $pdo->prepare('UPDATE hv_member_upgrades SET upi_ref=?, upi_ref_at=NOW() WHERE id=?')->execute([$m['ref'], $o['id']]); $o['upi_ref'] = $m['ref']; } catch (Throwable $e) {} }
    $r = mp_confirm($pdo, $o, 'bank relay', 'bank relay', '₹' . relay_rupees($m['paise']) . ($m['ref'] ? ' Ref ' . $m['ref'] : '') . ($m['payer'] ? ' from ' . $m['payer'] : ''));
    return !empty($r['ok']);
};
/* owner alert (admin notification centre, no e-mail batching) */
function relay_notify(PDO $pdo, array $o, array $m, $ok) {
    $title = ($ok ? '✅ Paid' : '⚠️ Paid, open by hand') . ' · ₹' . relay_rupees($m['paise']) . ' · ' . $o['code'];
    $body = ($o['kind'] === 'plan' ? 'Membership plan' : 'Single greeting') . ' confirmed by the SBI credit' . ($m['ref'] ? ' Ref ' . $m['ref'] : '') . ($m['payer'] ? ' from ' . $m['payer'] : '') . '.';
    try { $f = dirname(__DIR__) . '/admin-core/notify.php'; if (is_file($f)) { require_once $f; hv_notify($pdo, 'ix_utr', $title, $body, '/admin/?m=dashboard&go=pay-relay'); } } catch (Throwable $e) {}
}
