<?php
/**
 * Adama 9141 — AI Detection integration
 *
 * Calls the local Python detector (ai/detect.py) which analyses images
 * or extracts frames from videos and returns structured detections
 * mapped to the four core problem categories.
 *
 * Results are stored in the ai_detections table (see schema note below).
 *
 * To upgrade to a real neural model: replace the body of run_detection()
 * inside ai/detect.py with ONNX Runtime / Torch inference; the PHP
 * contract stays identical.
 */

if (!defined('AI_DETECT_SCRIPT')) {
    define('AI_DETECT_SCRIPT', __DIR__ . '/../ai/detect.py');
}
if (!defined('AI_DETECT_PYTHON')) {
    define('AI_DETECT_PYTHON', 'python3');
}

/**
 * Run AI detection on an image or video file.
 *
 * @param string $filePath Absolute path to image or video
 * @param string $location Optional location text (improves priors)
 * @param string $categoryHint Optional category key (illegal|security|service|emergency)
 * @return array{ok:bool, detections?:array, summary?:string, error?:string, ...}
 */
function ai_run_detection(string $filePath, string $location = '', string $categoryHint = ''): array {
    if (!is_file($filePath)) {
        return ['ok' => false, 'error' => 'File not found'];
    }
    if (!is_file(AI_DETECT_SCRIPT)) {
        return ['ok' => false, 'error' => 'AI script missing'];
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $isVideo = in_array($ext, ['mp4', 'mov', '3gp', 'webm', 'avi', 'mkv'], true);

    $cmd = [
        AI_DETECT_PYTHON,
        AI_DETECT_SCRIPT,
        $isVideo ? '--video' : '--image',
        $filePath,
        '--cleanup',
    ];
    if ($location !== '') {
        $cmd[] = '--location';
        $cmd[] = $location;
    }
    if ($categoryHint !== '') {
        $cmd[] = '--category_hint';
        $cmd[] = $categoryHint;
    }

    // Escape for shell
    $escaped = array_map('escapeshellarg', $cmd);
    $fullCmd = implode(' ', $escaped) . ' 2>/dev/null';

    $output = shell_exec($fullCmd);
    if ($output === null || trim($output) === '') {
        return ['ok' => false, 'error' => 'Detector returned empty output'];
    }

    $data = json_decode(trim($output), true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON from detector'];
    }
    return $data;
}

/**
 * Persist detection results for an attachment / event.
 * Creates the table on first use if it does not exist.
 */
function ai_save_detections(PDO $pdo, int $eventId, int $attachmentId, array $result): bool {
    if (empty($result['ok'])) return false;

    // Ensure table exists (idempotent)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_detections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        attachment_id INT NULL,
        model VARCHAR(64) NOT NULL DEFAULT 'adama-local-v1',
        summary TEXT,
        detections_json JSON,
        frames_analyzed INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (event_id),
        INDEX (attachment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare(
        "INSERT INTO ai_detections (event_id, attachment_id, model, summary, detections_json, frames_analyzed)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    return $stmt->execute([
        $eventId,
        $attachmentId ?: null,
        $result['model'] ?? 'adama-local-v1',
        $result['summary'] ?? '',
        json_encode($result['detections'] ?? [], JSON_UNESCAPED_UNICODE),
        (int)($result['frames_analyzed'] ?? 0),
    ]);
}

/**
 * Fetch latest AI results for an event.
 */
function ai_get_for_event(PDO $pdo, int $eventId): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM ai_detections WHERE event_id = ? ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Human-readable label for a detection category.
 */
function ai_category_label(string $cat): string {
    $map = [
        'illegal'   => t('cat_illegal'),
        'security'  => t('cat_security'),
        'service'   => t('cat_service'),
        'emergency' => t('cat_emergency'),
    ];
    return $map[$cat] ?? $cat;
}
