<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user && isset($user['id'])) {
        log_audit($pdo, (int)$user['id'], 'LOGOUT', 'users', (int)$user['id']);
    }
}

// Unset all session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start a clean session to pass flash message
session_start();
set_flash('info', 'คุณได้ออกจากระบบเรียบร้อยแล้ว');

header('Location: login.php');
exit;
