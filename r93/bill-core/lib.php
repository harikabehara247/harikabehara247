<?php
/* =====================================================================
 * H View Billing (r93, 28 Nov 2026): clients, invoices, quotes, payments register.
 * Money is stored in paise (INT). Dates for documents are IST calendar dates;
 * created_at / updated_at are UTC like the rest of the site.
 *
 *   bill_install($pdo)                       tables
 *   bill_profile($pdo)                       business details (Billing › Settings)
 *   bill_issue($pdo, $id)                    draft -> issued, gives HV/26-27/0001
 *   bill_recalc($pdo, $id)                   totals from the lines
 *   bill_pay_add($pdo, [...])                one row in the payments register (idempotent)
 *   bill_apply($pdo, $docId)                 paid / part paid from the register
 *   bill_relay_try($pdo, $msgRow)            a bank credit that matched no order -> an open invoice
 *   bill_sync($pdo)                          copies paid ₹49 unlocks and plans into the register
 * ===================================================================== */
if (defined('HV_BILL')) return; define('HV_BILL', 1);
require_once dirname(__DIR__) . '/ix-core/relay.php';

function bill_now() { return gmdate('Y-m-d H:i:s'); }
function bill_today() { $d = new DateTime('now', new DateTimeZone('Asia/Kolkata')); return $d->format('Y-m-d'); }
function bill_rs($p, $sym = true) { $p = (int)$p; $s = ($p < 0 ? '-' : '') . ($sym ? '₹' : '') . bill_inr(abs($p) / 100); return $s; }
/* Indian grouping: 1,23,456.00 */
function bill_inr($n) {
    $n = number_format((float)$n, 2, '.', ''); [$i, $d] = explode('.', $n);
    if (strlen($i) > 3) { $last = substr($i, -3); $rest = substr($i, 0, -3); $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest); $i = $rest . ',' . $last; }
    return $i . '.' . $d;
}
function bill_paise($s) { $s = str_replace([',', '₹', ' '], '', (string)$s); return is_numeric($s) ? (int)round(((float)$s) * 100) : 0; }
function bill_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bill_date($ymd, $fmt = 'j F Y') { if (!$ymd) return ''; try { return (new DateTime($ymd))->format($fmt); } catch (Throwable $e) { return (string)$ymd; } }
function bill_ist($utc, $fmt = 'd M Y, H:i') { if (!$utc) return '—'; try { $d = new DateTime($utc . ' UTC'); $d->setTimezone(new DateTimeZone('Asia/Kolkata')); return $d->format($fmt); } catch (Throwable $e) { return (string)$utc; } }
function bill_token() { $a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; $t = ''; for ($i = 0; $i < 14; $i++) $t .= $a[random_int(0, strlen($a) - 1)]; return $t; }
function bill_get(PDO $pdo, $k, $d = '') { return relay_set($pdo, $k, $d); }
function bill_put(PDO $pdo, $k, $v) { relay_put($pdo, $k, $v); }

