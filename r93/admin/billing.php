<?php
/* Billing (r93, 28 Nov 2026). Opens at /admin/?m=dashboard&go=billing
 * Invoices and quotes (HV/26-27/0001), clients, the payments register (every rupee in),
 * bank credits to match, manual orders, business settings, CSV export. */
adm_require('billing');
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/ix-core/relay-hooks.php';
require_once $ROOT . '/bill-core/render.php';
if (is_file($ROOT . '/bill-core/orders.php')) require_once $ROOT . '/bill-core/orders.php';
bill_install($pdo); relay_install($pdo);
$U = function (array $q = []) { return adm_url('billing', $q); };
$tab = preg_replace('/[^a-z]/', '', (string)($_GET['tab'] ?? 'home')) ?: 'home';
$P = bill_profile($pdo);
$stInv = ['draft' => ['Draft', 'muted'], 'sent' => ['Unpaid', 'warn'], 'part_paid' => ['Part paid', 'blue'], 'paid' => ['Paid', 'good'], 'cancelled' => ['Cancelled', 'bad'],
          'accepted' => ['Accepted', 'good'], 'declined' => ['Declined', 'bad'], 'converted' => ['Invoiced', 'blue']];
$methods = ['upi' => 'UPI', 'bank' => 'Bank transfer', 'cash' => 'Cash', 'card' => 'Card', 'gateway' => 'Gateway', 'other' => 'Other'];
$kinds = ['service' => 'Service / project', 'plan' => 'Membership plan', 'greeting' => 'Premium greeting', 'design' => 'Custom design', 'ads' => 'Ads / sponsorship', 'other' => 'Other income', 'refund' => 'Refund (money out)'];
if (function_exists('bill_sync')) { try { bill_sync($pdo); } catch (Throwable $e) {} }

