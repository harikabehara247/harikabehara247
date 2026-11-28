<?php /* Billing: the documents table (used by Overview, Invoices and Quotes). Expects $docs, $U, $pill. */ ?>
<table class="bl-t"><tr><th>Number</th><th>Date</th><th>Customer</th><th>For</th><th class="r">Total</th><th class="r">Balance</th><th>Status</th></tr>
<?php foreach ($docs as $r): ?><tr>
  <td><a href="<?= bill_h($U(['tab' => 'doc', 'id' => $r['id']])) ?>"><b><?= bill_h($r['number'] ?: 'Draft') ?></b></a><?= $r['kind'] === 'quote' ? ' <span class="bl-m">quote</span>' : '' ?></td>
  <td><?= bill_h(bill_date($r['issue_date'] ?: substr($r['created_at'], 0, 10), 'd M Y')) ?></td>
  <td><?= bill_h($r['bill_name']) ?></td><td class="bl-m"><?= bill_h($r['site'] ?: $r['subject']) ?></td>
  <td class="r"><?= bill_rs($r['total']) ?></td><td class="r"><?= $r['kind'] === 'invoice' && !in_array($r['status'], ['cancelled', 'draft'], true) ? bill_rs(bill_due($r)) : '—' ?></td>
  <td><?= $pill($r['status']) ?></td></tr>
<?php endforeach; if (!$docs): ?><tr><td colspan="7" class="bl-m">Nothing here yet.</td></tr><?php endif; ?></table>
