<?php
/**
 * Real-time stream recording for Camera Control Room.
 *
 * Uses ffmpeg to capture a live stream (HLS / RTSP / HTTP) for a chosen
 * duration and saves the file under uploads/ with location metadata.
 * Creates a new event (category = emergency by default) so the recording
 * appears in the Camera Room recorded tab and can be AI-analysed.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/cameras.php';

/**
 * Ensure a recordings tracking table exists.
 */
function stream_record_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stream_recordings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        camera_id INT NULL,
        event_id INT NULL,
        file_path VARCHAR(512) NOT NULL,
        location VARCHAR(255) NULL,
        duration_sec INT NOT NULL DEFAULT 60,
        status ENUM('recording','done','failed') NOT NULL DEFAULT 'recording',
        pid INT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL,
        error_message TEXT NULL,
        INDEX (camera_id),
        INDEX (event_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Start a background ffmpeg recording of a camera stream.
 *
 * @param int    $cameraId
 * @param int    $durationSec  30–3600 (max 1 hour per job for safety)
 * @param string $locationOverride  optional location text
 * @return array{ok:bool, recording_id?:int, event_id?:int, error?:string}
 */
function stream_record_start(PDO $pdo, int $cameraId, int $durationSec = 60, string $locationOverride = ''): array {
    stream_record_ensure_table($pdo);

    $cam = cameras_get($pdo, $cameraId);
    if (!$cam || $cam['status'] !== 'active') {
        return ['ok' => false, 'error' => 'Camera not found or inactive'];
    }

    $durationSec = max(30, min(3600, $durationSec));
    $location = $locationOverride !== '' ? $locationOverride : ($cam['location'] ?? $cam['name']);

    // Create a lightweight event so the recording is visible in the system
    $tracking = generate_tracking_code();
    // Prefer "emergency" / traffic category if present
    $catId = null;
    try {
        $catId = $pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1")->fetchColumn();
        $emerg = $pdo->query("SELECT id FROM categories WHERE name LIKE '%ccident%' OR name LIKE '%mergency%' OR name LIKE '%balaa%' LIMIT 1")->fetchColumn();
        if ($emerg) $catId = $emerg;
    } catch (Throwable $e) {}

    $stmt = $pdo->prepare(
        "INSERT INTO events (tracking_code, category_id, description, location, priority, status, source)
         VALUES (?, ?, ?, ?, 'medium', 'new', 'camera_record')"
    );
    // source column may not exist in older schemas – fall back
    try {
        $stmt->execute([
            $tracking,
            $catId,
            'Live camera recording: ' . $cam['name'] . ' (' . $durationSec . 's)',
            $location,
        ]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare(
            "INSERT INTO events (tracking_code, category_id, description, location, priority, status)
             VALUES (?, ?, ?, ?, 'medium', 'new')"
        );
        $stmt->execute([
            $tracking,
            $catId,
            'Live camera recording: ' . $cam['name'] . ' (' . $durationSec . 's)',
            $location,
        ]);
    }
    $eventId = (int)$pdo->lastInsertId();

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $safeName = 'rec_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.mp4';
    $destAbs = UPLOAD_DIR . '/' . $safeName;
    $destRel = UPLOAD_URL . '/' . $safeName;

    // Insert recording row first
    $ins = $pdo->prepare(
        "INSERT INTO stream_recordings (camera_id, event_id, file_path, location, duration_sec, status)
         VALUES (?, ?, ?, ?, ?, 'recording')"
    );
    $ins->execute([$cameraId, $eventId, $destRel, $location, $durationSec]);
    $recId = (int)$pdo->lastInsertId();

    $streamUrl = $cam['stream_url'];
    $type = $cam['stream_type'];

    // Build ffmpeg command
    // -t duration; copy codecs when possible for speed
    $inputFlags = [];
    if ($type === 'rtsp') {
        $inputFlags = ['-rtsp_transport', 'tcp', '-i', $streamUrl];
    } else {
        $inputFlags = ['-i', $streamUrl];
    }

    $cmd = array_merge(
        ['ffmpeg', '-y'],
        $inputFlags,
        [
            '-t', (string)$durationSec,
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            $destAbs,
        ]
    );

    // Log file for debugging
    $logFile = UPLOAD_DIR . '/rec_' . $recId . '.log';
    $cmdStr = implode(' ', array_map('escapeshellarg', $cmd)) . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';

    $pid = (int)trim(shell_exec($cmdStr) ?? '0');
    if ($pid > 0) {
        $pdo->prepare("UPDATE stream_recordings SET pid = ? WHERE id = ?")->execute([$pid, $recId]);
    }

    // Schedule a finish checker via a small background PHP wait (best-effort)
    // Caller can also poll status.
    return [
        'ok' => true,
        'recording_id' => $recId,
        'event_id' => $eventId,
        'tracking_code' => $tracking,
        'file_path' => $destRel,
        'duration_sec' => $durationSec,
        'pid' => $pid,
        'message' => "Recording started for {$durationSec}s → {$cam['name']}",
    ];
}

/**
 * Mark finished recordings and register attachment when file exists.
 */
function stream_record_finalize(PDO $pdo, int $recordingId): array {
    stream_record_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT * FROM stream_recordings WHERE id = ?");
    $stmt->execute([$recordingId]);
    $rec = $stmt->fetch();
    if (!$rec) return ['ok' => false, 'error' => 'Recording not found'];

    if ($rec['status'] === 'done') {
        return ['ok' => true, 'status' => 'done', 'file_path' => $rec['file_path']];
    }

    $abs = realpath(__DIR__ . '/../' . $rec['file_path'])
        ?: (__DIR__ . '/../' . $rec['file_path']);

    if (is_file($abs) && filesize($abs) > 1000) {
        $size = filesize($abs);
        if ($rec['event_id']) {
            register_attachment_from_path(
                $pdo,
                (int)$rec['event_id'],
                $rec['file_path'],
                basename($rec['file_path']),
                'video',
                $size
            );
        }
        $pdo->prepare("UPDATE stream_recordings SET status='done', finished_at=NOW() WHERE id=?")
            ->execute([$recordingId]);
        return ['ok' => true, 'status' => 'done', 'file_path' => $rec['file_path'], 'size' => $size];
    }

    // Still recording or failed
    $pid = (int)($rec['pid'] ?? 0);
    $stillRunning = $pid > 0 && file_exists("/proc/{$pid}");
    if (!$stillRunning && (!is_file($abs) || filesize($abs) < 1000)) {
        $pdo->prepare("UPDATE stream_recordings SET status='failed', finished_at=NOW(), error_message=? WHERE id=?")
            ->execute(['ffmpeg finished without valid output', $recordingId]);
        return ['ok' => false, 'status' => 'failed', 'error' => 'Recording failed or empty'];
    }

    return ['ok' => true, 'status' => 'recording'];
}
