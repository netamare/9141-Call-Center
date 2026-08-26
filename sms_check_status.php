<?php
/**
 * sms_check_status.php — Poll delivery status (admin / cron / debug)
 *
 * GET /sms_check_status.php?id=MESSAGE_ID
 * Requires logged-in admin (optional) — for public debug remove auth.
 *
 * Rate limit on Afro side: ~1 request / 2 seconds.
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sms.php';

// Optional: restrict to logged-in staff
if (is_file(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
    if (function_exists('require_login')) {
        // Soft: only if session already expected; comment out to allow cron
        // require_login();
    }
}

header('Content-Type: application/json; charset=utf-8');

$id = trim($_GET['id'] ?? $_GET['message_id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id required']);
    exit;
}

echo json_encode(sms_check_status($pdo, $id, true), JSON_UNESCAPED_UNICODE);
