<?php
/* r94 dry test of the WhatsApp bot: runs a copy of wa-core with sending and alerts switched off, inside a DB transaction that is rolled back. */
define('WA_FAKE', 1); $GLOBALS['WA_OUT'] = [];
$f = __DIR__ . '/wa-core/lib.php'; $s = file_get_contents($f);
$a1 = 'function wa_api(PDO $pdo, $method, $path, $body = null, $timeout = 15){';
$a2 = 'function wa_notify(PDO $pdo, array $c, $title, $body){';
if (strpos($s, 'WA_FAKE') === false) {
    if (substr_count($s, $a1) !== 1 || substr_count($s, $a2) !== 1) exit("anchors?\n");
    $s = str_replace($a1, $a1 . ' if (defined(\'WA_FAKE\')) { $GLOBALS[\'WA_OUT\'][] = $body; return [\'ok\' => true, \'code\' => 200, \'error\' => \'\', \'data\' => [\'messages\' => [[\'id\' => \'wamid.T\' . uniqid()]]]]; }', $s);
    $s = str_replace($a2, $a2 . ' if (defined(\'WA_FAKE\')) { $GLOBALS[\'WA_OUT\'][] = [\'NOTIFY\' => $title]; return; }', $s);
    file_put_contents($f, $s);
}
$c = require '/home/u933495658/domains/hview.in/public_html/config.php';
$pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require $f;
foreach (glob(__DIR__ . '/wa-core/*.php') as $g) { try { require_once $g; } catch (Throwable $e) {} }
$pdo->beginTransaction();
$steps = json_decode($argv[1] ?? '[]', true) ?: [['text', 'hi'], ['text', 'I want a birthday wish for my cousin'], ['btn', 'ixw:free'], ['btn', 'ixw:custom']];
foreach ($steps as $i => [$k, $v]) {
    $GLOBALS['WA_OUT'] = [];
    $m = ['from' => '910000099901', 'id' => 'wamid.IN' . uniqid() . $i, 'timestamp' => (string)time(), 'type' => $k === 'text' ? 'text' : 'interactive'];
    if ($k === 'text') $m['text'] = ['body' => $v]; else $m['interactive'] = ['type' => 'button_reply', 'button_reply' => ['id' => $v, 'title' => $v]];
    try { wa_incoming($pdo, $m, ['name' => 'Test User']); } catch (Throwable $e) { echo 'ERR ', $e->getMessage(), ' @', basename($e->getFile()), ':', $e->getLine(), "\n"; }
    echo "=== $k: $v\n";
    foreach ($GLOBALS['WA_OUT'] as $o) {
        if (isset($o['NOTIFY'])) { echo '  [alert] ', $o['NOTIFY'], "\n"; continue; }
        $t = $o['text']['body'] ?? ($o['interactive']['body']['text'] ?? json_encode($o, JSON_UNESCAPED_UNICODE));
        echo '  ', mb_substr(str_replace("\n", ' / ', $t), 0, (int)($argv[2] ?? 300)), "\n";
        foreach (($o['interactive']['action']['buttons'] ?? []) as $b) echo '    [', $b['reply']['id'], ' | ', $b['reply']['title'], "]\n";
        foreach (($o['interactive']['action']['sections'] ?? []) as $sec) foreach ($sec['rows'] as $r) echo '    {', $r['id'], ' | ', $r['title'], "}\n";
    }
}
$pdo->rollBack(); echo "rolled back\n";
