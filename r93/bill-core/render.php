<?php
/* =====================================================================
 * H View Billing: the invoice / quote page (customer link, admin preview, print). r93
 * bill_render($pdo, $doc)       reads the database, then calls
 * bill_render_html($data)       pure layout from one array (profile, doc, items, payments),
 *                               so a free public "invoice generator" tool can reuse it later.
 * ===================================================================== */
require_once __DIR__ . '/lib.php';

/* ₹ amount in words, Indian system: "Rupees One Lakh Twenty Thousand Five Hundred and Paise Fifty Only" */
function bill_words($paise) {
    $paise = abs((int)$paise); $r = intdiv($paise, 100); $ps = $paise % 100;
    $o = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $t = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function ($n) use ($o, $t) { return $n < 20 ? $o[$n] : trim($t[intdiv($n, 10)] . ' ' . $o[$n % 10]); };
    $three = function ($n) use ($two, $o) { $h = intdiv($n, 100); $rest = $n % 100; return trim(($h ? $o[$h] . ' Hundred' : '') . ($h && $rest ? ' ' : '') . ($rest ? $two($rest) : '')); };
    $w = function ($n) use ($two, $three) {
        if ($n === 0) return 'Zero'; $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$u, $l]) { if ($n >= $u) { $q = intdiv($n, $u); $parts[] = ($q > 99 ? $three($q) : $two($q)) . ' ' . $l; $n %= $u; } }
        if ($n) $parts[] = $three($n); return implode(' ', $parts);
    };
    return 'Rupees ' . $w($r) . ($ps ? ' and Paise ' . $two($ps) : '') . ' Only';
}
function bill_qty($q) { return rtrim(rtrim(number_format((float)$q, 2, '.', ''), '0'), '.'); }
function bill_upi_uri(array $p, array $d) {
    $due = bill_due($d); if (($p['upi'] ?? '') === '' || $due <= 0) return '';
    return 'upi://pay?pa=' . rawurlencode($p['upi']) . '&pn=' . rawurlencode($p['payee'] ?: $p['name']) . '&am=' . number_format($due / 100, 2, '.', '') . '&cu=INR&tn=' . rawurlencode($d['number'] ?: 'H View');
}

function bill_render(PDO $pdo, array $d, array $o = []) {
    $pays = [];
    if ($d['kind'] === 'invoice') { $s = $pdo->prepare("SELECT received_on, amount, method, ref FROM hv_bill_payments WHERE doc_id=? AND status='ok' ORDER BY received_on, id"); $s->execute([$d['id']]); $pays = $s->fetchAll(PDO::FETCH_ASSOC); }
    $p = bill_profile($pdo);
    $p['logo'] = (string)bill_get($pdo, 'bill_logo', '') ?: '/assets/img-cache/3d2312badff9991a-640.webp'; /* site header logo until one is set in Billing › Settings */
    return bill_render_html(['profile' => $p, 'doc' => $d, 'items' => bill_items($pdo, $d['id']), 'payments' => $pays], $o);
}

