<?php
/**
 * SendSMS.php — Thin wrapper (legacy name kept)
 * Prefer: send_sms($pdo, $phone, $message, $event_id) from includes/notifications.php
 *
 * Configure in Admin → Settings:
 *   sms_enabled     = 1
 *   sms_provider    = afromessage
 *   sms_api_key     = YOUR_TOKEN
 *   sms_identifier  = YOUR_IDENTIFIER_ID
 *   sms_sender_id   = 9141
 *   sms_callback_url = https://yourdomain.com/sms_callback.php
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/notifications.php';

$phone   = trim($_GET['phone']   ?? $_POST['phone']   ?? ($argv[1] ?? ''));
$message = trim($_GET['message'] ?? $_POST['message'] ?? ($argv[2] ?? ''));
$eventId = isset($_REQUEST['event_id']) ? (int) $_REQUEST['event_id'] : null;

if ($phone === '' || $message === '') {
    if (php_sapi_name() === 'cli') {
        echo "Usage: php SendSMS.php <phone> <message>\n";
        exit(1);
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'phone and message required']);
    exit;
}

$result = sms_send($pdo, $phone, $message, $eventId);
if ($result === false) {
    $ok = send_sms($pdo, $phone, $message, $eventId);
    $result = ['ok' => (bool) $ok];
}

if (php_sapi_name() === 'cli') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(!empty($result['ok']) ? 0 : 1);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
