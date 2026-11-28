<?php
/* r93 deploy: Billing. Run from public_html: php /tmp/r93.php <commit> */
$C = $argv[1] ?? ''; if (!preg_match('/^[0-9a-f]{40}$/', $C)) exit("commit?\n");
$B = '_hv_export_c3cafe373629b93c/backup-r93/'; @mkdir($B, 0755, true);
$get = function ($p) use ($C) { $u = "https://raw.githubusercontent.com/harikabehara247/harikabehara247/$C/r93/$p"; $s = @file_get_contents($u); if ($s === false || $s === '') { echo "FAIL $p\n"; exit(1); } return $s; };
$files = ['bill-core/lib.php' => 'bill-core/lib.php', 'bill-core/render.php' => 'bill-core/render.php', 'bill-core/orders.php' => 'bill-core/orders.php',
          'admin/billing.php' => 'admin-core/modules/billing.php', 'admin/billing-list.inc.php' => 'admin-core/modules/billing-list.inc.php',
          'invoice-ep/index.php' => 'invoice-ep/index.php', 'assets/qrcode.js' => 'assets/bill/qrcode.js'];
foreach ($files as $src => $dst) { @mkdir(dirname($dst), 0755, true); file_put_contents($dst, $get($src)); echo "ok $dst\n"; }
file_put_contents('bill-core/.htaccess', "Require all denied\nDeny from all\n");
$patch = function ($f, array $pairs) use ($B) {
    $s = file_get_contents($f); if (!is_file($B . basename($f))) copy($f, $B . basename($f));
    foreach ($pairs as [$a, $b]) { if (strpos($s, $b) !== false) { echo "skip (done) $f\n"; continue; } if (substr_count($s, $a) !== 1) { echo "ANCHOR FAIL $f: " . substr($a, 0, 60) . "\n"; exit(1); } $s = str_replace($a, $b, $s); }
    file_put_contents($f, $s); echo "patched $f\n";
};
$patch('admin-core/bootstrap.php', [
    ["        'pay-relay'      => ['admin'],", "        'pay-relay'      => ['admin'],\n        'billing'        => ['admin'],"],
    ["function adm_bridged_modules(){ return ['pay-relay',", "function adm_bridged_modules(){ return ['billing', 'pay-relay',"],
]);
$patch('admin-core/layout.php', [["            ['pay-relay', 'Payment relay', 'shield', null],", "            ['billing', 'Billing & invoices', 'chart', null],\n            ['pay-relay', 'Payment relay', 'shield', null],"]]);
$patch('.htaccess', [["RewriteRule ^api/pay-relay/?$ api/pay-relay-ep/index.php [L]", "RewriteRule ^api/pay-relay/?$ api/pay-relay-ep/index.php [L]\nRewriteRule ^invoice/([A-Za-z0-9]{10,20})/?$ invoice-ep/index.php?t=$1 [L,QSA]"]]);
$patch('ix-core/relay.php', [
    ["    return [200, ['ok' => true, 'matched' => relay_try_match(\$pdo, \$row)]];",
     "    \$mc = relay_try_match(\$pdo, \$row);\n    /* r93: a credit with no ₹49 / plan order may pay an open invoice */\n    if (!\$mc && is_file(dirname(__DIR__) . '/bill-core/lib.php')) { try { require_once dirname(__DIR__) . '/bill-core/lib.php'; \$mc = bill_relay_try(\$pdo, \$row); } catch (Throwable \$e) { error_log('bill relay: ' . \$e->getMessage()); } }\n    return [200, ['ok' => true, 'matched' => \$mc]];"],
    ["(?:paid you|sent you)/im'", "(?:has\\s+)?(?:paid you|sent you|sent\\s+(?:₹|Rs\\.?|INR))/imu'"],
]);
foreach (array_merge(array_values($files), ['admin-core/bootstrap.php', 'admin-core/layout.php', 'ix-core/relay.php']) as $f) if (substr($f, -4) === '.php') { $o = shell_exec('php -l ' . escapeshellarg($f) . ' 2>&1'); if (strpos($o, 'No syntax') === false) echo "LINT $f: $o"; }
echo "done\n";
