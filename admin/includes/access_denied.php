<?php
/**
 * Access denied → always send user to staff login (no "permission" card).
 * Session is cleared so a bad/stale role cannot loop forever.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
}
session_destroy();

$login = 'login.php';
// If somehow included outside /admin/
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($script, '/admin/') === false) {
    $login = 'admin/login.php';
}
header('Location: ' . $login . '?reauth=1');
exit;
