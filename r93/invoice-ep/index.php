<?php
/* GET /invoice/<token>/ : the customer's invoice or quote page. POST do=accept accepts a quote. r93 */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$root = dirname(__DIR__);
try {
    $c = require $root . '/config.php';
    $pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    require $root . '/bill-core/render.php';
    bill_install($pdo);
    $d = bill_doc_by_token($pdo, (string)($_GET['t'] ?? ''));
    if (!$d || $d['status'] === 'draft') { http_response_code(404); header('Content-Type: text/html; charset=utf-8'); echo '<!DOCTYPE html><meta name="viewport" content="width=device-width"><title>Not found</title><p style="font:16px system-ui;padding:40px;text-align:center">This invoice link is not valid. Please ask H View for a new link.</p>'; exit; }
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'accept' && $d['kind'] === 'quote' && $d['status'] === 'sent') {
        $pdo->prepare("UPDATE hv_bill_docs SET status='accepted', updated_at=? WHERE id=? AND status='sent'")->execute([bill_now(), $d['id']]);
        bill_log($pdo, $d['id'], 'quote accepted by customer', '', 'customer');
        try { $f = $root . '/admin-core/notify.php'; if (is_file($f)) { require_once $f; hv_notify($pdo, 'ix_utr', '🤝 Quote accepted · ' . $d['number'] . ' · ' . bill_rs($d['total']), $d['bill_name'], '/admin/?m=dashboard&go=billing&doc=' . $d['id']); } } catch (Throwable $e) {}
        header('Location: /invoice/' . $d['token'] . '/?ok=1', true, 303); exit;
    }
    if (!empty($_GET['ok'])) $flash = 'Thank you! We have your acceptance and will be in touch shortly.';
    if (empty($d['viewed_at']) && empty($_GET['p'])) $pdo->prepare('UPDATE hv_bill_docs SET viewed_at=? WHERE id=? AND viewed_at IS NULL')->execute([bill_now(), $d['id']]);
    header('Content-Type: text/html; charset=utf-8');
    echo bill_render($pdo, $d, ['self' => '/invoice/' . $d['token'] . '/', 'flash' => $flash, 'print' => !empty($_GET['print'])]);
} catch (Throwable $e) { error_log('invoice-ep: ' . $e->getMessage()); http_response_code(500); echo 'Something went wrong. Please try again.'; }