/* ---------------------------------------------------------------- CSV export (before any output) */
if (($_GET['export'] ?? '') !== '') {
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : '2000-01-01';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : '2100-01-01';
    header('Content-Type: text/csv; charset=utf-8'); $ex = $_GET['export'] === 'invoices' ? 'invoices' : 'payments';
    header('Content-Disposition: attachment; filename="hview-' . $ex . '-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    if ($ex === 'payments') {
        fputcsv($out, ['Date', 'Amount (INR)', 'Method', 'Type', 'Source', 'Reference / UTR', 'Payer', 'Invoice', 'Order', 'Note', 'Status']);
        $s = $pdo->prepare('SELECT p.*, d.number FROM hv_bill_payments p LEFT JOIN hv_bill_docs d ON d.id=p.doc_id WHERE p.received_on BETWEEN ? AND ? ORDER BY p.received_on, p.id'); $s->execute([$from, $to]);
        foreach ($s as $r) fputcsv($out, [$r['received_on'], number_format($r['amount'] / 100, 2, '.', ''), $r['method'], $r['kind'], $r['source'], $r['ref'], $r['payer'], $r['number'], $r['order_ref'], $r['note'], $r['status']]);
    } else {
        fputcsv($out, ['Number', 'Date', 'Customer', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Balance', 'Status']);
        $s = $pdo->prepare("SELECT * FROM hv_bill_docs WHERE kind='invoice' AND number IS NOT NULL AND issue_date BETWEEN ? AND ? ORDER BY issue_date, id"); $s->execute([$from, $to]);
        foreach ($s as $r) fputcsv($out, [$r['number'], $r['issue_date'], $r['bill_name'], $r['subtotal'] / 100, $r['discount'] / 100, $r['tax'] / 100, $r['total'] / 100, $r['paid'] / 100, bill_due($r) / 100, $r['status']]);
    }
    fclose($out); exit;
}
/* printable page for the admin (works for drafts too) */
if (($_GET['view'] ?? '') !== '') { $d = bill_doc($pdo, (int)$_GET['view']); if ($d) { header('Content-Type: text/html; charset=utf-8'); echo bill_render($pdo, $d, ['admin' => 1, 'print' => !empty($_GET['print'])]); exit; } }

/* ---------------------------------------------------------------- actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $do = (string)($_POST['do'] ?? ''); $go = $U(['tab' => $tab]);
    $str = function ($k, $n = 190) { return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n); };
    $ymd = function ($k) { $v = (string)($_POST[$k] ?? ''); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null; };
    try {
    if ($do === 'save_doc') {
        $id = (int)($_POST['id'] ?? 0); $kind = ($_POST['kind'] ?? '') === 'quote' ? 'quote' : 'invoice';
        /* client: pick existing or create from the typed name */
        $cid = (int)($_POST['client_id'] ?? 0); $name = $str('bill_name', 160);
        if (!$cid && $name !== '') {
            $pdo->prepare('INSERT INTO hv_bill_clients (name, company, email, phone, address, gstin, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$name, $str('c_company', 160) ?: null, $str('c_email', 160) ?: null, preg_replace('/\D/', '', $str('c_phone', 20)) ?: null, $str('bill_extra', 600) ?: null, strtoupper($str('c_gstin', 20)) ?: null, bill_now(), bill_now()]);
            $cid = (int)$pdo->lastInsertId();
        }
        if ($cid && $name === '') { $c = bill_client($pdo, $cid); $name = $c ? $c['name'] : ''; }
        if ($name === '') throw new RuntimeException('Add the customer name.');
        $disc = ($_POST['disc_type'] ?? '') === 'percent' ? 'percent' : 'amount';
        $dv = $disc === 'percent' ? (int)round(min(100, max(0, (float)($_POST['disc_value'] ?? 0))) * 100) : bill_paise($_POST['disc_value'] ?? 0);
        $tax = $P['gstin'] ? (int)round(max(0, (float)($_POST['tax_rate'] ?? 0)) * 100) : 0;
        $f = ['kind' => $kind, 'client_id' => $cid ?: null, 'bill_name' => $name, 'bill_extra' => $str('bill_extra', 600) ?: null, 'site' => $str('site', 160) ?: null, 'subject' => $str('subject') ?: null,
              'issue_date' => $ymd('issue_date') ?: bill_today(), 'due_date' => $ymd('due_date'), 'terms' => $str('terms', 60) ?: null,
              'disc_label' => $str('disc_label', 80) ?: null, 'disc_type' => $disc, 'disc_value' => $dv, 'tax_label' => $tax ? ($str('tax_label', 40) ?: 'GST') : null, 'tax_bps' => $tax,
              'note_title' => $str('note_title', 80) ?: null, 'note_body' => $str('note_body', 2000) ?: null, 'footer' => $str('footer', 600) ?: null, 'internal' => $str('internal', 2000) ?: null,
              'order_ref' => $str('order_ref', 60) ?: null, 'updated_at' => bill_now()];
        if ($id) {
            $old = bill_doc($pdo, $id); if (!$old) throw new RuntimeException('Not found.');
            if (in_array($old['status'], ['paid', 'cancelled', 'converted'], true)) throw new RuntimeException('This ' . $old['kind'] . ' is ' . $old['status'] . ' and can no longer be edited. Duplicate it instead.');
            $set = implode(',', array_map(function ($k) { return "$k=?"; }, array_keys($f)));
            $pdo->prepare("UPDATE hv_bill_docs SET $set WHERE id=?")->execute(array_merge(array_values($f), [$id]));
            $pdo->prepare('DELETE FROM hv_bill_items WHERE doc_id=?')->execute([$id]);
            bill_log($pdo, $id, 'edited');
        } else {
            $f['token'] = bill_token(); $f['created_by'] = bill_who(); $f['created_at'] = bill_now(); $f['status'] = 'draft'; $f['from_doc'] = (int)($_POST['from_doc'] ?? 0) ?: null;
            $pdo->prepare('INSERT INTO hv_bill_docs (' . implode(',', array_keys($f)) . ') VALUES (' . rtrim(str_repeat('?,', count($f)), ',') . ')')->execute(array_values($f));
            $id = (int)$pdo->lastInsertId(); bill_log($pdo, $id, 'created');
        }
        $ins = $pdo->prepare('INSERT INTO hv_bill_items (doc_id, pos, title, details, qty, unit, rate, amount) VALUES (?,?,?,?,?,?,?,?)'); $n = 0;
        foreach ((array)($_POST['it_title'] ?? []) as $i => $t) {
            $t = mb_substr(trim((string)$t), 0, 190); if ($t === '') continue;
            $q = max(0, (float)($_POST['it_qty'][$i] ?? 1)) ?: 1; $r = bill_paise($_POST['it_rate'][$i] ?? 0);
            $ins->execute([$id, $n++, $t, mb_substr(trim((string)($_POST['it_details'][$i] ?? '')), 0, 2000) ?: null, $q, mb_substr(trim((string)($_POST['it_unit'][$i] ?? '')), 0, 20) ?: null, $r, (int)round($q * $r)]);
        }
        if (!$n) throw new RuntimeException('Add at least one line with a service name.');
        bill_recalc($pdo, $id);
        if (!empty($_POST['issue'])) { $d = bill_issue($pdo, $id); bill_log($pdo, $id, 'issued', $d['number']); adm_flash('success', ($kind === 'quote' ? 'Quote ' : 'Invoice ') . $d['number'] . ' is ready. Send the link to the customer.'); }
        else adm_flash('success', 'Saved as a draft. Nothing has been sent.');
        adm_log($pdo, 'bill_save', 'bill_doc', $id);
        adm_redirect($U(['tab' => 'doc', 'id' => $id]));
    }
    $id = (int)($_POST['id'] ?? 0); $d = $id ? bill_doc($pdo, $id) : null;
    if ($do === 'issue' && $d) { $d = bill_issue($pdo, $id); bill_log($pdo, $id, 'issued', $d['number']); adm_flash('success', $d['number'] . ' is ready to send.'); adm_redirect($U(['tab' => 'doc', 'id' => $id])); }
    if ($do === 'mark_sent' && $d) { $pdo->prepare('UPDATE hv_bill_docs SET sent_at=? WHERE id=?')->execute([bill_now(), $id]); bill_log($pdo, $id, 'sent', $str('via', 30)); exit('ok'); }
    if ($do === 'pay' && $d && $d['kind'] === 'invoice') {
        $amt = bill_paise($_POST['amount'] ?? 0); if ($amt <= 0) throw new RuntimeException('Enter the amount received.');
        if ($d['status'] === 'draft') { $d = bill_issue($pdo, $id); }
        bill_pay_add($pdo, ['received_on' => $ymd('received_on') ?: bill_today(), 'amount' => $amt, 'method' => array_key_exists($_POST['method'] ?? '', $methods) ? $_POST['method'] : 'upi',
            'ref' => preg_replace('/[^A-Za-z0-9\-\/]/', '', $str('ref', 40)) ?: null, 'payer' => $str('payer', 120) ?: null, 'account' => $str('account', 40) ?: null,
            'doc_id' => $id, 'client_id' => $d['client_id'], 'kind' => 'service', 'note' => $str('note', 255) ?: null]);
        bill_log($pdo, $id, 'payment recorded', bill_rs($amt)); adm_log($pdo, 'bill_payment', 'bill_doc', $id, bill_rs($amt));
        adm_flash('success', bill_rs($amt) . ' recorded against ' . $d['number'] . '.'); adm_redirect($U(['tab' => 'doc', 'id' => $id]));
    }
    if ($do === 'match' && $d) {
        $s = $pdo->prepare('SELECT * FROM hv_relay_msgs WHERE id=?'); $s->execute([(int)($_POST['msg'] ?? 0)]); $m = $s->fetch(PDO::FETCH_ASSOC);
        if ($d['status'] === 'draft') $d = bill_issue($pdo, $id);
        $ok = $m && bill_relay_link($pdo, $m, $d, bill_who());
        adm_flash($ok ? 'success' : 'error', $ok ? 'Bank credit linked to ' . $d['number'] . '.' : 'That credit is already used.'); adm_redirect($U(['tab' => 'doc', 'id' => $id]));
    }
    if ($do === 'cancel' && $d) {
        if ((int)$d['paid'] > 0) throw new RuntimeException('Money has been received on this invoice. Record a refund first.');
        $pdo->prepare("UPDATE hv_bill_docs SET status='cancelled', updated_at=? WHERE id=?")->execute([bill_now(), $id]); bill_log($pdo, $id, 'cancelled', $str('why', 200));
        adm_flash('success', ($d['number'] ?: 'Draft') . ' cancelled. The number stays used so the series has no gaps.'); adm_redirect($U(['tab' => 'doc', 'id' => $id]));
    }
    if ($do === 'delete_draft' && $d && $d['status'] === 'draft' && !$d['number']) {
        $pdo->prepare('DELETE FROM hv_bill_items WHERE doc_id=?')->execute([$id]); $pdo->prepare('DELETE FROM hv_bill_docs WHERE id=?')->execute([$id]);
        adm_flash('success', 'Draft deleted.'); adm_redirect($U(['tab' => $d['kind'] === 'quote' ? 'quotes' : 'invoices']));
    }
    if ($do === 'quote_status' && $d && $d['kind'] === 'quote') {
        $ns = in_array($_POST['to'] ?? '', ['accepted', 'declined', 'sent'], true) ? $_POST['to'] : 'sent';
        $pdo->prepare('UPDATE hv_bill_docs SET status=?, updated_at=? WHERE id=?')->execute([$ns, bill_now(), $id]); bill_log($pdo, $id, 'quote ' . $ns);
        adm_redirect($U(['tab' => 'doc', 'id' => $id]));
    }
    if (($do === 'to_invoice' || $do === 'duplicate') && $d) {
        $f = $d; unset($f['id']); $f['kind'] = $do === 'to_invoice' ? 'invoice' : $d['kind']; $f['number'] = null; $f['status'] = 'draft'; $f['paid'] = 0; $f['token'] = bill_token();
        $f['sent_at'] = $f['paid_at'] = $f['viewed_at'] = null; $f['issue_date'] = bill_today(); $f['due_date'] = null; $f['terms'] = $do === 'to_invoice' ? null : $d['terms'];
        $f['from_doc'] = $id; $f['created_by'] = bill_who(); $f['created_at'] = $f['updated_at'] = bill_now();
        $pdo->prepare('INSERT INTO hv_bill_docs (' . implode(',', array_keys($f)) . ') VALUES (' . rtrim(str_repeat('?,', count($f)), ',') . ')')->execute(array_values($f));
        $nid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO hv_bill_items (doc_id, pos, title, details, qty, unit, rate, amount) SELECT ?, pos, title, details, qty, unit, rate, amount FROM hv_bill_items WHERE doc_id=?')->execute([$nid, $id]);
        bill_recalc($pdo, $nid);
        if ($do === 'to_invoice') { $pdo->prepare("UPDATE hv_bill_docs SET status='converted' WHERE id=?")->execute([$id]); bill_log($pdo, $id, 'converted to invoice', '#' . $nid); }
        adm_flash('success', $do === 'to_invoice' ? 'Invoice draft made from the quote. Check it and press Issue.' : 'Copy made as a draft.'); adm_redirect($U(['tab' => 'edit', 'id' => $nid]));
    }
    if ($do === 'pay_free') { /* money with no invoice: other income, refunds */
        $amt = bill_paise($_POST['amount'] ?? 0); $k = array_key_exists($_POST['kind'] ?? '', $kinds) ? $_POST['kind'] : 'other';
        if ($amt <= 0) throw new RuntimeException('Enter the amount.'); if ($k === 'refund') $amt = -$amt;
        $doc = (int)($_POST['doc_id'] ?? 0) ?: null; $dd = $doc ? bill_doc($pdo, $doc) : null;
        bill_pay_add($pdo, ['received_on' => $ymd('received_on') ?: bill_today(), 'amount' => $amt, 'method' => array_key_exists($_POST['method'] ?? '', $methods) ? $_POST['method'] : 'upi', 'kind' => $k,
            'ref' => preg_replace('/[^A-Za-z0-9\-\/]/', '', $str('ref', 40)) ?: null, 'payer' => $str('payer', 120) ?: null, 'order_ref' => $str('order_ref', 60) ?: null, 'note' => $str('note', 255) ?: null,
            'doc_id' => $doc, 'client_id' => $dd ? $dd['client_id'] : null]);
        adm_log($pdo, 'bill_payment_free', 'bill', 0, bill_rs($amt)); adm_flash('success', ($amt < 0 ? 'Refund of ' : '') . bill_rs(abs($amt)) . ' added to the register.'); adm_redirect($U(['tab' => 'payments']));
    }
    if ($do === 'credit_other') { /* a bank credit that is real money but not for an invoice */
        $s = $pdo->prepare("SELECT * FROM hv_relay_msgs WHERE id=? AND status IN ('unmatched','noamount')"); $s->execute([(int)($_POST['msg'] ?? 0)]); $m = $s->fetch(PDO::FETCH_ASSOC);
        if ($m && $m['paise']) {
            $k = array_key_exists($_POST['kind'] ?? '', $kinds) ? $_POST['kind'] : 'other'; $at = (int)floor($m['received_at'] / 1000); $dt = new DateTime('@' . ($at ?: time())); $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            $pdo->prepare("UPDATE hv_relay_msgs SET status='matched', note=? WHERE id=?")->execute(['register: ' . $k, $m['id']]);
            bill_pay_add($pdo, ['received_on' => $dt->format('Y-m-d'), 'amount' => (int)$m['paise'], 'method' => 'upi', 'source' => 'relay', 'source_id' => 'r' . $m['id'], 'ref' => $m['ref'], 'payer' => $m['payer'], 'relay_msg' => $m['id'], 'kind' => $k, 'note' => $str('note', 255) ?: 'Bank credit']);
            adm_flash('success', bill_rs($m['paise']) . ' added to the register as ' . strtolower($kinds[$k]) . '.');
        }
        adm_redirect($U(['tab' => 'payments']));
    }
    if ($do === 'pay_void') {
        $pid = (int)($_POST['pid'] ?? 0); $s = $pdo->prepare('SELECT * FROM hv_bill_payments WHERE id=?'); $s->execute([$pid]); $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r && $r['source'] === 'manual') { $pdo->prepare("UPDATE hv_bill_payments SET status='void', note=CONCAT(COALESCE(note,''), ' [voided: ', ?, ']') WHERE id=?")->execute([$str('why', 100) ?: 'entered by mistake', $pid]); if ($r['doc_id']) { bill_apply($pdo, (int)$r['doc_id']); bill_log($pdo, $r['doc_id'], 'payment voided', bill_rs($r['amount'])); } adm_flash('success', 'Entry voided. It stays in the register, struck through.'); }
        adm_redirect($_POST['back'] ?? $U(['tab' => 'payments']));
    }
    if ($do === 'client') {
        $cid = (int)($_POST['cid'] ?? 0); $v = [$str('name', 120), $str('company', 160) ?: null, $str('email', 160) ?: null, preg_replace('/\D/', '', $str('phone', 20)) ?: null, $str('address', 600) ?: null, strtoupper($str('gstin', 20)) ?: null, $str('notes', 2000) ?: null, bill_now()];
        if ($v[0] === '') throw new RuntimeException('Add a name.');
        if ($cid) $pdo->prepare('UPDATE hv_bill_clients SET name=?, company=?, email=?, phone=?, address=?, gstin=?, notes=?, updated_at=? WHERE id=?')->execute(array_merge($v, [$cid]));
        else { $v[] = bill_now(); $pdo->prepare('INSERT INTO hv_bill_clients (name, company, email, phone, address, gstin, notes, updated_at, created_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute($v); }
        adm_flash('success', 'Client saved.'); adm_redirect($U(['tab' => 'clients']));
    }
    if ($do === 'settings') {
        $in = $_POST; if (!empty($in['show_qr_set']) && empty($in['show_qr'])) $in['show_qr'] = ''; $in['pay_mode'] = in_array($in['pay_mode'] ?? '', ['upi', 'none'], true) ? $in['pay_mode'] : 'upi';
        bill_profile_save($pdo, $in); if (isset($_POST['logo'])) bill_put($pdo, 'bill_logo', mb_substr(trim((string)$_POST['logo']), 0, 300));
        adm_log($pdo, 'bill_settings', 'bill'); adm_flash('success', 'Billing settings saved.'); adm_redirect($U(['tab' => 'settings']));
    }
    if (function_exists('bill_orders_post')) bill_orders_post($pdo, $do, $U);
    } catch (Throwable $e) { adm_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Could not save: ' . $e->getMessage()); adm_redirect($_SERVER['HTTP_REFERER'] ?? $go); }
    adm_redirect($go);
}

/* ---------------------------------------------------------------- page */
$pill = function ($st) use ($stInv) { [$l, $c] = $stInv[$st] ?? [$st, 'muted']; return '<span class="bl-pill ' . $c . '">' . bill_h($l) . '</span>'; };
$unmatched = $pdo->query("SELECT * FROM hv_relay_msgs WHERE status IN ('unmatched') AND paise IS NOT NULL ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
adm_head($pdo, 'billing', 'Billing');
adm_page_head('Billing', 'Invoices, quotes and every payment H View receives, in one place. Each invoice has its own link to share; payments your phone sees arriving are matched to invoices.');
$tabs = ['home' => 'Overview', 'invoices' => 'Invoices', 'quotes' => 'Quotes', 'payments' => 'Payments register', 'orders' => 'Manual orders', 'clients' => 'Clients', 'settings' => 'Settings'];
?>
<style>
.bl-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 16px}.bl-tabs a{padding:8px 14px;border-radius:999px;border:1px solid #e8e3f3;background:#fff;color:#3b3355;text-decoration:none;font-weight:600;font-size:14px}.bl-tabs a.on{background:#4f46e5;color:#fff;border-color:#4f46e5}
.bl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:16px}
.bl-card{background:#fff;border:1px solid #e8e3f3;border-radius:16px;padding:16px;margin-bottom:16px}.bl-card h3{margin:0 0 10px;font-size:16px}
.bl-k{font-size:26px;font-weight:800;color:#1b2347}.bl-m{color:#6b6480;font-size:13px}
.bl-t{width:100%;border-collapse:collapse;font-size:14px}.bl-t th,.bl-t td{padding:9px 8px;border-bottom:1px solid #f0edf7;text-align:left;vertical-align:top}.bl-t th{color:#6b6480;font-weight:600;font-size:13px}.bl-t .r{text-align:right;white-space:nowrap}
.bl-t tr.void td{text-decoration:line-through;color:#9ca3af}
.bl-pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700}.bl-pill.good{background:#dcfce7;color:#166534}.bl-pill.warn{background:#fef3c7;color:#92400e}.bl-pill.blue{background:#e0e7ff;color:#3730a3}.bl-pill.muted{background:#f1f5f9;color:#475569}.bl-pill.bad{background:#fee2e2;color:#991b1b}
.bl-btn{display:inline-block;font:600 14px system-ui;border:1px solid #d9d4ea;background:#fff;color:#2d2547;border-radius:10px;padding:8px 13px;cursor:pointer;text-decoration:none;margin:2px 4px 2px 0}
.bl-btn.pri{background:#4f46e5;border-color:#4f46e5;color:#fff}.bl-btn.ok{background:#16a34a;border-color:#16a34a;color:#fff}.bl-btn.danger{color:#b91c1c;border-color:#fecaca}.bl-btn.sm{padding:5px 9px;font-size:13px}
.bl-f{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 14px}.bl-f label{display:block;font-size:13px;font-weight:600;color:#4b4560}
.bl-f input,.bl-f select,.bl-f textarea,.bl-it input,.bl-it textarea{width:100%;box-sizing:border-box;border:1px solid #ddd8ea;border-radius:9px;padding:8px 10px;font:14px system-ui;margin-top:3px;background:#fff}
.bl-f .w2{grid-column:1/-1}
.bl-it{width:100%;border-collapse:collapse}.bl-it td{padding:6px 4px;vertical-align:top}.bl-it th{font-size:12px;color:#6b6480;text-align:left;padding:4px}
.bl-tot{margin-left:auto;max-width:340px;font-size:15px}.bl-tot div{display:flex;justify-content:space-between;padding:4px 0}.bl-tot .g{font-weight:800;border-top:2px solid #4f46e5;margin-top:4px;padding-top:8px}
.bl-link{font:600 13px ui-monospace,Menlo,monospace;background:#f6f3ff;border:1px dashed #a78bfa;border-radius:10px;padding:9px;word-break:break-all;user-select:all}
.bl-inline form{display:inline}.bl-sug{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:10px 12px;margin:6px 0}
.bl-frame{width:100%;height:900px;border:1px solid #e8e3f3;border-radius:14px;background:#eef0f6}
details.bl-d summary{cursor:pointer;font-weight:700;margin:6px 0}
</style>
<div class="bl-tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k || ($tab === 'doc' && $k === 'invoices') ? 'on' : '' ?>" href="<?= bill_h($U(['tab' => $k])) ?>"><?= bill_h($l) ?></a><?php endforeach; ?>
  <a href="<?= bill_h($U(['tab' => 'edit', 'kind' => 'invoice'])) ?>" style="margin-left:auto;background:#16a34a;color:#fff;border-color:#16a34a">+ New invoice</a>
  <a href="<?= bill_h($U(['tab' => 'edit', 'kind' => 'quote'])) ?>">+ New quote</a></div>
<?php
/* ================================================================ OVERVIEW */
if ($tab === 'home'):
    $m0 = (new DateTime('first day of this month', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    $fy0 = '20' . substr(bill_fy(bill_today()), 0, 2) . '-04-01';
    $sum = function ($w, $a = []) use ($pdo) { $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM hv_bill_payments WHERE status='ok' AND $w"); $s->execute($a); return (int)$s->fetchColumn(); };
    $month = $sum('received_on >= ?', [$m0]); $fy = $sum('received_on >= ?', [$fy0]);
    $out = $pdo->query("SELECT COALESCE(SUM(total-paid),0) n, COUNT(*) c FROM hv_bill_docs WHERE kind='invoice' AND status IN ('sent','part_paid')")->fetch(PDO::FETCH_ASSOC);
    $byKind = $pdo->prepare("SELECT kind, COALESCE(SUM(amount),0) s, COUNT(*) c FROM hv_bill_payments WHERE status='ok' AND received_on >= ? GROUP BY kind ORDER BY s DESC"); $byKind->execute([$m0]);
    $quotesOpen = (int)$pdo->query("SELECT COUNT(*) FROM hv_bill_docs WHERE kind='quote' AND status IN ('sent','accepted')")->fetchColumn();
?>
<div class="bl-grid">
  <div class="bl-card"><div class="bl-m">Received this month</div><div class="bl-k"><?= bill_rs($month) ?></div><div class="bl-m">From the payments register</div></div>
  <div class="bl-card"><div class="bl-m">Received this financial year</div><div class="bl-k"><?= bill_rs($fy) ?></div><div class="bl-m">Since 1 April</div></div>
  <div class="bl-card"><div class="bl-m">Waiting to be paid</div><div class="bl-k"><?= bill_rs($out['n']) ?></div><div class="bl-m"><?= (int)$out['c'] ?> open invoice<?= $out['c'] == 1 ? '' : 's' ?></div></div>
  <div class="bl-card"><div class="bl-m">Bank credits to sort</div><div class="bl-k"><?= count($unmatched) ?></div><div class="bl-m"><a href="<?= bill_h($U(['tab' => 'payments'])) ?>">Open the register</a> · <?= $quotesOpen ?> open quote<?= $quotesOpen == 1 ? '' : 's' ?></div></div>
</div>
<div class="bl-card"><h3>This month by type</h3><table class="bl-t"><tr><th>Type</th><th class="r">Payments</th><th class="r">Amount</th></tr>
<?php $any = false; foreach ($byKind as $r): $any = true; ?><tr><td><?= bill_h($kinds[$r['kind']] ?? $r['kind']) ?></td><td class="r"><?= (int)$r['c'] ?></td><td class="r"><?= bill_rs($r['s']) ?></td></tr><?php endforeach; if (!$any): ?><tr><td colspan="3" class="bl-m">Nothing received yet this month.</td></tr><?php endif; ?></table></div>
<div class="bl-card"><h3>Latest invoices</h3><?php $docs = $pdo->query("SELECT * FROM hv_bill_docs ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC); include __DIR__ . '/billing-list.inc.php'; ?></div>
<?php
/* ================================================================ LISTS */
elseif ($tab === 'invoices' || $tab === 'quotes'):
    $k = $tab === 'quotes' ? 'quote' : 'invoice'; $f = (string)($_GET['f'] ?? '');
    $w = "kind=?"; $a = [$k]; if ($f !== '' && isset($stInv[$f])) { $w .= ' AND status=?'; $a[] = $f; } elseif ($f === 'open') { $w .= " AND status IN ('sent','part_paid')"; }
    $q = trim((string)($_GET['q'] ?? '')); if ($q !== '') { $w .= ' AND (bill_name LIKE ? OR number LIKE ? OR site LIKE ?)'; $a[] = "%$q%"; $a[] = "%$q%"; $a[] = "%$q%"; }
    $s = $pdo->prepare("SELECT * FROM hv_bill_docs WHERE $w ORDER BY COALESCE(issue_date, DATE(created_at)) DESC, id DESC LIMIT 300"); $s->execute($a); $docs = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bl-card">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><?php foreach (['m' => 'dashboard', 'go' => 'billing', 'tab' => $tab] as $hk => $hv): ?><input type="hidden" name="<?= $hk ?>" value="<?= bill_h($hv) ?>"><?php endforeach; ?>
    <input name="q" value="<?= bill_h($q) ?>" placeholder="Search name, number or site" style="flex:1;min-width:200px;border:1px solid #ddd8ea;border-radius:9px;padding:8px 10px">
    <select name="f" style="border:1px solid #ddd8ea;border-radius:9px;padding:8px"><option value="">All</option><?php foreach ($k === 'invoice' ? ['open' => 'Unpaid (all)', 'draft' => 'Draft', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] : ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'converted' => 'Invoiced'] as $fk => $fl): ?><option value="<?= $fk ?>" <?= $f === $fk ? 'selected' : '' ?>><?= $fl ?></option><?php endforeach; ?></select>
    <button class="bl-btn">Filter</button>
    <?php if ($k === 'invoice'): ?><a class="bl-btn" href="<?= bill_h($U(['export' => 'invoices'])) ?>">Export CSV</a><?php endif; ?>
  </form>
  <?php include __DIR__ . '/billing-list.inc.php'; ?>
</div>
<?php
/* ================================================================ EDITOR */
elseif ($tab === 'edit'):
    $id = (int)($_GET['id'] ?? 0); $d = $id ? bill_doc($pdo, $id) : null; $kind = $d ? $d['kind'] : (($_GET['kind'] ?? '') === 'quote' ? 'quote' : 'invoice');
    $items = $d ? bill_items($pdo, $id) : [['title' => '', 'details' => '', 'qty' => 1, 'unit' => '', 'rate' => 0]];
    $clients = $pdo->query('SELECT id, name, company, phone, email, address FROM hv_bill_clients ORDER BY name LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC);
    $v = function ($k, $def = '') use ($d) { return bill_h($d[$k] ?? $def); };
    $locked = $d && in_array($d['status'], ['paid', 'cancelled', 'converted'], true);
?>
<?php if ($locked): ?><div class="bl-card bl-sug">This <?= $kind ?> is <?= bill_h($d['status']) ?> and can't be edited. Use <b>Duplicate</b> on its page to make a new one.</div><?php else: ?>
<form method="post" class="bl-card" id="bl-ed"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="save_doc"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="kind" value="<?= $kind ?>">
  <h3><?= $d ? 'Edit ' . bill_h($d['number'] ?: 'draft') : 'New ' . $kind ?></h3>
  <div class="bl-f">
    <div><label>Existing client<select name="client_id" id="bl-cl"><option value="0">— New client (type below) —</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" data-n="<?= bill_h($c['name']) ?>" data-x="<?= bill_h(trim(($c['company'] ? $c['company'] . "\n" : '') . ($c['address'] ? $c['address'] . "\n" : '') . ($c['phone'] ? '+' . ltrim($c['phone'], '+') . "\n" : '') . ($c['email'] ?: ''))) ?>" <?= $d && (int)$d['client_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= bill_h($c['name'] . ($c['company'] ? ' · ' . $c['company'] : '')) ?></option><?php endforeach; ?></select></label></div>
    <div><label>Customer name (Bill to)<input name="bill_name" id="bl-nm" required value="<?= $v('bill_name') ?>" placeholder="Sudhir Sharma"></label></div>
    <div><label>WhatsApp / phone (new client)<input name="c_phone" placeholder="91 98xxxxxxxx"></label></div>
    <div><label>Email (new client)<input name="c_email" type="email"></label></div>
    <div class="w2"><label>Details under the name (company, address, GSTIN, phone)<textarea name="bill_extra" id="bl-ex" rows="2"><?= $v('bill_extra') ?></textarea></label></div>
    <div><label>Service site / website<input name="site" value="<?= $v('site') ?>" placeholder="example.com"></label></div>
    <div><label>Subject<input name="subject" value="<?= $v('subject') ?>" placeholder="WordPress homepage recovery"></label></div>
    <div><label><?= $kind === 'quote' ? 'Quote date' : 'Invoice date' ?><input type="date" name="issue_date" value="<?= $v('issue_date', bill_today()) ?>"></label></div>
    <div><label><?= $kind === 'quote' ? 'Valid until' : 'Due date' ?> (optional)<input type="date" name="due_date" value="<?= $v('due_date') ?>"></label></div>
    <div><label><?= $kind === 'quote' ? 'Validity text' : 'Payment terms' ?><input name="terms" value="<?= $v('terms', $kind === 'quote' ? 'Valid for 15 days' : 'Due on receipt') ?>"></label></div>
    <div><label>Order reference (optional)<input name="order_ref" value="<?= $v('order_ref') ?>" placeholder="WhatsApp lead, HVX-…"></label></div>
  </div>
  <h3 style="margin-top:18px">Lines</h3>
  <table class="bl-it" id="bl-lines"><tr><th style="width:46%">Service and details</th><th>Qty</th><th>Unit</th><th>Rate (₹)</th><th class="r">Amount</th><th></th></tr>
  <?php foreach ($items as $it): ?>
    <tr><td><input name="it_title[]" value="<?= bill_h($it['title']) ?>" placeholder="Service name"><textarea name="it_details[]" rows="2" placeholder="What was done (optional)"><?= bill_h($it['details']) ?></textarea></td>
      <td><input name="it_qty[]" value="<?= bill_h(rtrim(rtrim(number_format((float)$it['qty'], 2, '.', ''), '0'), '.')) ?>" inputmode="decimal" style="width:70px"></td>
      <td><input name="it_unit[]" value="<?= bill_h($it['unit']) ?>" placeholder="" style="width:80px"></td>
      <td><input name="it_rate[]" value="<?= $it['rate'] ? number_format($it['rate'] / 100, 2, '.', '') : '' ?>" inputmode="decimal" style="width:110px"></td>
      <td class="r amt"></td><td><button type="button" class="bl-btn sm danger" data-del>✕</button></td></tr>
  <?php endforeach; ?></table>
  <button type="button" class="bl-btn sm" id="bl-add">+ Add line</button>
  <div class="bl-f" style="margin-top:14px">
    <div><label>Discount label<input name="disc_label" value="<?= $v('disc_label') ?>" placeholder="Black Friday offer"></label></div>
    <div><label>Discount type<select name="disc_type"><option value="amount">₹ amount</option><option value="percent" <?= $d && $d['disc_type'] === 'percent' ? 'selected' : '' ?>>% of subtotal</option></select></label></div>
    <div><label>Discount value<input name="disc_value" inputmode="decimal" value="<?= $d ? ($d['disc_type'] === 'percent' ? $d['disc_value'] / 100 : ($d['disc_value'] ? number_format($d['disc_value'] / 100, 2, '.', '') : '')) : '' ?>"></label></div>
    <?php if ($P['gstin']): ?><div><label>GST rate %<input name="tax_rate" inputmode="decimal" value="<?= $d ? $d['tax_bps'] / 100 : 18 ?>"></label><input type="hidden" name="tax_label" value="GST"></div><?php endif; ?>
  </div>
  <div class="bl-tot" id="bl-tot"></div>
  <details class="bl-d" <?= $d && ($d['note_body'] || $d['footer'] || $d['internal']) ? 'open' : '' ?>><summary>Note box, footer and private note</summary><div class="bl-f">
    <div><label>Note box title<input name="note_title" value="<?= $v('note_title', 'Follow-up finding') ?>"></label></div>
    <div class="w2"><label>Note box text (shown on the invoice)<textarea name="note_body" rows="3"><?= $v('note_body') ?></textarea></label></div>
    <div class="w2"><label>Extra footer line (shown)<textarea name="footer" rows="2"><?= $v('footer') ?></textarea></label></div>
    <div class="w2"><label>Private note (only you see it)<textarea name="internal" rows="2"><?= $v('internal') ?></textarea></label></div>
  </div></details>
  <div style="margin-top:14px"><button class="bl-btn" name="issue" value="">Save draft</button>
    <?php if (!$d || $d['status'] === 'draft'): ?><button class="bl-btn pri" name="issue" value="1"><?= $kind === 'quote' ? 'Save and make the quote' : 'Save and issue invoice' ?></button><?php else: ?><button class="bl-btn pri" name="issue" value="">Save changes</button><?php endif; ?>
    <span class="bl-m"><?= $d && $d['number'] ? '' : 'Issuing gives the next number (' . bill_h(($kind === 'quote' ? $P['qprefix'] : $P['prefix']) . '/' . bill_fy(bill_today())) . '/…). Nothing is sent to the customer by itself.' ?></span></div>
</form>
<script>
(function(){var t=document.getElementById('bl-lines'),tot=document.getElementById('bl-tot'),f=document.getElementById('bl-ed');
function n(v){v=parseFloat(String(v).replace(/,/g,''));return isNaN(v)?0:v}function rs(p){return '₹'+(p/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function calc(){var sub=0;t.querySelectorAll('tr').forEach(function(r){var q=r.querySelector('[name="it_qty[]"]');if(!q)return;var a=Math.round(n(q.value||1)*Math.round(n(r.querySelector('[name="it_rate[]"]').value)*100));r.querySelector('.amt').textContent=rs(a);sub+=a});
 var dt=f.disc_type.value,dv=n(f.disc_value.value),d=dt==='percent'?Math.round(sub*Math.min(100,dv)/100):Math.round(dv*100);d=Math.max(0,Math.min(sub,d));var tx=f.tax_rate?Math.round((sub-d)*n(f.tax_rate.value)/100):0;
 tot.innerHTML='<div><span>Subtotal</span><span>'+rs(sub)+'</span></div>'+(d?'<div><span>'+(f.disc_label.value||'Discount')+'</span><span>− '+rs(d)+'</span></div>':'')+(tx?'<div><span>GST</span><span>'+rs(tx)+'</span></div>':'')+'<div class="g"><span>Total</span><span>'+rs(sub-d+tx)+'</span></div>'}
document.getElementById('bl-add').onclick=function(){var r=t.querySelector('tr:last-child').cloneNode(true);r.querySelectorAll('input,textarea').forEach(function(i){i.value=i.name==='it_qty[]'?'1':''});t.appendChild(r);calc()};
t.addEventListener('click',function(e){if(e.target.hasAttribute('data-del')&&t.querySelectorAll('tr').length>2){e.target.closest('tr').remove();calc()}});
f.addEventListener('input',calc);calc();
var cl=document.getElementById('bl-cl');cl.addEventListener('change',function(){var o=cl.options[cl.selectedIndex];if(o.value!=='0'){document.getElementById('bl-nm').value=o.dataset.n;document.getElementById('bl-ex').value=o.dataset.x}});
f.addEventListener('submit',function(){f.querySelectorAll('button').forEach(function(b){setTimeout(function(){b.disabled=true},0)})});
})();
</script>
<?php endif; ?>
<?php
/* ================================================================ ONE DOCUMENT */
elseif ($tab === 'doc'):
    $id = (int)($_GET['id'] ?? ($_GET['doc'] ?? 0)); $d = bill_doc($pdo, $id);
    if (!$d): ?><div class="bl-card">Not found.</div><?php else:
    $link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'hview.in') . '/invoice/' . $d['token'] . '/'; $isQ = $d['kind'] === 'quote'; $due = bill_due($d);
    $c = bill_client($pdo, $d['client_id']); $ph = $c && $c['phone'] ? preg_replace('/\D/', '', $c['phone']) : ''; if ($ph && strlen($ph) === 10) $ph = '91' . $ph;
    $msg = 'Hello ' . $d['bill_name'] . ",\n\n" . ($isQ ? 'Here is our quotation ' . $d['number'] . ' for ' . bill_rs($d['total']) . ":\n" . $link . "\n\nYou can accept it on the page." : 'Here is your invoice ' . $d['number'] . ' from H View for ' . bill_rs($due) . ":\n" . $link . "\n\nYou can pay by UPI on the page (scan the QR or use the UPI ID). Please use " . $d['number'] . ' as the payment note.') . "\n\nThank you,\nH View";
    $wa = 'https://wa.me/' . $ph . '?text=' . rawurlencode($msg);
    $mail = 'mailto:' . rawurlencode($c['email'] ?? '') . '?subject=' . rawurlencode(($isQ ? 'Quotation ' : 'Invoice ') . $d['number'] . ' from H View') . '&body=' . rawurlencode($msg);
    $pays = $pdo->prepare('SELECT * FROM hv_bill_payments WHERE doc_id=? ORDER BY received_on, id'); $pays->execute([$id]); $pays = $pays->fetchAll(PDO::FETCH_ASSOC);
    $logs = $pdo->prepare('SELECT * FROM hv_bill_log WHERE doc_id=? ORDER BY id DESC LIMIT 30'); $logs->execute([$id]); $logs = $logs->fetchAll(PDO::FETCH_ASSOC);
    $sug = array_values(array_filter($unmatched, function ($m) use ($due) { return (int)$m['paise'] === $due; }));
?>
<div class="bl-card">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <div><h3 style="margin:0"><?= $isQ ? 'Quote' : 'Invoice' ?> <?= bill_h($d['number'] ?: '(draft)') ?> <?= $pill($d['status']) ?></h3>
      <div class="bl-m"><?= bill_h($d['bill_name']) ?> · Total <?= bill_rs($d['total']) ?><?= !$isQ ? ' · Paid ' . bill_rs($d['paid']) . ' · Balance ' . bill_rs($due) : '' ?><?= $d['viewed_at'] ? ' · Customer opened it ' . bill_h(bill_ist($d['viewed_at'])) : '' ?></div></div>
    <div class="bl-inline">
      <?php if ($d['status'] === 'draft'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="issue"><input type="hidden" name="id" value="<?= $id ?>"><button class="bl-btn pri"><?= $isQ ? 'Make quote' : 'Issue invoice' ?></button></form><?php endif; ?>
      <?php if (!in_array($d['status'], ['paid', 'cancelled', 'converted'], true)): ?><a class="bl-btn" href="<?= bill_h($U(['tab' => 'edit', 'id' => $id])) ?>">Edit</a><?php endif; ?>
      <a class="bl-btn" target="_blank" href="<?= bill_h($U(['view' => $id, 'print' => 1])) ?>">Print / PDF</a>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="duplicate"><input type="hidden" name="id" value="<?= $id ?>"><button class="bl-btn">Duplicate</button></form>
      <?php if ($isQ && in_array($d['status'], ['sent', 'accepted', 'draft'], true)): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="to_invoice"><input type="hidden" name="id" value="<?= $id ?>"><button class="bl-btn ok">Turn into invoice</button></form><?php endif; ?>
      <?php if ($isQ && $d['status'] === 'sent'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="quote_status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="to" value="accepted"><button class="bl-btn">Mark accepted</button></form><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="quote_status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="to" value="declined"><button class="bl-btn danger">Declined</button></form><?php endif; ?>
      <?php if ($d['status'] === 'draft' && !$d['number']): ?><form method="post" onsubmit="return confirm('Delete this draft for good?')"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="delete_draft"><input type="hidden" name="id" value="<?= $id ?>"><button class="bl-btn danger">Delete draft</button></form>
      <?php elseif (!$isQ && in_array($d['status'], ['sent', 'part_paid'], true) && !(int)$d['paid']): ?><form method="post" onsubmit="this.why.value=prompt('Why cancel this invoice? (kept in the history)')||'';return this.why.value!==''"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="why"><button class="bl-btn danger">Cancel invoice</button></form><?php endif; ?>
    </div>
  </div>
</div>
<?php if ($d['status'] !== 'draft' && $d['status'] !== 'cancelled'): ?>
<div class="bl-card"><h3>Send to the customer</h3>
  <div class="bl-link" id="bl-lk"><?= bill_h($link) ?></div>
  <div style="margin-top:8px">
    <button type="button" class="bl-btn" data-copy="<?= bill_h($link) ?>">Copy link</button>
    <button type="button" class="bl-btn" data-copy="<?= bill_h($msg) ?>">Copy message</button>
    <a class="bl-btn ok" target="_blank" rel="noopener" href="<?= bill_h($wa) ?>" data-sent="whatsapp">Open in WhatsApp<?= $ph ? '' : ' (choose chat)' ?></a>
    <a class="bl-btn" href="<?= bill_h($mail) ?>" data-sent="email">Email</a>
  </div>
  <div class="bl-m" style="margin-top:6px">WhatsApp and Email open with the message ready; you press send yourself.<?= $d['sent_at'] ? ' Last shared ' . bill_h(bill_ist($d['sent_at'])) . '.' : '' ?></div>
</div>
<?php endif; ?>
<?php if (!$isQ && !in_array($d['status'], ['paid', 'cancelled'], true)): ?>
<div class="bl-card"><h3>Payment received?</h3>
  <?php if ($sug): ?><div class="bl-sug"><b>Bank credit<?= count($sug) > 1 ? 's' : '' ?> with exactly <?= bill_rs($due) ?>:</b><?php foreach ($sug as $m): ?>
    <div style="margin-top:6px"><?= bill_h(bill_ist(gmdate('Y-m-d H:i:s', (int)floor($m['received_at'] / 1000)))) ?> · <?= bill_h($m['payer'] ?: 'payer not shown') ?><?= $m['ref'] ? ' · Ref ' . bill_h($m['ref']) : '' ?>
      <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="match"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="msg" value="<?= (int)$m['id'] ?>"><button class="bl-btn sm ok">This is the payment</button></form></div><?php endforeach; ?></div><?php endif; ?>
  <form method="post" class="bl-f"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="pay"><input type="hidden" name="id" value="<?= $id ?>">
    <div><label>Amount (₹)<input name="amount" value="<?= number_format($due / 100, 2, '.', '') ?>" inputmode="decimal" required></label></div>
    <div><label>Date received<input type="date" name="received_on" value="<?= bill_today() ?>"></label></div>
    <div><label>Method<select name="method"><?php foreach ($methods as $mk => $ml): ?><option value="<?= $mk ?>"><?= $ml ?></option><?php endforeach; ?></select></label></div>
    <div><label>UTR / reference<input name="ref" placeholder="12-digit UPI ref"></label></div>
    <div><label>Paid by (name on the payment)<input name="payer" value="<?= bill_h($d['bill_name']) ?>"></label></div>
    <div><label>Into account<input name="account" placeholder="SBI personal / current a/c"></label></div>
    <div class="w2"><label>Note<input name="note"></label></div>
    <div><button class="bl-btn ok">Record payment</button></div>
  </form>
  <div class="bl-m">Check your bank or UPI app first. The invoice turns Paid when the payments add up to the total.</div>
</div>
<?php endif; ?>
<?php if ($pays): ?><div class="bl-card"><h3>Payments on this invoice</h3><table class="bl-t"><tr><th>Date</th><th>Method</th><th>Ref</th><th>From</th><th>Source</th><th class="r">Amount</th><th></th></tr>
  <?php foreach ($pays as $r): ?><tr class="<?= $r['status'] === 'void' ? 'void' : '' ?>"><td><?= bill_h(bill_date($r['received_on'], 'd M Y')) ?></td><td><?= bill_h($methods[$r['method']] ?? $r['method']) ?></td><td><?= bill_h($r['ref']) ?></td><td><?= bill_h($r['payer']) ?></td><td><?= bill_h($r['source'] === 'relay' ? 'Bank relay' : 'Entered by ' . $r['created_by']) ?></td><td class="r"><?= bill_rs($r['amount']) ?></td>
    <td><?php if ($r['status'] === 'ok' && $r['source'] === 'manual'): ?><form method="post" onsubmit="this.why.value=prompt('Why void this entry?')||'';return this.why.value!==''"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="pay_void"><input type="hidden" name="pid" value="<?= (int)$r['id'] ?>"><input type="hidden" name="why"><input type="hidden" name="back" value="<?= bill_h($U(['tab' => 'doc', 'id' => $id])) ?>"><button class="bl-btn sm danger">Void</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<iframe class="bl-frame" src="<?= bill_h($U(['view' => $id])) ?>" title="Preview"></iframe>
<?php if ($d['internal']): ?><div class="bl-card"><h3>Private note</h3><div style="white-space:pre-line"><?= bill_h($d['internal']) ?></div></div><?php endif; ?>
<div class="bl-card"><h3>History</h3><table class="bl-t"><?php foreach ($logs as $l): ?><tr><td style="width:170px"><?= bill_h(bill_ist($l['created_at'])) ?></td><td><?= bill_h($l['action']) ?> <span class="bl-m"><?= bill_h($l['detail']) ?></span></td><td class="bl-m"><?= bill_h($l['user_name']) ?></td></tr><?php endforeach; ?></table></div>
<script>document.querySelectorAll('[data-sent]').forEach(function(a){a.addEventListener('click',function(){var b=new URLSearchParams();b.set('do','mark_sent');b.set('id','<?= $id ?>');b.set('via',a.dataset.sent);var c=document.querySelector('input[name=csrf]');if(c)b.set('csrf',c.value);fetch(location.href,{method:'POST',body:b,credentials:'same-origin'})})});</script>
<?php endif; ?>
<?php
/* ================================================================ PAYMENTS REGISTER */
elseif ($tab === 'payments'):
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : (new DateTime('first day of this month', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : bill_today();
    $s = $pdo->prepare('SELECT p.*, d.number, d.bill_name FROM hv_bill_payments p LEFT JOIN hv_bill_docs d ON d.id=p.doc_id WHERE p.received_on BETWEEN ? AND ? ORDER BY p.received_on DESC, p.id DESC LIMIT 1000'); $s->execute([$from, $to]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    $tot = 0; $in = 0; $outm = 0; foreach ($rows as $r) if ($r['status'] === 'ok') { $tot += $r['amount']; if ($r['amount'] > 0) $in += $r['amount']; else $outm += $r['amount']; }
    $open = $pdo->query("SELECT id, number, bill_name, total, paid FROM hv_bill_docs WHERE kind='invoice' AND status IN ('sent','part_paid','draft') ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if ($unmatched): ?><div class="bl-card"><h3>Bank credits not yet linked (<?= count($unmatched) ?>)</h3><div class="bl-m" style="margin-bottom:8px">Money your phone saw arrive that matched no ₹49 greeting, plan or invoice. Link each one so the register is complete.</div>
  <table class="bl-t"><tr><th>When</th><th class="r">Amount</th><th>From / Ref</th><th>Link it to</th></tr>
  <?php foreach ($unmatched as $m): ?><tr><td><?= bill_h(bill_ist(gmdate('Y-m-d H:i:s', (int)floor($m['received_at'] / 1000)))) ?></td><td class="r"><b><?= bill_rs($m['paise']) ?></b></td><td><?= bill_h($m['payer'] ?: '—') ?><br><span class="bl-m"><?= bill_h($m['ref'] ?: '') ?> <?= bill_h($m['note'] ?: '') ?></span></td>
    <td class="bl-inline"><form method="post" onsubmit="if(!this.id.value){alert('Pick an invoice');return false}"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="match"><select name="id" style="border:1px solid #ddd8ea;border-radius:8px;padding:5px;max-width:230px"><option value="">Invoice…</option><?php foreach ($open as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$o['total'] - (int)$o['paid'] === (int)$m['paise'] ? 'selected' : '' ?>><?= bill_h(($o['number'] ?: 'Draft') . ' · ' . $o['bill_name'] . ' · ' . bill_rs($o['total'] - $o['paid'])) ?></option><?php endforeach; ?></select><button class="bl-btn sm ok">Link</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="credit_other"><input type="hidden" name="msg" value="<?= (int)$m['id'] ?>"><select name="kind" style="border:1px solid #ddd8ea;border-radius:8px;padding:5px"><?php foreach ($kinds as $kk => $kl) if ($kk !== 'refund'): ?><option value="<?= $kk ?>" <?= $kk === 'other' ? 'selected' : '' ?>><?= $kl ?></option><?php endif; ?></select><input name="note" placeholder="Note" style="border:1px solid #ddd8ea;border-radius:8px;padding:5px;width:120px"><button class="bl-btn sm">Add as income</button></form>
      <form method="post" action="<?= bill_h(adm_url('pay-relay')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="ignore"><input type="hidden" name="msg" value="<?= (int)$m['id'] ?>"><button class="bl-btn sm danger" title="Personal money, not H View">Not H View</button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<div class="bl-card">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:10px"><input type="hidden" name="m" value="dashboard"><input type="hidden" name="go" value="billing"><input type="hidden" name="tab" value="payments">
    <label class="bl-m">From<br><input type="date" name="from" value="<?= bill_h($from) ?>" style="border:1px solid #ddd8ea;border-radius:9px;padding:7px"></label>
    <label class="bl-m">To<br><input type="date" name="to" value="<?= bill_h($to) ?>" style="border:1px solid #ddd8ea;border-radius:9px;padding:7px"></label>
    <button class="bl-btn">Show</button><a class="bl-btn" href="<?= bill_h($U(['export' => 'payments', 'from' => $from, 'to' => $to])) ?>">Export CSV</a>
    <span style="margin-left:auto" class="bl-m">In <b><?= bill_rs($in) ?></b><?= $outm ? ' · Refunds <b>' . bill_rs($outm) . '</b>' : '' ?> · Net <b style="color:#1b2347;font-size:16px"><?= bill_rs($tot) ?></b></span></form>
  <table class="bl-t"><tr><th>Date</th><th>Type</th><th>From</th><th>For</th><th>Method / Ref</th><th>Recorded by</th><th class="r">Amount</th><th></th></tr>
  <?php foreach ($rows as $r): ?><tr class="<?= $r['status'] === 'void' ? 'void' : '' ?>"><td><?= bill_h(bill_date($r['received_on'], 'd M')) ?></td><td><?= bill_h($kinds[$r['kind']] ?? $r['kind']) ?></td><td><?= bill_h($r['payer'] ?: '—') ?></td>
    <td><?php if ($r['doc_id']): ?><a href="<?= bill_h($U(['tab' => 'doc', 'id' => $r['doc_id']])) ?>"><?= bill_h($r['number'] ?: 'draft') ?></a> <?= bill_h($r['bill_name']) ?><?php else: ?><?= bill_h($r['order_ref'] ?: '') ?> <span class="bl-m"><?= bill_h($r['note']) ?></span><?php endif; ?></td>
    <td><?= bill_h($methods[$r['method']] ?? $r['method']) ?><br><span class="bl-m"><?= bill_h($r['ref']) ?></span></td><td class="bl-m"><?= bill_h(['relay' => 'Bank relay', 'ix' => 'Greeting order', 'plan' => 'Plan order', 'manual' => $r['created_by']][$r['source']] ?? $r['source']) ?></td>
    <td class="r"><b><?= bill_rs($r['amount']) ?></b></td>
    <td><?php if ($r['status'] === 'ok' && $r['source'] === 'manual'): ?><form method="post" onsubmit="this.why.value=prompt('Why void this entry?')||'';return this.why.value!==''"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="pay_void"><input type="hidden" name="pid" value="<?= (int)$r['id'] ?>"><input type="hidden" name="why"><button class="bl-btn sm danger">Void</button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="bl-m">No payments in these dates.</td></tr><?php endif; ?></table>
</div>
<div class="bl-card"><h3>Add money that has no invoice</h3><div class="bl-m" style="margin-bottom:8px">Cash, a bank transfer, ads income, or a refund you paid back. For a customer job, make an invoice instead so they get a bill.</div>
  <form method="post" class="bl-f"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="pay_free">
    <div><label>Type<select name="kind"><?php foreach ($kinds as $kk => $kl): ?><option value="<?= $kk ?>"><?= $kl ?></option><?php endforeach; ?></select></label></div>
    <div><label>Amount (₹)<input name="amount" inputmode="decimal" required></label></div>
    <div><label>Date<input type="date" name="received_on" value="<?= bill_today() ?>"></label></div>
    <div><label>Method<select name="method"><?php foreach ($methods as $mk => $ml): ?><option value="<?= $mk ?>"><?= $ml ?></option><?php endforeach; ?></select></label></div>
    <div><label>From / to (name)<input name="payer"></label></div>
    <div><label>UTR / reference<input name="ref"></label></div>
    <div><label>Order reference<input name="order_ref" placeholder="HVX-…"></label></div>
    <div><label>Note<input name="note"></label></div>
    <div><button class="bl-btn ok">Add to register</button></div>
  </form></div>
<?php
/* ================================================================ MANUAL ORDERS */
elseif ($tab === 'orders'):
    if (function_exists('bill_orders_page')) bill_orders_page($pdo, $U); else echo '<div class="bl-card">Manual orders are being set up.</div>';
/* ================================================================ CLIENTS */
elseif ($tab === 'clients'):
    $cl = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM hv_bill_docs d WHERE d.client_id=c.id AND d.kind='invoice' AND d.number IS NOT NULL) inv, (SELECT COALESCE(SUM(amount),0) FROM hv_bill_payments p WHERE p.client_id=c.id AND p.status='ok') got, (SELECT COALESCE(SUM(total-paid),0) FROM hv_bill_docs d WHERE d.client_id=c.id AND d.status IN ('sent','part_paid')) owe FROM hv_bill_clients c ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
    $ed = (int)($_GET['cid'] ?? 0); $e = $ed ? bill_client($pdo, $ed) : null;
?>
<div class="bl-card"><table class="bl-t"><tr><th>Client</th><th>Contact</th><th class="r">Invoices</th><th class="r">Paid</th><th class="r">Owes</th><th></th></tr>
<?php foreach ($cl as $c): ?><tr><td><b><?= bill_h($c['name']) ?></b><br><span class="bl-m"><?= bill_h($c['company']) ?></span></td><td class="bl-m"><?= bill_h(($c['phone'] ? '+' . $c['phone'] : '') . ' ' . $c['email']) ?></td><td class="r"><?= (int)$c['inv'] ?></td><td class="r"><?= bill_rs($c['got']) ?></td><td class="r"><?= bill_rs($c['owe']) ?></td>
  <td><a class="bl-btn sm" href="<?= bill_h($U(['tab' => 'clients', 'cid' => $c['id']])) ?>">Edit</a><a class="bl-btn sm" href="<?= bill_h($U(['tab' => 'invoices', 'q' => $c['name']])) ?>">Invoices</a></td></tr><?php endforeach; if (!$cl): ?><tr><td colspan="6" class="bl-m">No clients yet. They are added when you make an invoice.</td></tr><?php endif; ?></table></div>
<form method="post" class="bl-card"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="client"><input type="hidden" name="cid" value="<?= (int)$ed ?>"><h3><?= $e ? 'Edit ' . bill_h($e['name']) : 'Add a client' ?></h3><div class="bl-f">
  <?php foreach (['name' => 'Name', 'company' => 'Company', 'phone' => 'WhatsApp / phone', 'email' => 'Email', 'gstin' => 'GSTIN (if they have one)'] as $k => $l): ?><div><label><?= $l ?><input name="<?= $k ?>" value="<?= bill_h($e[$k] ?? '') ?>" <?= $k === 'name' ? 'required' : '' ?>></label></div><?php endforeach; ?>
  <div class="w2"><label>Address<textarea name="address" rows="2"><?= bill_h($e['address'] ?? '') ?></textarea></label></div><div class="w2"><label>Private notes<textarea name="notes" rows="2"><?= bill_h($e['notes'] ?? '') ?></textarea></label></div>
  <div><button class="bl-btn pri">Save client</button></div></div></form>
<?php
/* ================================================================ SETTINGS */
elseif ($tab === 'settings'):
    $logo = (string)bill_get($pdo, 'bill_logo', '');
?>
<form method="post" class="bl-card"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="settings"><h3>Business details on every invoice</h3><div class="bl-f">
  <?php foreach (['name' => 'Business name', 'tagline' => 'Line under the name', 'email' => 'Email', 'phone' => 'Phone (optional)', 'website' => 'Website', 'pan' => 'PAN (optional)', 'gstin' => 'GSTIN (leave empty until registered)', 'upi' => 'UPI ID customers pay to', 'payee' => 'Name shown in the UPI app', 'prefix' => 'Invoice prefix', 'qprefix' => 'Quote prefix'] as $k => $l): ?>
    <div><label><?= $l ?><input name="<?= $k ?>" value="<?= bill_h($P[$k]) ?>"></label></div><?php endforeach; ?>
  <div><label>How customers pay<select name="pay_mode"><option value="upi" <?= $P['pay_mode'] === 'upi' ? 'selected' : '' ?>>UPI ID on the invoice</option><option value="none" <?= $P['pay_mode'] === 'none' ? 'selected' : '' ?>>Don't show payment details</option></select></label></div>
  <div><label><input type="checkbox" name="show_qr" value="1" <?= $P['show_qr'] ? 'checked' : '' ?> style="width:auto"> Show a small UPI QR</label><input type="hidden" name="show_qr_set" value="1"></div>
  <div class="w2"><label>Online payment link (when the gateway is ready; {token} and {number} are filled in)<input name="pay_link" value="<?= bill_h($P['pay_link']) ?>" placeholder="https://hview.in/pay/invoice/{token}/"></label></div>
  <div><label>Logo image URL<input name="logo" value="<?= bill_h($logo) ?>"></label></div>
  <div class="w2"><label>Address (optional)<textarea name="address" rows="2"><?= bill_h($P['address']) ?></textarea></label></div>
  <div class="w2"><label>Thank-you line<input name="footer" value="<?= bill_h($P['footer']) ?>"></label></div>
  <div class="w2"><label>Line shown while there is no GSTIN<input name="nogst" value="<?= bill_h($P['nogst']) ?>"></label></div>
  <div><button class="bl-btn pri">Save</button></div></div>
  <p class="bl-m">Numbers run per financial year with no gaps: <?= bill_h($P['prefix'] . '/' . bill_fy(bill_today())) ?>/0001, 0002 … A cancelled invoice keeps its number. When the H View current account opens, change the UPI ID here; old invoices keep working.</p></form>
<?php endif; ?>
<script>document.querySelectorAll('.bl-card [data-copy]').forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-copy'),t=b.textContent;(navigator.clipboard?navigator.clipboard.writeText(v):Promise.reject()).then(function(){b.textContent='Copied';setTimeout(function(){b.textContent=t},1500)}).catch(function(){prompt('Copy:',v)})})});</script>
<?php adm_foot();
