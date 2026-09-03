<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/notifications.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($__POST_ACTION = $_POST['action'] ?? '') === 'mark_read') {
    mark_all_read($pdo, $_SESSION['user_id']);
    echo json_encode(['ok' => true]);
    exit;
}

// Escalating alert: check on every poll (~every 15s) so an unhandled event
// fires an urgent alert for operators/admins within seconds of crossing the
// 5-minute threshold, without needing a cron job.
if (in_array(current_role(), ['administrator', 'operator'], true)) {
    check_stale_new_events($pdo);
}

$items = get_recent_notifications($pdo, $_SESSION['user_id']);
// Sound/alarm only for operators (call center). Administrators see notifications
// but do not get the audible siren, per requirement.
$role = current_role();
$playSound = ($role === 'operator') && has_unread_urgent($pdo, $_SESSION['user_id']);
echo json_encode([
    'count' => get_unread_count($pdo, $_SESSION['user_id']),
    'urgent' => $playSound,
    'items' => array_map(function ($n) {
        return [
            'id' => $n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'code' => $n['tracking_code'],
            'event_id' => $n['event_id'],
            'urgent' => (bool) $n['is_urgent'],
            'read' => (bool) $n['is_read'],
            'when' => $n['created_at'],
        ];
    }, $items),
]);