function bill_render_html(array $data, array $o = []) {
    $p = $data['profile']; $d = $data['doc']; $items = $data['items']; $pays = $data['payments'] ?? [];
    $isQ = $d['kind'] === 'quote'; $num = $d['number'] ?: 'Draft';
    $due = bill_due($d); $isPaid = !$isQ && $d['status'] === 'paid';
    $live = !in_array($d['status'], ['draft', 'cancelled', 'converted', 'declined'], true);
    $showPay = !$isQ && $live && !$isPaid && $due > 0;
    $payLink = trim((string)($p['pay_link'] ?? '')); if ($payLink !== '') $payLink = str_replace(['{token}', '{number}'], [$d['token'] ?? '', rawurlencode($d['number'] ?? '')], $payLink);
    $upi = $showPay && ($p['pay_mode'] ?? 'upi') === 'upi' ? bill_upi_uri($p, $d) : '';
    $hasTax = (int)$d['tax'] > 0; $hasUnit = (bool)array_filter($items, function ($i) { return !empty($i['unit']); });
    $chip = ['paid' => ['Paid', 'ok'], 'part_paid' => ['Part paid', 'warn'], 'sent' => [$isQ ? 'Awaiting response' : 'Due', 'warn'], 'draft' => ['Draft', 'mut'], 'cancelled' => ['Cancelled', 'bad'], 'accepted' => ['Accepted', 'ok'], 'declined' => ['Declined', 'bad'], 'converted' => ['Invoiced', 'ok']][$d['status']] ?? [$d['status'], 'mut'];
    $terms = $d['terms'] ?: ($isQ ? 'Valid for 15 days' : 'Due on receipt');
    $self = $o['self'] ?? '';
    ob_start(); ?>
<!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= bill_h(($isQ ? 'Quotation ' : 'Invoice ') . $num . ' · ' . $p['name']) ?></title>
<style>
:root{--ink:#111827;--sub:#4b5563;--mut:#6b7280;--line:#e5e7eb;--soft:#f9fafb;--brand:#3730a3;--accent:linear-gradient(90deg,#1d4ed8,#7c3aed 55%,#db2777)}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;background:#f3f4f6;color:var(--ink);font:13.5px/1.55 "Inter","Segoe UI",system-ui,-apple-system,Roboto,"Noto Sans",sans-serif;font-variant-numeric:tabular-nums}
.tools{max-width:820px;margin:20px auto 0;padding:0 12px;display:flex;justify-content:flex-end;gap:8px}
.tools button{font:600 13px system-ui;border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:8px;padding:8px 14px;cursor:pointer}.tools .pri{background:var(--brand);border-color:var(--brand);color:#fff}
.flash{max-width:820px;margin:12px auto 0;padding:10px 14px;border-radius:8px;background:#ecfdf5;color:#065f46;font-weight:600}
.page{max-width:820px;margin:10px auto 40px;background:#fff;border:1px solid var(--line);border-radius:6px;overflow:hidden}
.bar{height:5px;background:var(--accent)}
.in{padding:36px 44px 30px}
.hd{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}
.logo{height:54px;display:block}.org{margin-top:10px;font-size:12px;color:var(--sub);line-height:1.5}.org b{color:var(--ink);font-size:13px;letter-spacing:.01em}
.ttl{text-align:right}.ttl h1{margin:0;font-size:22px;font-weight:700;letter-spacing:.18em;color:var(--ink)}
.ttl .no{margin-top:4px;font-size:13px;color:var(--sub)}
.chip{display:inline-block;margin-top:8px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;border:1px solid}
.chip.ok{color:#047857;border-color:#a7f3d0;background:#ecfdf5}.chip.warn{color:#92400e;border-color:#fde68a;background:#fffbeb}.chip.bad{color:#b91c1c;border-color:#fecaca;background:#fef2f2}.chip.mut{color:#4b5563;border-color:var(--line);background:var(--soft)}
.meta{display:grid;grid-template-columns:1.3fr 1fr 1fr;margin-top:28px;border:1px solid var(--line);border-radius:6px}
.meta>div{padding:14px 16px}.meta>div+div{border-left:1px solid var(--line)}
.k{font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);margin-bottom:4px}
.meta .who{font-size:15px;font-weight:700}.meta .ex{color:var(--sub);white-space:pre-line;font-size:12.5px}
.meta dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:3px 12px;font-size:12.5px}.meta dt{color:var(--mut)}.meta dd{margin:0;font-weight:600;text-align:right}
.meta .big{font-size:24px;font-weight:800;letter-spacing:-.01em;color:var(--brand)}.meta .big.zero{color:#047857}
.re{margin-top:18px;font-size:12.5px;color:var(--sub)}.re b{color:var(--ink)}
table.li{width:100%;border-collapse:collapse;margin-top:18px}
table.li th{font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);text-align:left;padding:10px 10px;border-bottom:1.5px solid var(--ink);white-space:nowrap}
table.li td{padding:13px 10px;border-bottom:1px solid var(--line);vertical-align:top}
table.li .n{width:34px;color:var(--mut)}table.li .r{text-align:right;white-space:nowrap}
table.li .t{font-weight:600}table.li .d{color:var(--sub);font-size:12.5px;margin-top:2px;white-space:pre-line}
.sum{display:grid;grid-template-columns:1fr 300px;gap:28px;margin-top:6px}
.words{padding-top:14px;font-size:12px;color:var(--sub)}.words b{display:block;color:var(--ink);font-size:12.5px;margin-top:2px}
.tot{width:100%;border-collapse:collapse}.tot td{padding:8px 10px}.tot td:last-child{text-align:right;white-space:nowrap}
.tot .disc td:last-child{color:#be185d}.tot .gt td{border-top:1.5px solid var(--ink);font-weight:800;font-size:15px}
.tot .pd td{color:#047857}.tot .bd td{background:var(--soft);font-weight:800}
.box{border:1px solid var(--line);border-radius:6px;padding:12px 14px;margin-top:18px}
.box .k{color:#be185d}.box div{white-space:pre-line;color:var(--sub)}
.pay{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;border:1px solid #c7d2fe;background:#eef2ff;border-radius:6px;padding:14px 16px;margin-top:18px}
.pay .k{color:var(--brand)}.pay .l{font-size:12.5px;color:var(--sub)}.pay .l b{color:var(--ink)}
.pay a.btn{display:inline-block;background:var(--brand);color:#fff;text-decoration:none;font-weight:700;border-radius:8px;padding:10px 18px}
.pay button{font:600 11px system-ui;border:1px solid #c7d2fe;background:#fff;border-radius:6px;padding:2px 7px;cursor:pointer;margin-left:4px}
.pay .qr img{width:84px;height:84px;display:block}
.rcpt{margin-top:10px;font-size:12px;color:#047857}
.ft{border-top:1px solid var(--line);margin-top:26px;padding-top:12px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:11.5px;color:var(--mut)}
.ft b{color:var(--ink)}
@media (max-width:640px){.in{padding:22px 16px}.hd{flex-direction:column}.ttl{text-align:left}.meta{grid-template-columns:1fr}.meta>div+div{border-left:0;border-top:1px solid var(--line)}
 .sum{grid-template-columns:1fr}.sum .words{order:2}table.li .hide-s{display:none}.page{border-radius:0;border-left:0;border-right:0}}
@media print{body{background:#fff}.tools,.flash,.pay a.btn,.pay button{display:none!important}.page{border:0;margin:0;max-width:none;border-radius:0}.in{padding:8px 4px}
 .bar,.chip,.tot .bd td,.pay{-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{size:A4;margin:12mm}}
</style></head><body>
<?php if (!empty($o['flash'])): ?><div class="flash"><?= bill_h($o['flash']) ?></div><?php endif; ?>
<div class="tools">
  <?php if ($isQ && $d['status'] === 'sent' && empty($o['admin'])): ?><form method="post" action="<?= bill_h($self) ?>" style="margin:0"><input type="hidden" name="do" value="accept"><button class="pri" type="submit">Accept quotation</button></form><?php endif; ?>
  <button type="button" onclick="window.print()">Download PDF</button>
</div>
<div class="page"><div class="bar"></div><div class="in">
  <div class="hd">
    <div>
      <?php if (!empty($p['logo'])): ?><img class="logo" src="<?= bill_h($p['logo']) ?>" alt="<?= bill_h($p['name']) ?>"><?php endif; ?>
      <div class="org"><b><?= bill_h($p['name']) ?></b><br>
        <?php if ($p['address']): ?><?= nl2br(bill_h($p['address'])) ?><br><?php endif; ?>
        <?= bill_h(implode(' · ', array_filter([$p['website'], $p['email'], $p['phone']]))) ?>
        <?php if ($p['gstin']): ?><br>GSTIN <?= bill_h($p['gstin']) ?><?php endif; ?><?php if ($p['pan']): ?><?= $p['gstin'] ? ' · ' : '<br>' ?>PAN <?= bill_h($p['pan']) ?><?php endif; ?></div>
    </div>
    <div class="ttl"><h1><?= $isQ ? 'QUOTATION' : ($p['gstin'] && $hasTax ? 'TAX INVOICE' : 'INVOICE') ?></h1><div class="no"><?= bill_h($num) ?></div><span class="chip <?= $chip[1] ?>"><?= bill_h($chip[0]) ?></span></div>
  </div>

  <div class="meta">
    <div><div class="k"><?= $isQ ? 'Prepared for' : 'Billed to' ?></div><div class="who"><?= bill_h($d['bill_name']) ?></div><?php if ($d['bill_extra']): ?><div class="ex"><?= bill_h($d['bill_extra']) ?></div><?php endif; ?></div>
    <div><div class="k">Details</div><dl>
      <dt>Date</dt><dd><?= bill_h(bill_date($d['issue_date'] ?: bill_today(), 'd M Y')) ?></dd>
      <?php if ($d['due_date']): ?><dt><?= $isQ ? 'Valid till' : 'Due' ?></dt><dd><?= bill_h(bill_date($d['due_date'], 'd M Y')) ?></dd><?php endif; ?>
      <dt><?= $isQ ? 'Validity' : 'Terms' ?></dt><dd><?= bill_h($terms) ?></dd>
      <?php if ($d['order_ref']): ?><dt>Ref.</dt><dd><?= bill_h($d['order_ref']) ?></dd><?php endif; ?>
    </dl></div>
    <div><div class="k"><?= $isQ ? 'Quoted total' : ($isPaid ? 'Amount paid' : 'Amount due') ?></div>
      <div class="big <?= $isPaid ? 'zero' : '' ?>"><?= bill_rs($isQ ? $d['total'] : ($isPaid ? $d['paid'] : $due)) ?></div>
      <?php if ($isPaid && $d['paid_at']): ?><div class="ex" style="color:var(--mut);font-size:12px">Paid on <?= bill_h(bill_ist($d['paid_at'], 'd M Y')) ?></div><?php endif; ?></div>
  </div>

  <?php if ($d['site'] || $d['subject']): ?><div class="re"><?= $d['subject'] ? '<b>Re:</b> ' . bill_h($d['subject']) : '' ?><?= $d['site'] ? ($d['subject'] ? ' · ' : '<b>Project:</b> ') . bill_h($d['site']) : '' ?></div><?php endif; ?>

  <table class="li"><thead><tr><th class="n">#</th><th>Description</th><th class="r hide-s">Qty</th><th class="r hide-s">Rate</th><th class="r">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $i => $it): ?>
    <tr><td class="n"><?= $i + 1 ?></td><td><div class="t"><?= bill_h($it['title']) ?></div><?php if ($it['details']): ?><div class="d"><?= bill_h($it['details']) ?></div><?php endif; ?></td>
      <td class="r hide-s"><?= bill_qty($it['qty']) ?><?= $hasUnit && $it['unit'] ? ' ' . bill_h($it['unit']) : '' ?></td><td class="r hide-s"><?= bill_rs($it['rate']) ?></td><td class="r"><?= bill_rs($it['amount']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>

  <div class="sum">
    <div class="words"><?php if ((int)$d['total'] > 0): ?>Amount in words<b><?= bill_h(bill_words($d['total'])) ?></b><?php endif; ?></div>
    <table class="tot">
      <tr><td>Subtotal</td><td><?= bill_rs($d['subtotal']) ?></td></tr>
      <?php if ((int)$d['discount'] > 0): ?><tr class="disc"><td><?= bill_h($d['disc_label'] ?: 'Discount') ?><?= $d['disc_type'] === 'percent' ? ' (' . bill_qty($d['disc_value'] / 100) . '%)' : '' ?></td><td>−<?= bill_rs($d['discount']) ?></td></tr><?php endif; ?>
      <?php if ($hasTax): ?><tr><td><?= bill_h($d['tax_label'] ?: 'GST') ?> @ <?= bill_qty($d['tax_bps'] / 100) ?>%</td><td><?= bill_rs($d['tax']) ?></td></tr><?php endif; ?>
      <tr class="gt"><td>Total</td><td><?= bill_rs($d['total']) ?></td></tr>
      <?php if (!$isQ && (int)$d['paid'] > 0): ?><tr class="pd"><td>Paid</td><td>−<?= bill_rs($d['paid']) ?></td></tr><tr class="bd"><td>Balance due</td><td><?= bill_rs($due) ?></td></tr><?php endif; ?>
    </table>
  </div>

  <?php if ($d['note_body']): ?><div class="box"><div class="k"><?= bill_h($d['note_title'] ?: 'Note') ?></div><div><?= bill_h($d['note_body']) ?></div></div><?php endif; ?>

  <?php if ($showPay && ($payLink !== '' || $upi !== '')): ?>
  <div class="pay">
    <div><div class="k">How to pay</div>
      <?php if ($payLink !== ''): ?><div class="l">Pay securely online by UPI, card or net banking.</div>
      <?php else: ?><div class="l">UPI <b><?= bill_h($p['upi']) ?></b><button type="button" data-copy="<?= bill_h($p['upi']) ?>">Copy</button> · Amount <b><?= bill_rs($due) ?></b><button type="button" data-copy="<?= number_format($due / 100, 2, '.', '') ?>">Copy</button><br>Add <b><?= bill_h($num) ?></b> as the payment note.</div><?php endif; ?>
    </div>
    <?php if ($payLink !== ''): ?><a class="btn" href="<?= bill_h($payLink) ?>">Pay <?= bill_rs($due) ?></a>
    <?php elseif (!empty($p['show_qr'])): ?><div class="qr" id="bill-qr" data-uri="<?= bill_h($upi) ?>"></div>
    <?php else: ?><a class="btn" href="<?= bill_h($upi) ?>">Pay by UPI app</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($pays): ?><div class="rcpt"><?php foreach ($pays as $py): ?><div>✓ Received <?= bill_rs($py['amount']) ?> on <?= bill_h(bill_date($py['received_on'], 'd M Y')) ?> via <?= bill_h(strtoupper($py['method'])) ?><?= $py['ref'] ? ' · Ref ' . bill_h($py['ref']) : '' ?></div><?php endforeach; ?></div><?php endif; ?>

  <div class="ft">
    <div><b><?= bill_h($p['footer']) ?></b><?php if ($d['footer']): ?><br><?= nl2br(bill_h($d['footer'])) ?><?php endif; ?>
      <?php if (!$hasTax && !$p['gstin']): ?><br><?= bill_h($p['nogst']) ?><?php endif; ?></div>
    <div style="text-align:right">Computer-generated <?= $isQ ? 'quotation' : 'invoice' ?>; no signature required.<br><?= bill_h($p['website']) ?></div>
  </div>
</div></div>
<?php if ($upi !== '' && !empty($p['show_qr'])): ?><script src="/assets/bill/qrcode.js"></script><?php endif; ?>
<script>
(function(){var el=document.getElementById('bill-qr');if(el&&window.qrcode){var q=qrcode(0,'M');q.addData(el.getAttribute('data-uri'));q.make();el.innerHTML=q.createImgTag(3,0);}
document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-copy');(navigator.clipboard?navigator.clipboard.writeText(v):Promise.reject()).then(function(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy'},1500)}).catch(function(){prompt('Copy:',v)})})});
<?php if (!empty($o['print'])): ?>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)});<?php endif; ?>
})();
</script>
</body></html>
<?php return ob_get_clean();
}
