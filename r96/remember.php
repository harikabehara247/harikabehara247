<?php
/* =====================================================================
 * H View admin: "Keep me signed in for 30 days" that really lasts (r96, 28 Nov 2026).
 * PHP sessions on the shared host are cleared after 24 minutes idle (session.gc_maxlifetime 1440, and every visit
 * to the public site can run the cleanup), so the old 30-day cookie pointed at a session that no longer existed.
 * Now a remember token is stored on the server: cookie hv_admin_rt = selector:validator (random), and only a
 * SHA-256 of the validator is saved in hv_admin_tokens. When the session is gone, the token signs you back in.
 * The user is checked again each time (deleted or disabled users are not signed back in). Sign out deletes it.
 * ===================================================================== */
if (defined('HV_REMEMBER')) return; define('HV_REMEMBER', 1);
const HV_RT_COOKIE = 'hv_admin_rt';
const HV_RT_DAYS = 30;

function hv_rt_install(PDO $pdo) {
    static $done = false; if ($done) return; $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS hv_admin_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, selector CHAR(16) NOT NULL, vhash CHAR(64) NOT NULL,
        user_id INT NOT NULL DEFAULT 0, user_login VARCHAR(190) NOT NULL, ua VARCHAR(200) NULL,
        expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, last_used DATETIME NULL,
        UNIQUE KEY uq_sel (selector), KEY (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function hv_rt_opts($expires) {
    $p = session_get_cookie_params();
    $o = ['expires' => $expires, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax'];
    if (!empty($p['domain'])) $o['domain'] = $p['domain'];
    return $o;
}
/* after a successful sign-in with "keep me signed in" ticked */
function hv_remember_issue(PDO $pdo) {
    try {
        hv_rt_install($pdo);
        $u = $_SESSION['hv_user'] ?? null; if (!is_array($u)) return;
        $sel = bin2hex(random_bytes(8)); $val = bin2hex(random_bytes(32)); $exp = time() + HV_RT_DAYS * 86400;
        $pdo->prepare('INSERT INTO hv_admin_tokens (selector, vhash, user_id, user_login, ua, expires_at, created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$sel, hash('sha256', $val), (int)($u['id'] ?? 0), (string)($u['email'] ?? ''), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), gmdate('Y-m-d H:i:s', $exp), gmdate('Y-m-d H:i:s')]);
        setcookie(HV_RT_COOKIE, $sel . ':' . $val, hv_rt_opts($exp));
        $pdo->exec("DELETE FROM hv_admin_tokens WHERE expires_at < UTC_TIMESTAMP()");
    } catch (Throwable $e) { error_log('remember issue: ' . $e->getMessage()); }
}
/* no session user, but a valid remember cookie: sign back in */
function hv_remember_restore(PDO $pdo, array $config) {
    if (!empty($_SESSION['hv_user'])) return false;
    $c = (string)($_COOKIE[HV_RT_COOKIE] ?? ''); if (!preg_match('/^([0-9a-f]{16}):([0-9a-f]{64})$/', $c, $m)) return false;
    try {
        hv_rt_install($pdo);
        $q = $pdo->prepare('SELECT * FROM hv_admin_tokens WHERE selector=? LIMIT 1'); $q->execute([$m[1]]); $t = $q->fetch(PDO::FETCH_ASSOC);
        if (!$t || !hash_equals($t['vhash'], hash('sha256', $m[2])) || strtotime($t['expires_at'] . ' UTC') < time()) { hv_remember_clear($pdo); return false; }
        if ((int)$t['user_id'] === 0) {
            if (!isset($config['admin_user']) || !hash_equals((string)$config['admin_user'], (string)$t['user_login'])) { hv_remember_clear($pdo); return false; }
            $user = ['id' => 0, 'name' => function_exists('setting_value') ? setting_value($pdo, 'admin_display_name', 'Administrator') : 'Administrator', 'email' => $t['user_login'], 'role' => 'admin'];
        } else {
            $q = $pdo->prepare("SELECT * FROM hv_users WHERE id=? AND status='active' LIMIT 1"); $q->execute([(int)$t['user_id']]); $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) { hv_remember_clear($pdo); return false; }
            $user = ['id' => (int)$row['id'], 'name' => $row['name'], 'email' => $row['email'], 'role' => $row['role']];
        }
        session_regenerate_id(true);
        $_SESSION['admin'] = true; $_SESSION['hv_user'] = $user; $_SESSION['hv_remember'] = strtotime($t['expires_at'] . ' UTC');
        $pdo->prepare('UPDATE hv_admin_tokens SET last_used=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'), $t['id']]);
        return true;
    } catch (Throwable $e) { error_log('remember restore: ' . $e->getMessage()); return false; }
}
/* sign out on this device */
function hv_remember_clear(PDO $pdo) {
    $c = (string)($_COOKIE[HV_RT_COOKIE] ?? '');
    if (preg_match('/^([0-9a-f]{16}):/', $c, $m)) { try { $pdo->prepare('DELETE FROM hv_admin_tokens WHERE selector=?')->execute([$m[1]]); } catch (Throwable $e) {} }
    if ($c !== '') setcookie(HV_RT_COOKIE, '', hv_rt_opts(time() - 3600));
}
