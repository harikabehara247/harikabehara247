<?php
/* r96: real 30-day admin sign-in. Run from public_html: php /tmp/r96.php <commit> */
$C = $argv[1] ?? ''; if (!preg_match('/^[0-9a-f]{40}$/', $C)) exit("commit?\n");
$B = '_hv_export_c3cafe373629b93c/backup-r96/'; @mkdir($B, 0755, true);
$s = @file_get_contents("https://raw.githubusercontent.com/harikabehara247/harikabehara247/$C/r96/remember.php"); if (!$s) exit("FAIL get\n");
file_put_contents('admin-core/remember.php', $s); echo "ok admin-core/remember.php\n";
$f = 'admin.php'; $t = file_get_contents($f); if (!is_file($B . 'admin.php')) copy($f, $B . 'admin.php');
if (strpos($t, 'hv_remember_restore') !== false) exit("already\n");
$pairs = [
  ["require __DIR__ . '/admin-core/layout.php';\n", "require __DIR__ . '/admin-core/layout.php';\nrequire_once __DIR__ . '/admin-core/remember.php'; /* r96: keep me signed in for 30 days, for real */\nif (!isset(\$_GET['logout'])) hv_remember_restore(\$pdo, \$config);\n"],
  ["                setcookie('hv_admin_remember', '1', \$cookieOpts);\n", "                setcookie('hv_admin_remember', '1', \$cookieOpts);\n                hv_remember_issue(\$pdo);\n"],
  ["    setcookie('hv_admin_remember', '', \$expired);\n", "    setcookie('hv_admin_remember', '', \$expired);\n    hv_remember_clear(\$pdo);\n"],
];
foreach ($pairs as [$a, $b]) { if (substr_count($t, $a) !== 1) exit("ANCHOR FAIL: " . substr($a, 0, 60) . "\n"); $t = str_replace($a, $b, $t); }
file_put_contents($f, $t); echo "patched admin.php\n";
