<?php
/* POST /api/pay-relay/ : H View Relay app (owner's phone) -> bank credit alerts. r90 */
header('Content-Type: application/json'); header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{"ok":false,"error":"POST only"}'; exit; }
$root = dirname(__DIR__, 2);
try {
    $c = require $root . '/config.php';
    $pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    date_default_timezone_set('Asia/Kolkata');
    require $root . '/ix-core/relay-hooks.php';
    $raw = file_get_contents('php://input', false, null, 0, 20000);
    $h = function_exists('getallheaders') ? getallheaders() : [];
    foreach (['HTTP_X_HV_TIMESTAMP' => 'X-HV-Timestamp', 'HTTP_X_HV_SIGNATURE' => 'X-HV-Signature', 'HTTP_X_HV_DEVICE' => 'X-HV-Device'] as $k => $n) if (isset($_SERVER[$k]) && !isset($h[$n])) $h[$n] = $_SERVER[$k];
    [$code, $out] = relay_handle($pdo, $h, $raw);
} catch (Throwable $e) { error_log('pay-relay: ' . $e->getMessage()); $code = 500; $out = ['ok' => false, 'error' => 'server']; }
http_response_code($code); echo json_encode($out);
