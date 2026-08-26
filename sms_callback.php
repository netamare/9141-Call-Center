<?php
/**
 * sms_callback.php — AfroMessage delivery status callback (public GET)
 *
 * Set in Admin → Settings → sms_callback_url:
 *   https://yourdomain.com/sms_callback.php
 *
 * AfroMessage calls:
 *   /sms_callback.php?message_id=UUID&status=DELIVRD
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sms.php';

header('Content-Type: text/plain; charset=utf-8');

$messageId = trim($_GET['message_id'] ?? $_GET['id'] ?? '');
$status    = trim($_GET['status'] ?? '');

if ($messageId === '') {
    http_response_code(400);
    echo 'missing message_id';
    exit;
}

$ok = sms_update_status($pdo, $messageId, $status !== '' ? $status : 'UNKNOWN');

// Optional: also note on related event if we can resolve event_id
if ($ok && $status !== '') {
    try {
        $stmt = $pdo->prepare('SELECT event_id, phone FROM sms_logs WHERE message_id = ? LIMIT 1');
        $stmt->execute([$messageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['event_id'])) {
            $note = 'SMS status ' . strtoupper($status) . ' for ' . ($row['phone'] ?? '');
            $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note) VALUES (?, 'sms_status', ?)");
            $log->execute([(int)$row['event_id'], $note]);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

http_response_code(200);
echo 'OK';
