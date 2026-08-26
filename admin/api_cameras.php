<?php
/**
 * Real-time Camera Streaming API
 *
 * GET  ?action=list          → JSON list of active cameras
 * GET  ?action=get&id=N      → single camera
 * POST action=save           → create/update (admin only)
 * POST action=delete&id=N    → delete (admin only)
 * GET  ?action=health        → simple status
 *
 * CORS is open for same-origin use by the Camera Room player.
 */
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/cameras.php';
require __DIR__ . '/../includes/stream_record.php';
require __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$role = current_role();
$canManage = in_array($role, ['administrator'], true);
// Room Camera: ALL logged-in roles may view, record, and system-upload
$canView   = true;
$canRecord = true;

if (!$canView) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'list') {
        $cams = cameras_list($pdo, true);
        echo json_encode([
            'ok' => true,
            'count' => count($cams),
            'cameras' => array_map('cameras_public_payload', $cams),
            'server_time' => date('c'),
        ]);
        exit;
    }

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        $cam = cameras_get($pdo, $id);
        if (!$cam || $cam['status'] !== 'active') {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Camera not found']);
            exit;
        }
        echo json_encode(['ok' => true, 'camera' => cameras_public_payload($cam)]);
        exit;
    }

    if ($action === 'health') {
        cameras_ensure_table($pdo);
        $count = (int)$pdo->query("SELECT COUNT(*) FROM cameras WHERE status='active'")->fetchColumn();
        echo json_encode([
            'ok' => true,
            'active_cameras' => $count,
            'ffmpeg' => is_executable('/usr/bin/ffmpeg'),
            'time' => date('c'),
        ]);
        exit;
    }

    // ---- Real-time recording (control-room roles) ----
    if ($action === 'record_start') {
        if (!$canRecord) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Forbidden']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'CSRF failed']);
                exit;
            }
        }
        $cameraId = (int)($_POST['camera_id'] ?? $_GET['camera_id'] ?? 0);
        $duration = (int)($_POST['duration'] ?? $_GET['duration'] ?? 60);
        $location = trim($_POST['location'] ?? $_GET['location'] ?? '');
        $result = stream_record_start($pdo, $cameraId, $duration, $location);
        echo json_encode($result);
        exit;
    }

    if ($action === 'record_status') {
        $recId = (int)($_GET['recording_id'] ?? 0);
        $result = stream_record_finalize($pdo, $recId);
        echo json_encode($result);
        exit;
    }

    // Mutations require admin + CSRF
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Admin only']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
    }

    if ($action === 'save') {
        $id = cameras_save($pdo, [
            'id'          => (int)($_POST['id'] ?? 0),
            'name'        => $_POST['name'] ?? '',
            'location'    => $_POST['location'] ?? '',
            'stream_url'  => $_POST['stream_url'] ?? '',
            'stream_type' => $_POST['stream_type'] ?? 'hls',
            'status'      => $_POST['status'] ?? 'active',
            'notes'       => $_POST['notes'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        cameras_delete($pdo, $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
