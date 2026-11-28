<?php
/* H View Billing: register sync + manual orders (r93).
 * bill_sync($pdo)            paid ₹49 greetings and paid plans -> payments register (idempotent)
 * bill_orders_page / _post   give a plan or open a greeting for someone who paid outside the site */
require_once __DIR__ . '/lib.php';

function bill_ist_date($dt, $utc = true) { if (!$dt) return bill_today(); try { $d = new DateTime($dt, new DateTimeZone($utc ? 'UTC' : 'Asia/Kolkata')); $d->setTimezone(new DateTimeZone('Asia/Kolkata')); return $d->format('Y-m-d'); } catch (Throwable $e) { return bill_today(); } }

function bill_sync(PDO $pdo) {
    static $done = false; if ($done) return; $done = true;
    /* ₹49 unlocks and other greeting orders (times stored in UTC) */
    try {
        $q = $pdo->query("SELECT o.*, (SELECT p.paise FROM hv_relay_pending p WHERE p.code=o.code AND p.status='paid' ORDER BY p.id DESC LIMIT 1) rp FROM hv_ix_orders o
            WHERE (o.status='paid' OR o.payment_status='verified') AND NOT EXISTS (SELECT 1 FROM hv_bill_payments b WHERE b.source='ix' AND b.source_id=CONCAT('x', o.id))");
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $amt = $o['rp'] ? (int)$o['rp'] : (int)round((float)($o['agreed_price'] ?: $o['est_price']) * 100); if ($amt <= 0) continue;
            bill_pay_add($pdo, ['received_on' => bill_ist_date($o['verified_at'] ?: $o['updated_at']), 'amount' => $amt, 'method' => 'upi', 'source' => 'ix', 'source_id' => 'x' . $o['id'],
                'ref' => $o['upi_ref'] ?: null, 'payer' => mb_substr((string)$o['customer_name'], 0, 120) ?: null, 'order_ref' => $o['code'], 'kind' => $o['package'] === 'unlock' ? 'greeting' : 'design',
                'note' => 'Confirmed by ' . ($o['verified_by'] ?: 'admin'), 'created_by' => 'sync']);
        }
    } catch (Throwable $e) {}
    /* membership plans (times stored in IST) */
    try {
        $q = $pdo->query("SELECT u.*, m.name mname, m.email memail, (SELECT p.paise FROM hv_relay_pending p WHERE p.code=u.code AND p.kind='plan' AND p.status='paid' ORDER BY p.id DESC LIMIT 1) rp
            FROM hv_member_upgrades u LEFT JOIN hv_members m ON m.id=u.member_id
            WHERE u.status IN ('active','provisional') AND COALESCE(u.source,'')<>'manual-bill' AND NOT EXISTS (SELECT 1 FROM hv_bill_payments b WHERE b.source='plan' AND b.source_id=CONCAT('p', u.id))");
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $amt = $o['rp'] ? (int)$o['rp'] : (int)round((float)($o['paid_amount'] ?: $o['amount']) * 100); if ($amt <= 0) continue;
            bill_pay_add($pdo, ['received_on' => bill_ist_date($o['paid_on'] ?: ($o['verified_at'] ?: $o['updated_at']), false), 'amount' => $amt, 'method' => 'upi', 'source' => 'plan', 'source_id' => 'p' . $o['id'],
                'ref' => $o['upi_ref'] ?: null, 'payer' => mb_substr((string)($o['mname'] ?: $o['memail']), 0, 120) ?: null, 'order_ref' => $o['code'], 'kind' => 'plan',
                'note' => 'Plan ' . $o['plan'] . ' · confirmed by ' . ($o['verified_by'] ?: 'admin'), 'created_by' => 'sync']);
        }
    } catch (Throwable $e) {}
}

function bill_orders_lib() {
    $root = dirname(__DIR__);
    require_once $root . '/ix-core/relay-hooks.php';
    if (!function_exists('mp_confirm')) { foreach (['/ac-core/lib.php', '/ac-core/mp.php'] as $f) if (is_file($root . $f)) require_once $root . $f; }
    return function_exists('mp_confirm') && function_exists('mp_order_create');
}

