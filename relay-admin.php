<?php
/* Payment relay (r90, 26 Nov 2026). Opens at /admin/?m=dashboard&go=pay-relay
 * The owner's phone (H View Relay app) forwards SBI credit alerts to /api/pay-relay/.
 * A ₹49 greeting or a plan opens only when a credit with its exact amount arrives.
 * Here: phone status, secret key, on/off, open orders, every credit received, manual match. */
adm_require('pay-relay');
require_once dirname(__DIR__, 2) . '/ix-core/relay-hooks.php';
relay_install($pdo);
$back = adm_url('pay-relay'); $reveal = '';
$ist = function ($utc, $fmt = 'd M H:i') { if (!$utc) return '—'; try { $d = new DateTime(is_numeric($utc) ? '@' . (int)$utc : $utc . ' UTC'); $d->setTimezone(new DateTimeZone('Asia/Kolkata')); return $d->format($fmt); } catch (Throwable $e) { return (string)$utc; } };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $do = (string)($_POST['do'] ?? '');
    if ($do === 'toggle') { $on = relay_set($pdo, 'pay_relay_on', '0') === '1' ? '0' : '1'; relay_put($pdo, 'pay_relay_on', $on); adm_log($pdo, 'pay_relay_' . ($on === '1' ? 'on' : 'off'), 'pay-relay'); adm_flash('success', $on === '1' ? 'Bank relay is ON: greetings and plans open when the money arrives.' : 'Bank relay is OFF: payments wait for your Verify in Payment verification.'); }
    if ($do === 'rotate') { relay_put($pdo, 'pay_relay_secret', bin2hex(random_bytes(24))); adm_log($pdo, 'pay_relay_secret_rotated', 'pay-relay'); adm_flash('success', 'New secret key made. Paste it into the H View Relay app (Setup › Secret key), or the phone will stop being accepted.'); }
    if ($do === 'reveal') { $reveal = relay_set($pdo, 'pay_relay_secret', ''); adm_log($pdo, 'pay_relay_secret_viewed', 'pay-relay'); }
    if ($do === 'match') {
        $ok = relay_match_manual($pdo, (int)($_POST['msg'] ?? 0), (int)($_POST['pending'] ?? 0));
        adm_log($pdo, 'pay_relay_manual_match', 'pay-relay', (int)($_POST['msg'] ?? 0), (string)($_POST['pending'] ?? ''));
        adm_flash($ok ? 'success' : 'error', $ok ? 'Matched. The order is paid and opened.' : 'Could not match: the credit or the order is no longer open.');
    }
    if ($do === 'ignore') { $pdo->prepare("UPDATE hv_relay_msgs SET status='ignored', note=? WHERE id=? AND status IN ('unmatched','noamount')")->execute(['ignored by admin', (int)($_POST['msg'] ?? 0)]); adm_flash('success', 'Marked as not an H View payment.'); }
    if ($do === 'proof_ok' || $do === 'proof_no') {
        $q = $pdo->prepare("SELECT * FROM hv_ix_orders WHERE id=? AND package='unlock'"); $q->execute([(int)($_POST['order'] ?? 0)]); $o = $q->fetch(PDO::FETCH_ASSOC);
        if ($o && $o['status'] !== 'paid') {
            $now = gmdate('Y-m-d H:i:s'); $who = (string)($GLOBALS['ixUser'] ?? ($_SESSION['user_name'] ?? 'admin'));
            if ($do === 'proof_ok') {
                $pdo->prepare("UPDATE hv_ix_orders SET payment_status='verified', status='paid', verified_by=?, verified_at=?, updated_at=? WHERE id=?")->execute(['admin (screenshot)', $now, $now, $o['id']]);
                if (preg_match('/wish:([A-Za-z0-9]{10})/', (string)$o['requirements'], $wm)) $pdo->prepare("UPDATE hv_ix_experiences SET status='active' WHERE token=? AND status='awaiting'")->execute([$wm[1]]);
                $pdo->prepare("UPDATE hv_relay_pending SET status='paid', paid_at=? WHERE code=? AND status IN ('pending','expired')")->execute([$now, $o['code']]);
                adm_flash('success', $o['code'] . ' approved: the greeting is open.');
            } else {
                $pdo->prepare("UPDATE hv_ix_orders SET payment_status='unpaid', status='awaiting_payment', upi_ref=NULL, upi_ref_at=NULL, notes=REPLACE(COALESCE(notes,''), ' proof:', ' rejected-proof:'), updated_at=? WHERE id=?")->execute([$now, $o['id']]);
                adm_flash('success', $o['code'] . ' rejected: the greeting stays locked.');
            }
            try { $pdo->prepare('INSERT INTO hv_ix_order_log (order_id, action, detail, user_name, created_at) VALUES (?,?,?,?,?)')->execute([$o['id'], $do === 'proof_ok' ? 'payment approved from screenshot' : 'screenshot rejected', 'Payment relay page', 'admin', $now]); } catch (Throwable $e) {}
            adm_log($pdo, $do === 'proof_ok' ? 'pay_proof_approved' : 'pay_proof_rejected', 'ix_order', (int)$o['id'], $o['code']);
        }
    }
    if ($reveal === '') adm_redirect($back);
}
relay_expire($pdo);
$on = relay_set($pdo, 'pay_relay_on', '0') === '1';
$ping = json_decode((string)relay_set($pdo, 'pay_relay_ping', '{}'), true) ?: [];
$age = !empty($ping['at']) ? time() - (int)$ping['at'] : null;
$tone = $age === null ? 'bad' : ($age < 1800 ? 'good' : ($age < 7200 ? 'warn' : 'bad'));
$ago = function ($s) { if ($s === null) return 'never'; if ($s < 90) return 'just now'; if ($s < 3600) return round($s / 60) . ' min ago'; if ($s < 172800) return round($s / 3600) . ' h ago'; return round($s / 86400) . ' days ago'; };
$rs = function ($p) { return $p === null ? '—' : '₹' . number_format($p / 100, 2); };
$pend = $pdo->query("SELECT * FROM hv_relay_pending WHERE status='pending' ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$recentPend = $pdo->query("SELECT * FROM hv_relay_pending WHERE status IN ('pending','expired') AND created_at > '" . gmdate('Y-m-d H:i:s', time() - 2 * 86400) . "' ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$msgs = $pdo->query("SELECT m.*, p.code pcode, p.kind pkind FROM hv_relay_msgs m LEFT JOIN hv_relay_pending p ON p.id=m.pending_id ORDER BY m.id DESC LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
$k = $pdo->query("SELECT SUM(status='matched') matched, SUM(status='unmatched') unmatched, COALESCE(SUM(CASE WHEN status='matched' THEN paise END),0) amt FROM hv_relay_msgs WHERE created_at > '" . gmdate('Y-m-d H:i:s', time() - 30 * 86400) . "'")->fetch(PDO::FETCH_ASSOC);
$stLbl = ['matched' => ['Opened', 'good'], 'unmatched' => ['Needs a look', 'warn'], 'twin' => ['Same payment (app)', 'blue'], 'ignored' => ['Ignored', 'muted'], 'test' => ['Test', 'blue'], 'noamount' => ['No amount', 'warn']];
?>
<style>
.prl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:16px}
.prl-card{background:#fff;border:1px solid #e8e3f3;border-radius:16px;padding:16px}
.prl-card h3{margin:0 0 8px;font-size:15px}.prl-muted{color:#6b6480;font-size:13px}
.prl-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;vertical-align:middle}.prl-dot.good{background:#16a34a}.prl-dot.warn{background:#f59e0b}.prl-dot.bad{background:#dc2626}
.prl-key{font:600 13px ui-monospace,Menlo,monospace;background:#f6f3ff;border:1px dashed #a78bfa;border-radius:10px;padding:10px;word-break:break-all;user-select:all}
.prl-t{width:100%;border-collapse:collapse;font-size:13px}.prl-t th,.prl-t td{padding:8px 6px;border-bottom:1px solid #f0edf7;text-align:left;vertical-align:top}.prl-t th{color:#6b6480;font-weight:600}
.prl-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}.prl-pill.good{background:#dcfce7;color:#166534}.prl-pill.warn{background:#fef3c7;color:#92400e}.prl-pill.blue{background:#e0e7ff;color:#3730a3}.prl-pill.muted{background:#f1f5f9;color:#475569}
.prl-body{max-width:340px;color:#4b4560;font-size:12px;white-space:pre-wrap}
.prl-row form{display:inline}
</style>
<div class="adm-head"><div><h1>Payment relay</h1><p class="prl-muted">Your phone forwards SBI "credited" alerts here. A ₹49 greeting or a plan opens only when a credit with its exact amount (for example ₹49.07) arrives. Nothing opens on a typed UTR or a screenshot.</p></div></div>
<div class="prl-grid">
  <div class="prl-card"><h3>Relay</h3>
    <p><span class="prl-dot <?= $on ? 'good' : 'bad' ?>"></span><b><?= $on ? 'ON' : 'OFF' ?></b> <span class="prl-muted"><?= $on ? '· unique amounts at checkout, auto-open when paid' : '· payments wait for your Verify' ?></span></p>
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="toggle"><button class="adm-btn<?= $on ? '' : ' adm-btn-primary' ?>"><?= $on ? 'Turn off' : 'Turn on' ?></button></form>
  </div>
  <div class="prl-card"><h3>Your phone</h3>
    <p><span class="prl-dot <?= $tone ?>"></span><b><?= e($ping['name'] ?? 'H View Relay') ?></b> · last seen <?= e($ago($age)) ?><?= isset($ping['queue']) && $ping['queue'] ? ' · ' . (int)$ping['queue'] . ' waiting on the phone' : '' ?></p>
    <p class="prl-muted"><?= $tone === 'good' ? 'Online. The app checks in every 15 minutes.' : 'Not heard from the phone recently. Open the app, check the permissions, battery settings and internet.' ?></p>
  </div>
  <div class="prl-card"><h3>Last 30 days</h3>
    <p><b><?= (int)$k['matched'] ?></b> payments opened automatically · <b><?= e($rs((int)$k['amt'])) ?></b></p>
    <p class="prl-muted"><?= (int)$k['unmatched'] ?> credit(s) need a look</p>
  </div>
</div>
<div class="prl-card" style="margin-bottom:16px"><h3>Secret key for the app</h3>
  <?php if ($reveal !== ''): ?>
    <p class="prl-key"><?= e($reveal) ?></p>
    <p class="prl-muted">Copy it into the H View Relay app: Setup › Secret key › Save. Then tap <b>Send test</b> and a "Test" line appears below. Don't send this key in chat or email. It is shown once; reload hides it.</p>
  <?php else: ?>
    <form method="post" class="prl-row"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="reveal"><button class="adm-btn adm-btn-primary">Show secret key</button></form>
    <form method="post" class="prl-row" onsubmit="return confirm('Make a new key? The phone stops being accepted until you paste the new key into the app.')"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="rotate"><button class="adm-btn">Make a new key</button></form>
    <p class="prl-muted" style="margin-top:8px">App endpoint: <b>https://hview.in/api/pay-relay</b> (already filled in the app).</p>
  <?php endif ?>
</div>
<?php $proofs = $pdo->query("SELECT id, code, upi_ref, upi_ref_at, notes, agreed_price, created_at FROM hv_ix_orders WHERE package='unlock' AND status='verification_pending' AND notes LIKE '% proof:%' ORDER BY upi_ref_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC); $pdir = dirname(__DIR__, 2) . '/../hview-private/pay-proofs'; ?>
<?php if ($proofs): ?><div class="prl-card" style="margin-bottom:16px;border-color:#f2c46d"><h3>Screenshots to check (<?= count($proofs) ?>)</h3>
  <p class="prl-muted">Open your SBI account or SMS, find a credit with this <b>exact amount</b> at about this time, then approve. If you can't find it, reject: the greeting stays locked.</p>
  <table class="prl-t"><tr><th>Screenshot</th><th>Order</th><th>Must have received</th><th>UTR typed</th><th>Sent</th><th></th></tr>
  <?php foreach ($proofs as $o): preg_match_all('/ proof:([A-Za-z0-9._-]+)/', (string)$o['notes'], $pm); $file = end($pm[1]); $path = $file ? realpath($pdir) . '/' . basename($file) : ''; $rsx = relay_status($pdo, $o['code']); ?>
    <tr><td><?php if ($path && is_file($path)): $mime = preg_match('/\.png$/', $path) ? 'image/png' : (preg_match('/\.webp$/', $path) ? 'image/webp' : 'image/jpeg'); $src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)); ?><a href="<?= $src ?>" target="_blank" rel="noopener"><img src="<?= $src ?>" alt="Payment screenshot <?= e($o['code']) ?>" style="width:120px;border-radius:10px;border:1px solid #ddd"></a><?php else: ?>missing<?php endif ?></td>
      <td><b><?= e($o['code']) ?></b><br><span class="prl-muted">₹49 greeting</span></td><td><b><?= e($rsx ? $rs((int)$rsx['paise']) : '₹' . (int)$o['agreed_price']) ?></b></td><td><?= e($o['upi_ref'] ?: '—') ?></td><td><?= e($ist($o['upi_ref_at'])) ?></td>
      <td><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="proof_ok"><input type="hidden" name="order" value="<?= (int)$o['id'] ?>"><button class="adm-btn adm-btn-primary" onclick="return confirm('Money found in the SBI account? This opens the greeting.')">Approve &amp; open</button></form>
        <form method="post" style="margin-top:6px"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="proof_no"><input type="hidden" name="order" value="<?= (int)$o['id'] ?>"><button class="adm-btn" onclick="return confirm('Not received? The greeting stays locked.')">Reject</button></form></td></tr>
  <?php endforeach ?></table></div><?php endif ?>
<div class="prl-card" style="margin-bottom:16px"><h3>Waiting for payment (<?= count($pend) ?>)</h3>
  <?php if (!$pend): ?><p class="prl-muted">No open orders right now.</p><?php else: ?>
  <table class="prl-t"><tr><th>Order</th><th>What</th><th>Exact amount</th><th>Customer's UTR</th><th>Created</th><th>Expires</th></tr>
  <?php foreach ($pend as $p): ?><tr><td><b><?= e($p['code']) ?></b></td><td><?= $p['kind'] === 'plan' ? 'Plan' : '₹49 greeting' ?></td><td><b><?= e($rs((int)$p['paise'])) ?></b></td><td><?= e($p['utr'] ?: '—') ?></td><td><?= e($ist($p['created_at'])) ?></td><td><?= e($ist($p['expires_at'], 'H:i')) ?></td></tr><?php endforeach ?>
  </table><?php endif ?>
</div>
<div class="prl-card"><h3>Credits received from the phone</h3>
  <?php if (!$msgs): ?><p class="prl-muted">Nothing yet. Install the app, paste the key and tap Send test.</p><?php else: ?>
  <table class="prl-t"><tr><th>When</th><th>From</th><th>Amount</th><th>Ref · payer</th><th>Result</th><th>Message</th></tr>
  <?php foreach ($msgs as $m): $L = $stLbl[$m['status']] ?? [$m['status'], 'muted']; ?>
    <tr><td><?= e($ist((int)floor($m['received_at'] / 1000))) ?></td><td><?= e($m['source'] === 'sms' ? $m['sender'] : ($m['source'] === 'notif' ? preg_replace('/^.*\.(\w+)(\.user)?$/', '$1', $m['sender']) : $m['source'])) ?></td><td><b><?= e($rs($m['paise'] === null ? null : (int)$m['paise'])) ?></b></td>
      <td><?= e($m['ref'] ?: '—') ?><br><span class="prl-muted"><?= e($m['payer'] ?: '') ?></span></td>
      <td><span class="prl-pill <?= $L[1] ?>"><?= e($L[0]) ?></span><?= $m['pcode'] ? '<br><b>' . e($m['pcode']) . '</b>' : '' ?><?= $m['note'] ? '<br><span class="prl-muted">' . e($m['note']) . '</span>' : '' ?>
        <?php if (in_array($m['status'], ['unmatched', 'noamount'], true)): ?>
          <form method="post" style="margin-top:6px"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="match"><input type="hidden" name="msg" value="<?= (int)$m['id'] ?>">
            <select name="pending" required><option value="">Match to order…</option><?php foreach ($recentPend as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$p['paise'] === (int)$m['paise'] ? ' selected' : '' ?>><?= e($p['code'] . ' · ' . $rs((int)$p['paise']) . ' · ' . ($p['kind'] === 'plan' ? 'plan' : 'greeting') . ($p['status'] === 'expired' ? ' (expired)' : '')) ?></option><?php endforeach ?></select>
            <button class="adm-btn adm-btn-primary" onclick="return confirm('Open this order for this credit?')">Match</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="ignore"><input type="hidden" name="msg" value="<?= (int)$m['id'] ?>"><button class="adm-btn">Not ours</button></form>
        <?php endif ?></td>
      <td class="prl-body"><?= e(mb_substr((string)$m['body'], 0, 220)) ?></td></tr>
  <?php endforeach ?></table><?php endif ?>
</div>
