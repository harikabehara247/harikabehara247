<?php
/* r95: H View Admin as its own installable app + phone layout. Run from public_html: php /tmp/r95.php <commit> */
$C = $argv[1] ?? ''; if (!preg_match('/^[0-9a-f]{40}$/', $C)) exit("commit?\n");
$B = '_hv_export_c3cafe373629b93c/backup-r95/'; @mkdir($B, 0755, true);
$get = function ($p) use ($C) { $s = @file_get_contents("https://raw.githubusercontent.com/harikabehara247/harikabehara247/$C/r95/$p"); if ($s === false || $s === '') { echo "FAIL $p\n"; exit(1); } return $s; };
@mkdir('assets/admin-app', 0755, true);
foreach (['admin-app/icon-192.png' => 'assets/admin-app/icon-192.png', 'admin-app/icon-512.png' => 'assets/admin-app/icon-512.png', 'admin-app/maskable-512.png' => 'assets/admin-app/maskable-512.png',
          'admin-app/sw.js' => 'assets/admin-app/sw.js', 'admin-app.webmanifest' => 'admin-app.webmanifest',
          'admin-mobile.css' => 'assets/admin/admin-mobile.css', 'admin-mobile.js' => 'assets/admin/admin-mobile.js'] as $s => $d) { file_put_contents($d, $get($s)); echo "ok $d\n"; }
$patch = function ($f, $a, $b) use ($B) {
    $s = file_get_contents($f); if (strpos($s, $b) !== false) { echo "skip $f\n"; return; }
    if (substr_count($s, $a) !== 1) { echo "ANCHOR FAIL $f\n"; exit(1); }
    if (!is_file($B . basename($f))) copy($f, $B . basename($f));
    file_put_contents($f, str_replace($a, $b, $s)); echo "patched $f\n";
};
$patch('.htaccess', "RewriteRule ^invoice/([A-Za-z0-9]{10,20})/?$ invoice-ep/index.php?t=$1 [L,QSA]",
    "RewriteRule ^invoice/([A-Za-z0-9]{10,20})/?$ invoice-ep/index.php?t=$1 [L,QSA]\nRewriteRule ^admin/sw\\.js$ assets/admin-app/sw.js [L]");
$patch('admin-core/layout.php', '</head><body class="adm" data-module="',
    '<link rel="manifest" href="/admin-app.webmanifest?v=1"><meta name="theme-color" content="#3730a3"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="HV Admin">'
  . '<link rel="stylesheet" href="/assets/admin/admin-mobile.css?v=<?= (int)@filemtime(dirname(__DIR__) . \'/assets/admin/admin-mobile.css\') ?>">'
  . '<script src="/assets/admin/admin-mobile.js?v=<?= (int)@filemtime(dirname(__DIR__) . \'/assets/admin/admin-mobile.js\') ?>" defer></script>'
  . '</head><body class="adm" data-module="');
echo "done\n";