function bill_install(PDO $pdo) {
    static $done = false; if ($done) return; $done = true;
    if (bill_get($pdo, 'bill_schema', '') === '1') return;
    $T = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_clients (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, company VARCHAR(160) NULL,
        email VARCHAR(160) NULL, phone VARCHAR(20) NULL, address TEXT NULL, gstin VARCHAR(20) NULL, notes TEXT NULL,
        user_id INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, KEY (phone), KEY (email))$T");
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_docs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL DEFAULT 'invoice',
        number VARCHAR(40) NULL, client_id INT UNSIGNED NULL, bill_name VARCHAR(160) NOT NULL DEFAULT '', bill_extra TEXT NULL,
        site VARCHAR(160) NULL, subject VARCHAR(190) NULL, issue_date DATE NULL, due_date DATE NULL, terms VARCHAR(60) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'draft',
        subtotal INT NOT NULL DEFAULT 0, disc_label VARCHAR(80) NULL, disc_type VARCHAR(8) NOT NULL DEFAULT 'amount', disc_value INT NOT NULL DEFAULT 0, discount INT NOT NULL DEFAULT 0,
        tax_label VARCHAR(40) NULL, tax_bps INT NOT NULL DEFAULT 0, tax INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 0, paid INT NOT NULL DEFAULT 0,
        note_title VARCHAR(80) NULL, note_body TEXT NULL, footer TEXT NULL, internal TEXT NULL,
        token VARCHAR(20) NOT NULL, from_doc INT UNSIGNED NULL, order_ref VARCHAR(60) NULL,
        sent_at DATETIME NULL, paid_at DATETIME NULL, viewed_at DATETIME NULL,
        created_by VARCHAR(60) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_bill_num (kind, number), UNIQUE KEY uq_bill_tok (token), KEY (status), KEY (client_id))$T");
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, doc_id INT UNSIGNED NOT NULL, pos INT NOT NULL DEFAULT 0,
        title VARCHAR(190) NOT NULL, details TEXT NULL, qty DECIMAL(10,2) NOT NULL DEFAULT 1, unit VARCHAR(20) NULL,
        rate INT NOT NULL DEFAULT 0, amount INT NOT NULL DEFAULT 0, KEY (doc_id))$T");
    /* the register: every rupee in (and out, as refunds) */
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, received_on DATE NOT NULL, amount INT NOT NULL,
        method VARCHAR(12) NOT NULL DEFAULT 'upi', source VARCHAR(16) NOT NULL DEFAULT 'manual', source_id VARCHAR(40) NULL,
        ref VARCHAR(40) NULL, payer VARCHAR(120) NULL, account VARCHAR(40) NULL,
        doc_id INT UNSIGNED NULL, client_id INT UNSIGNED NULL, order_ref VARCHAR(60) NULL, relay_msg INT NULL,
        kind VARCHAR(16) NOT NULL DEFAULT 'service', note VARCHAR(255) NULL, status VARCHAR(10) NOT NULL DEFAULT 'ok',
        created_by VARCHAR(60) NULL, created_at DATETIME NOT NULL,
        UNIQUE KEY uq_bill_src (source, source_id), KEY (doc_id), KEY (received_on))$T");
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_seq (series VARCHAR(30) NOT NULL PRIMARY KEY, last_no INT NOT NULL DEFAULT 0)$T");
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_bill_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, doc_id INT UNSIGNED NULL, action VARCHAR(60) NOT NULL, detail VARCHAR(255) NULL,
        user_name VARCHAR(60) NULL, created_at DATETIME NOT NULL, KEY (doc_id))$T");
    bill_put($pdo, 'bill_schema', '1');
}
function bill_log(PDO $pdo, $docId, $action, $detail = '', $who = null) {
    try { $pdo->prepare('INSERT INTO hv_bill_log (doc_id, action, detail, user_name, created_at) VALUES (?,?,?,?,?)')->execute([$docId ?: null, $action, mb_substr((string)$detail, 0, 255), $who ?: bill_who(), bill_now()]); } catch (Throwable $e) {}
}
function bill_who() { $u = function_exists('adm_user') ? adm_user() : null; if (is_array($u)) return (string)($u['name'] ?? ($u['username'] ?? ($u['email'] ?? 'admin'))); if (is_string($u) && $u !== '') return $u; return 'admin'; }

/* ---------------------------------------------------------------- business profile */
function bill_profile(PDO $pdo) {
    $d = [
        'name' => 'H View Digital Solutions', 'tagline' => 'Website repair, design, SEO and development',
        'email' => 'connect@hview.in', 'phone' => '', 'website' => 'hview.in', 'address' => '', 'pan' => '', 'gstin' => '',
        'upi' => '', 'payee' => '', 'prefix' => 'HV', 'qprefix' => 'HVQ', 'due_days' => '0',
        'footer' => 'Thank you for choosing H View.', 'nogst' => 'Not registered under GST. No GST has been charged.',
        'pay_mode' => 'upi', 'pay_link' => '', 'show_qr' => '',
    ];
    $j = json_decode((string)bill_get($pdo, 'bill_profile', '{}'), true) ?: [];
    $p = array_merge($d, array_intersect_key($j, $d));
    if ($p['upi'] === '') { foreach (['upi_id', 'ix_upi_id', 'ac_upi_id', 'site_upi'] as $k) { $v = trim((string)bill_get($pdo, $k, '')); if ($v !== '' && strpos($v, '@') !== false) { $p['upi'] = $v; break; } } }
    return $p;
}
function bill_profile_save(PDO $pdo, array $in) {
    $p = bill_profile($pdo); foreach ($p as $k => $v) if (array_key_exists($k, $in)) $p[$k] = mb_substr(trim((string)$in[$k]), 0, $k === 'address' || $k === 'footer' || $k === 'nogst' || $k === 'pay_link' ? 400 : 120);
    $p['prefix'] = preg_replace('/[^A-Z0-9]/', '', strtoupper($p['prefix'])) ?: 'HV';
    $p['qprefix'] = preg_replace('/[^A-Z0-9]/', '', strtoupper($p['qprefix'])) ?: 'HVQ';
    $p['gstin'] = strtoupper(preg_replace('/\s+/', '', $p['gstin'])); $p['pan'] = strtoupper(preg_replace('/\s+/', '', $p['pan']));
    bill_put($pdo, 'bill_profile', json_encode($p, JSON_UNESCAPED_UNICODE));
    return $p;
}

