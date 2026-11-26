<?php
/* /api/phonepe : start (ix greeting or plan), return from PhonePe, webhook. r91 */
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$root = dirname(__DIR__, 2);
$c = require $root . '/config.php';
$pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require $root . '/ix-core/phonepe.php';
$a = (string)($_GET['a'] ?? '');
function pp_json($code, $j) { http_response_code($code); header('Content-Type: application/json'); echo json_encode($j); exit; }
function pp_safe_back($u, $def) { $u = (string)$u; return (preg_match('#^/[^/\\\\]#', $u) || $u === '/') ? $u : $def; }
try {
    if ($a === 'webhook') { [$code, $out] = pp_webhook($pdo, function_exists('getallheaders') ? getallheaders() : ['Authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? ''], file_get_contents('php://input', false, null, 0, 50000)); pp_json($code, $out); }
    if (!pp_on($pdo)) pp_json(503, ['error' => 'Card and PhonePe payments are not available right now. Please pay by UPI ID.']);
    if ($a === 'start') {               /* ₹49 single greeting: POST JSON {code, t, back} */
        $in = json_decode(file_get_contents('php://input', false, null, 0, 5000), true) ?: [];
        $q = $pdo->prepare("SELECT * FROM hv_ix_orders WHERE code=? AND package='unlock' LIMIT 1"); $q->execute([(string)($in['code'] ?? '')]); $o = $q->fetch(PDO::FETCH_ASSOC);
        if (!$o || !hash_equals($o['token'], (string)($in['t'] ?? ''))) pp_json(404, ['error' => 'Order not found.']);
        if ($o['status'] === 'paid') pp_json(200, ['paid' => true]);
        [$url, $err] = pp_start($pdo, 'ix_unlock', $o['id'], $o['code'], (int)$o['agreed_price'] * 100, pp_safe_back($in['back'] ?? '', '/interactive/'), 'H View greeting ' . $o['code']);
        if (!$url) { error_log('phonepe start: ' . $err); pp_json(502, ['error' => 'PhonePe is not responding. Please pay by UPI ID below, or try again.']); }
        pp_json(200, ['url' => $url]);
    }
    if ($a === 'plan') {                /* membership plan: GET ?code=HVP-… from the plan payment page */
        $q = $pdo->prepare('SELECT * FROM hv_member_upgrades WHERE code=? LIMIT 1'); $q->execute([(string)($_GET['code'] ?? '')]); $o = $q->fetch(PDO::FETCH_ASSOC);
        $back = '/account/upgrade/' . rawurlencode((string)($_GET['code'] ?? '')) . '/';
        if (!$o || !in_array($o['status'], ['awaiting_payment', 'verification_pending'], true)) { header('Location: /account/plan/'); exit; }
        [$url, $err] = pp_start($pdo, 'plan', $o['id'], $o['code'], (int)$o['amount'] * 100, pp_safe_back($_GET['back'] ?? '', $back), 'H View plan ' . $o['code']);
        if (!$url) { error_log('phonepe plan start: ' . $err); header('Location: ' . $back . '?pp=error'); exit; }
        header('Location: ' . $url); exit;
    }
    if ($a === 'return') {              /* PhonePe sends the buyer back here */
        $o = pp_check($pdo, (string)($_GET['o'] ?? ''));
        if (!$o) { header('Location: /interactive/'); exit; }
        $back = $o['back'] ?: '/interactive/'; $sep = strpos($back, '?') === false ? '?' : '&';
        $extra = '';
        if ($o['kind'] === 'ix_unlock') { $t = $pdo->prepare('SELECT token FROM hv_ix_orders WHERE id=?'); $t->execute([(int)$o['ref_id']]); $extra = '&ixt=' . rawurlencode((string)$t->fetchColumn()); }
        header('Location: ' . $back . $sep . 'ixpaid=' . rawurlencode($o['code']) . '&pp=' . strtolower($o['state']) . $extra); exit;
    }
    pp_json(404, ['error' => 'Unknown action']);
} catch (Throwable $e) { error_log('phonepe: ' . $e->getMessage()); pp_json(500, ['error' => 'Server error. Please pay by UPI ID or try again.']); }
