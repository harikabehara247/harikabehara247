<?php
/* r94: WhatsApp: free wishes = website + AI helper; custom = paid order flow; team only for problems. Run from public_html. */
$B = '_hv_export_c3cafe373629b93c/backup-r94/'; @mkdir($B, 0755, true);
$patch = function ($f, array $pairs) use ($B) {
    $s = file_get_contents($f); if (!is_file($B . basename($f))) copy($f, $B . basename($f));
    foreach ($pairs as [$a, $b]) { if (strpos($s, $b) !== false) { echo "skip $f\n"; continue; } $n = substr_count($s, $a); if ($n !== 1) { echo "ANCHOR FAIL ($n) $f: " . mb_substr($a, 0, 70) . "\n"; exit(1); } $s = str_replace($a, $b, $s); }
    file_put_contents($f, $s); $o = shell_exec('php -l ' . escapeshellarg($f) . ' 2>&1'); echo (strpos($o, 'No syntax') !== false ? "ok " : "LINT FAIL ") . $f . "\n";
};
$note = <<<'PHPX'
/* r94: one line every wish reply ends with: free = website, custom = paid */
if (!function_exists('wa_ixw_note')) { function wa_ixw_note($L){
    $t = [
        'en' => "🎨 *Want it made for you?* If you need your own idea (your scene order, special wishes, extra pages), our team designs it for you from ₹499, paid in advance. Tap *Custom design* and I'll take your details here.",
        'hl' => "🎨 *Aapke liye banwana hai?* Apna khud ka idea (scenes ka order, special wishes) chahiye to hamari team ₹499 se custom design banati hai, payment advance mein. *Custom design* dabayein, main yahin aapki details le lunga.",
        'hi' => "🎨 *आपके लिए बनवाना है?* अपना खुद का आइडिया (सीन का क्रम, खास शुभकामनाएँ) चाहिए तो हमारी टीम ₹499 से कस्टम डिज़ाइन बनाती है, भुगतान पहले। *Custom design* दबाएँ, मैं यहीं आपकी जानकारी ले लूँगा।",
        'ur' => "🎨 *اپنے لیے بنوانا ہے؟* اگر آپ کو اپنا خاص آئیڈیا چاہیے (سین کی ترتیب، خاص دعائیں) تو ہماری ٹیم ₹499 سے کسٹم ڈیزائن بناتی ہے، ادائیگی پہلے۔ *Custom design* دبائیں، میں یہیں آپ کی تفصیل لے لوں گا۔",
    ];
    return $t[$L] ?? $t['en'];
} }
function wa_help_wish(
PHPX;
$btnFree = "['ixw:free', 'Make it free ✨', 'Free mein banayein ✨', 'मुफ़्त बनाएं ✨', 'مفت بنائیں ✨']";
$btnCustom = "['ixw:custom', 'Custom design ₹499', 'Custom design ₹499', 'कस्टम डिज़ाइन ₹499', 'کسٹم ڈیزائن ₹499']";
$btnIssue = "['ixw:issue', 'Problem with my link', 'Link mein dikkat', 'लिंक में दिक्कत', 'لنک میں مسئلہ']";
$btnMenu = "['menu', 'Main menu', 'Main menu', 'मुख्य मेनू', 'مین مینو']";
$oldRows = "[['m:human', 'Help with my wish', 'Wish mein madad', 'विश में मदद', 'وش میں مدد'], ['menu', 'Main menu', 'Main menu', 'मुख्य मेनू', 'مین مینو']]";

$patch('wa-core/help.php', [
    ["function wa_help_wish(", $note],
    ["    return [\$m[\$L], wa_help_btns(\$L, $oldRows)];", "    return [\$m[\$L] . \"\\n\\n\" . wa_ixw_note(\$L), wa_help_btns(\$L, [$btnCustom, $btnIssue, $btnMenu])];"],
]);
$patch('wa-core/ix-make.php', [
    ["    \$rows = $oldRows;", "    if (function_exists('wa_ixw_note')) foreach (\$m as \$k => \$v) \$m[\$k] = \$v . \"\\n\\n\" . wa_ixw_note(\$k); /* r94 */\n    \$rows = [$btnFree, $btnCustom, $btnMenu];"],
    ["wa_buttons(\$pdo, \$c, \$m['en'], [['m:human', 'Help with my wish'], ['menu', 'Main menu']]); return true;", "wa_buttons(\$pdo, \$c, \$m['en'], [['ixw:free', 'Make it free ✨'], ['ixw:custom', 'Custom design ₹499'], ['menu', 'Main menu']]); return true;"],
]);
$patch('wa-core/lib.php', [
    ["        case 'm:human': wa_handoff(\$pdo, \$c, 'Asked to talk to the team from the WhatsApp menu.'); return true;",
     "        case 'm:human': wa_handoff(\$pdo, \$c, 'Asked to talk to the team from the WhatsApp menu.'); return true;\n"
   . "        /* r94: wishes: free on the website (AI helper), custom = paid order flow, team only for a problem with a made greeting */\n"
   . "        case 'ixw:free': if (!function_exists('wa_help_wish') && is_file(WA_ROOT . '/wa-core/help.php')) require_once WA_ROOT . '/wa-core/help.php';\n"
   . "            if (function_exists('wa_help_wish')) { \$L = wa_help_lang('', \$c); [\$t, \$b] = wa_help_wish(\$pdo, \$c, \$L); wa_help_send(\$pdo, \$c, \$L, \$t, \$b); return true; }\n"
   . "            wa_menu(\$pdo, \$c); return true;\n"
   . "        case 'ixw:custom': if (!function_exists('wa_flow_start') && is_file(WA_ROOT . '/wa-core/flows.php')) require_once WA_ROOT . '/wa-core/flows.php';\n"
   . "            if (function_exists('wa_flow_start')) { wa_flow_start(\$pdo, \$c, 'ix'); return true; }\n"
   . "            wa_handoff(\$pdo, \$c, 'Wants a custom greeting design (paid).'); return true;\n"
   . "        case 'ixw:issue': wa_handoff(\$pdo, \$c, 'Problem with a greeting they already made.'); return true;"],
]);
echo "done\n";