/* ---------------------------------------------------------------- numbering: HV/26-27/0001 (one series per financial year, no gaps) */
function bill_fy($ymd) { $t = strtotime($ymd ?: bill_today()); $y = (int)date('Y', $t); if ((int)date('n', $t) < 4) $y--; return sprintf('%02d-%02d', $y % 100, ($y + 1) % 100); }
function bill_next_number(PDO $pdo, $kind, $ymd) {
    $p = bill_profile($pdo); $pre = $kind === 'quote' ? $p['qprefix'] : $p['prefix']; $fy = bill_fy($ymd); $series = $pre . '/' . $fy;
    $pdo->prepare('INSERT IGNORE INTO hv_bill_seq (series, last_no) VALUES (?, 0)')->execute([$series]);
    $pdo->prepare('UPDATE hv_bill_seq SET last_no = LAST_INSERT_ID(last_no + 1) WHERE series=?')->execute([$series]);
    $n = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    return $series . '/' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/* ---------------------------------------------------------------- documents */
function bill_doc(PDO $pdo, $id) { $s = $pdo->prepare('SELECT * FROM hv_bill_docs WHERE id=?'); $s->execute([(int)$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function bill_doc_by_token(PDO $pdo, $t) { if (!preg_match('/^[A-Za-z0-9]{10,20}$/', (string)$t)) return null; $s = $pdo->prepare('SELECT * FROM hv_bill_docs WHERE token=?'); $s->execute([$t]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function bill_items(PDO $pdo, $id) { $s = $pdo->prepare('SELECT * FROM hv_bill_items WHERE doc_id=? ORDER BY pos, id'); $s->execute([(int)$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
function bill_client(PDO $pdo, $id) { if (!$id) return null; $s = $pdo->prepare('SELECT * FROM hv_bill_clients WHERE id=?'); $s->execute([(int)$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function bill_due(array $d) { return max(0, (int)$d['total'] - (int)$d['paid']); }

function bill_recalc(PDO $pdo, $id) {
    $d = bill_doc($pdo, $id); if (!$d) return null;
    $sub = 0; foreach (bill_items($pdo, $id) as $it) { $amt = (int)round((float)$it['qty'] * (int)$it['rate']); if ($amt !== (int)$it['amount']) $pdo->prepare('UPDATE hv_bill_items SET amount=? WHERE id=?')->execute([$amt, $it['id']]); $sub += $amt; }
    $disc = $d['disc_type'] === 'percent' ? (int)round($sub * (int)$d['disc_value'] / 10000) : (int)$d['disc_value'];
    $disc = max(0, min($sub, $disc));
    $tax = (int)round(($sub - $disc) * (int)$d['tax_bps'] / 10000);
    $total = $sub - $disc + $tax;
    $pdo->prepare('UPDATE hv_bill_docs SET subtotal=?, discount=?, tax=?, total=?, updated_at=? WHERE id=?')->execute([$sub, $disc, $tax, $total, bill_now(), $id]);
    bill_apply($pdo, $id);
    return bill_doc($pdo, $id);
}
/* draft -> issued (gets its number). Quotes: draft -> sent. */
function bill_issue(PDO $pdo, $id) {
    $d = bill_doc($pdo, $id); if (!$d) return null;
    if (!$d['number']) {
        $ymd = $d['issue_date'] ?: bill_today();
        $pdo->prepare('UPDATE hv_bill_docs SET number=?, issue_date=? WHERE id=? AND number IS NULL')->execute([bill_next_number($pdo, $d['kind'], $ymd), $ymd, $id]);
    }
    if ($d['status'] === 'draft') $pdo->prepare("UPDATE hv_bill_docs SET status='sent', sent_at=COALESCE(sent_at, ?), updated_at=? WHERE id=?")->execute([bill_now(), bill_now(), $id]);
    bill_apply($pdo, $id);
    return bill_doc($pdo, $id);
}
/* status from the register (invoices only) */
function bill_apply(PDO $pdo, $id) {
    $d = bill_doc($pdo, $id); if (!$d || $d['kind'] !== 'invoice') return;
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM hv_bill_payments WHERE doc_id=? AND status='ok'"); $s->execute([$id]); $paid = (int)$s->fetchColumn();
    $st = $d['status'];
    if (in_array($st, ['cancelled', 'draft'], true)) { $pdo->prepare('UPDATE hv_bill_docs SET paid=? WHERE id=?')->execute([$paid, $id]); return; }
    if ($d['total'] > 0 && $paid >= (int)$d['total']) $st = 'paid'; elseif ($paid > 0) $st = 'part_paid'; else $st = 'sent';
    $pdo->prepare('UPDATE hv_bill_docs SET paid=?, status=?, paid_at=CASE WHEN ?=\'paid\' THEN COALESCE(paid_at, ?) ELSE NULL END, updated_at=? WHERE id=?')->execute([$paid, $st, $st, bill_now(), bill_now(), $id]);
}

/* ---------------------------------------------------------------- the register */
function bill_pay_add(PDO $pdo, array $r) {
    $row = array_merge(['received_on' => bill_today(), 'amount' => 0, 'method' => 'upi', 'source' => 'manual', 'source_id' => null, 'ref' => null, 'payer' => null, 'account' => null,
        'doc_id' => null, 'client_id' => null, 'order_ref' => null, 'relay_msg' => null, 'kind' => 'service', 'note' => null, 'status' => 'ok', 'created_by' => bill_who()], $r);
    if ($row['source_id'] === null && $row['source'] === 'manual') $row['source_id'] = 'm' . bin2hex(random_bytes(6));
    $cols = array_keys($row); $cols[] = 'created_at'; $vals = array_values($row); $vals[] = bill_now();
    try {
        $pdo->prepare('INSERT INTO hv_bill_payments (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')')->execute($vals);
    } catch (Throwable $e) { return 0; } /* already in the register */
    $pid = (int)$pdo->lastInsertId();
    if ($row['doc_id']) bill_apply($pdo, (int)$row['doc_id']);
    return $pid;
}

/* A bank credit that matched no ₹49 / plan order. Orders always carry paise (₹49.37), invoices are
 * whole amounts, so the two never collide. Auto-match only when exactly one open invoice has that
 * exact amount due; otherwise it waits on Billing › Payments as a suggestion. */
function bill_relay_try(PDO $pdo, $msgRow) {
    bill_install($pdo);
    $s = $pdo->prepare('SELECT * FROM hv_relay_msgs WHERE id=?'); $s->execute([(int)$msgRow]); $m = $s->fetch(PDO::FETCH_ASSOC);
    if (!$m || $m['status'] !== 'unmatched' || !$m['paise']) return null;
    $q = $pdo->prepare("SELECT * FROM hv_bill_docs WHERE kind='invoice' AND status IN ('sent','part_paid') AND (total - paid) = ?"); $q->execute([(int)$m['paise']]);
    $c = $q->fetchAll(PDO::FETCH_ASSOC);
    if (count($c) !== 1) { if (count($c) > 1) $pdo->prepare("UPDATE hv_relay_msgs SET note='several invoices with this amount: match on Billing' WHERE id=?")->execute([$m['id']]); return null; }
    return bill_relay_link($pdo, $m, $c[0], 'bank relay') ? $c[0]['number'] : null;
}
function bill_relay_link(PDO $pdo, array $m, array $doc, $who) {
    $u = $pdo->prepare("UPDATE hv_relay_msgs SET status='matched', note=? WHERE id=? AND status IN ('unmatched','noamount')"); $u->execute(['invoice ' . $doc['number'], $m['id']]);
    if ($u->rowCount() !== 1) return false;
    $at = (int)floor(((int)$m['received_at']) / 1000); $d = new DateTime('@' . ($at ?: time())); $d->setTimezone(new DateTimeZone('Asia/Kolkata'));
    bill_pay_add($pdo, ['received_on' => $d->format('Y-m-d'), 'amount' => (int)$m['paise'], 'method' => 'upi', 'source' => 'relay', 'source_id' => 'r' . $m['id'], 'ref' => $m['ref'], 'payer' => $m['payer'],
        'doc_id' => $doc['id'], 'client_id' => $doc['client_id'], 'relay_msg' => $m['id'], 'kind' => 'service', 'note' => 'Bank credit matched to ' . $doc['number'], 'created_by' => $who]);
    bill_log($pdo, $doc['id'], 'payment received', bill_rs($m['paise']) . ($m['ref'] ? ' Ref ' . $m['ref'] : '') . ' (' . $who . ')', $who);
    try { $f = dirname(__DIR__) . '/admin-core/notify.php'; if (is_file($f)) { require_once $f; hv_notify($pdo, 'ix_utr', '✅ Invoice paid · ' . bill_rs($m['paise']) . ' · ' . $doc['number'], $doc['bill_name'] . ($m['ref'] ? ' · Ref ' . $m['ref'] : ''), '/admin/?m=dashboard&go=billing&doc=' . $doc['id']); } } catch (Throwable $e) {}
    return true;
}