function bill_orders_post(PDO $pdo, $do, $U) {
    $methods = ['upi', 'bank', 'cash', 'card', 'gateway', 'other'];
    $str = function ($k, $n = 190) { return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n); };
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['received_on'] ?? '')) ? $_POST['received_on'] : bill_today();
    $method = in_array($_POST['method'] ?? '', $methods, true) ? $_POST['method'] : 'upi';
    $ref = preg_replace('/[^A-Za-z0-9\-\/]/', '', $str('ref', 40)) ?: null;
    if ($do === 'give_plan') {
        if (!bill_orders_lib()) throw new RuntimeException('The plans system could not be loaded.');
        $email = mb_strtolower($str('email', 160)); $planKey = $str('plan', 20); $amt = bill_paise($_POST['amount'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM hv_members WHERE LOWER(email)=? LIMIT 1'); $s->execute([$email]); $m = $s->fetch(PDO::FETCH_ASSOC);
        if (!$m) throw new RuntimeException('No H View account with ' . $email . '. Ask them to sign up at hview.in first (free), then try again.');
        $p = mp_plan($pdo, $planKey); if (!$p || $planKey === 'free' || $p['status'] !== 'active') throw new RuntimeException('Pick a plan.');
        if ($amt <= 0) throw new RuntimeException('Enter the amount you received. Use the Free gift option only through Members.');
        $o = mp_order_create($pdo, $m, $planKey, 'manual');
        if (!$o) throw new RuntimeException('Could not create the order (is this a sale plan that has ended?).');
        try { $pdo->prepare('UPDATE hv_member_upgrades SET upi_ref=COALESCE(upi_ref, ?), paid_amount=?, paid_on=? WHERE id=?')->execute([$ref, (int)round($amt / 100), $date . ' ' . date('H:i:s'), $o['id']]); } catch (Throwable $e) {}
        $o = mp_order_by_code($pdo, $o['code']);
        $r = mp_confirm($pdo, $o, 'manual', bill_who(), 'Billing › Manual order · ' . bill_rs($amt) . ($ref ? ' Ref ' . $ref : ''));
        if (empty($r['ok'])) throw new RuntimeException('Plan not started: ' . ($r['error'] ?? 'unknown error'));
        bill_pay_add($pdo, ['received_on' => $date, 'amount' => $amt, 'method' => $method, 'source' => 'plan', 'source_id' => 'p' . $o['id'], 'ref' => $ref, 'payer' => $str('payer', 120) ?: ($m['name'] ?: $m['email']),
            'order_ref' => $o['code'], 'kind' => 'plan', 'note' => $p['name'] . ' given by hand' . ($str('note') ? ' · ' . $str('note') : '')]);
        adm_log($pdo, 'bill_manual_plan', 'member', (int)$m['id'], $o['code'] . ' ' . bill_rs($amt));
        adm_flash('success', $p['name'] . ' is now active for ' . $m['email'] . ' (' . $o['code'] . '), and ' . bill_rs($amt) . ' is in the register.');
        adm_redirect($U(['tab' => 'orders']));
    }
    if ($do === 'open_greeting') {
        require_once dirname(__DIR__) . '/ix-core/relay-hooks.php';
        $s = $pdo->prepare("SELECT * FROM hv_ix_orders WHERE id=? AND package='unlock'"); $s->execute([(int)($_POST['order'] ?? 0)]); $o = $s->fetch(PDO::FETCH_ASSOC);
        if (!$o || $o['status'] === 'paid') throw new RuntimeException('That greeting is already open or was not found.');
        $amt = bill_paise($_POST['amount'] ?? 0) ?: (int)round((float)$o['agreed_price'] * 100);
        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare("UPDATE hv_ix_orders SET payment_status='verified', status='paid', upi_ref=COALESCE(?, upi_ref), verified_by=?, verified_at=?, updated_at=? WHERE id=?")->execute([$ref, 'admin (billing)', $now, $now, $o['id']]);
        if (preg_match('/wish:([A-Za-z0-9]{10})/', (string)$o['requirements'], $wm)) $pdo->prepare("UPDATE hv_ix_experiences SET status='active' WHERE token=? AND status='awaiting'")->execute([$wm[1]]);
        $pdo->prepare("UPDATE hv_relay_pending SET status='paid', paid_at=? WHERE code=? AND status IN ('pending','expired')")->execute([$now, $o['code']]);
        try { $pdo->prepare('INSERT INTO hv_ix_order_log (order_id, action, detail, user_name, created_at) VALUES (?,?,?,?,?)')->execute([$o['id'], 'payment confirmed by hand', 'Billing › Manual orders · ' . bill_rs($amt), bill_who(), $now]); } catch (Throwable $e) {}
        bill_pay_add($pdo, ['received_on' => $date, 'amount' => $amt, 'method' => $method, 'source' => 'ix', 'source_id' => 'x' . $o['id'], 'ref' => $ref, 'payer' => $str('payer', 120) ?: $o['customer_name'],
            'order_ref' => $o['code'], 'kind' => 'greeting', 'note' => 'Opened by hand']);
        adm_log($pdo, 'bill_manual_greeting', 'ix_order', (int)$o['id'], $o['code']);
        adm_flash('success', $o['code'] . ' is paid and the greeting is open.'); adm_redirect($U(['tab' => 'orders']));
    }
}

function bill_orders_page(PDO $pdo, $U) {
    $plans = []; if (bill_orders_lib()) { foreach (mp_plans($pdo, true) as $k => $p) if ($k !== 'free' && $p['status'] === 'active' && (strpos($k, 'bf') !== 0 || mp_bf_on($pdo))) $plans[$k] = $p; }
    $wait = $pdo->query("SELECT * FROM hv_ix_orders WHERE package='unlock' AND status<>'paid' AND status NOT IN ('cancelled','rejected') ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $recent = $pdo->query("SELECT * FROM hv_bill_payments WHERE source IN ('plan','ix') AND created_by<>'sync' ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
    $csrf = '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
    $mopt = '<option value="upi">UPI</option><option value="bank">Bank transfer</option><option value="cash">Cash</option><option value="other">Other</option>';
?>
<div class="bl-card"><h3>Which one do I use?</h3><div class="bl-m">
  <b>A customer job</b> (website, design, custom work): make an <a href="<?= bill_h($U(['tab' => 'edit', 'kind' => 'invoice'])) ?>">invoice</a>. It is the order and the bill in one.<br>
  <b>Someone paid you for a plan</b> on WhatsApp or by bank: give it below. The plan starts now and the money goes into the register.<br>
  <b>Someone paid ₹49 for a greeting</b> but it is still locked: open it below.</div></div>
<form method="post" class="bl-card"><?= $csrf ?><input type="hidden" name="do" value="give_plan"><h3>Give a plan</h3>
  <div class="bl-f">
    <div><label>Their H View account email<input name="email" type="email" required placeholder="name@gmail.com"></label></div>
    <div><label>Plan<select name="plan" id="bl-plan"><?php foreach ($plans as $k => $p): ?><option value="<?= bill_h($k) ?>" data-p="<?= (int)$p['price'] ?>"><?= bill_h($p['name'] . ' · ₹' . $p['price']) ?></option><?php endforeach; ?></select></label></div>
    <div><label>Amount received (₹)<input name="amount" id="bl-pamt" inputmode="decimal" required></label></div>
    <div><label>Date<input type="date" name="received_on" value="<?= bill_today() ?>"></label></div>
    <div><label>Method<select name="method"><?= $mopt ?></select></label></div>
    <div><label>UTR / reference<input name="ref"></label></div>
    <div><label>Paid by (name)<input name="payer"></label></div>
    <div><label>Note<input name="note"></label></div>
    <div><button class="bl-btn ok" onclick="return confirm('Start this plan now? Check that the money is in your account first.')">Start plan and record payment</button></div>
  </div><div class="bl-m">They need a free H View account first. The plan's dates start today.</div></form>
<script>(function(){var s=document.getElementById('bl-plan'),a=document.getElementById('bl-pamt');if(!s)return;function f(){var o=s.options[s.selectedIndex];if(o&&!a.dataset.t)a.value=o.dataset.p}s.addEventListener('change',function(){a.dataset.t='';f()});a.addEventListener('input',function(){a.dataset.t='1'});f()})();</script>
<div class="bl-card"><h3>Greetings waiting for payment</h3>
  <?php if (!$wait): ?><div class="bl-m">None waiting.</div><?php else: ?><table class="bl-t"><tr><th>Order</th><th>Made</th><th>For</th><th>Status</th><th>Open it</th></tr>
  <?php foreach ($wait as $o): ?><tr><td><b><?= bill_h($o['code']) ?></b></td><td><?= bill_h(bill_ist($o['created_at'], 'd M, H:i')) ?></td><td><?= bill_h($o['customer_name'] ?: '—') ?><br><span class="bl-m"><?= bill_h(trim($o['phone'] . ' ' . $o['email'])) ?></span></td><td><?= bill_h($o['status']) ?><?= $o['upi_ref'] ? '<br><span class="bl-m">UTR ' . bill_h($o['upi_ref']) . '</span>' : '' ?></td>
    <td><form method="post" class="bl-inline" onsubmit="return confirm('Only if the money is in your account. Open <?= bill_h($o['code']) ?>?')"><?= $csrf ?><input type="hidden" name="do" value="open_greeting"><input type="hidden" name="order" value="<?= (int)$o['id'] ?>">
      <input name="amount" value="<?= number_format((float)$o['agreed_price'], 2, '.', '') ?>" style="width:80px;border:1px solid #ddd8ea;border-radius:8px;padding:5px"> <select name="method" style="border:1px solid #ddd8ea;border-radius:8px;padding:5px"><?= $mopt ?></select>
      <input name="ref" placeholder="UTR" style="width:120px;border:1px solid #ddd8ea;border-radius:8px;padding:5px"> <button class="bl-btn sm ok">Paid, open it</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php if ($recent): ?><div class="bl-card"><h3>Given by hand recently</h3><table class="bl-t"><?php foreach ($recent as $r): ?><tr><td><?= bill_h(bill_date($r['received_on'], 'd M')) ?></td><td><?= bill_h($r['order_ref']) ?></td><td><?= bill_h($r['payer']) ?></td><td><?= bill_h($r['note']) ?></td><td class="r"><?= bill_rs($r['amount']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php
}
