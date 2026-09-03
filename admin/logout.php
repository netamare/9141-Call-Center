<?php
require __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
try {
    require_once __DIR__ . '/../includes/activity.php';
    if (!empty($_SESSION['user_id'])) {
        log_activity(
            $pdo,
            'logout',
            ($_SESSION['user_name'] ?? 'User') . ' logged out',
            'user',
            (int)$_SESSION['user_id'],
            null,
            [
                'id'   => (int)$_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? null,
                'role' => $_SESSION['user_role'] ?? null,
            ]
        );
    }
} catch (Throwable $e) { /* ignore */ }
session_destroy();
header('Location: login.php');
exit;
