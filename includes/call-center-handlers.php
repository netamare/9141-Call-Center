<?php
/**
 * Call Center Operator - Voice & Video Upload Handlers
 * Include this file in new_event.php: require __DIR__ . '/call-center-handlers.php';
 */

// Configuration
define('VOICE_UPLOAD_DIR', __DIR__ . '/../uploads/voice/');
define('VIDEO_UPLOAD_DIR', __DIR__ . '/../uploads/video/');
define('MAX_VOICE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_VOICE_TYPES', ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/mpeg']);
define('ALLOWED_VIDEO_TYPES', ['video/webm', 'video/mp4', 'video/quicktime']);

/**
 * Create upload directories if they don't exist
 */
function ensure_upload_directories() {
    if (!is_dir(VOICE_UPLOAD_DIR)) {
        mkdir(VOICE_UPLOAD_DIR, 0755, true);
    }
    if (!is_dir(VIDEO_UPLOAD_DIR)) {
        mkdir(VIDEO_UPLOAD_DIR, 0755, true);
    }
}

/**
 * Handle voice file upload
 * @param array $file $_FILES array
 * @return string|null Filename on success, null on failure
 */
function handle_voice_upload($file) {
    ensure_upload_directories();

    // Validate upload
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Check file size
    if ($file['size'] > MAX_VOICE_SIZE) {
        error_log("Voice file too large: " . $file['size']);
        return null;
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_VOICE_TYPES)) {
        error_log("Invalid voice MIME type: " . $mime);
        return null;
    }

    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $random = bin2hex(random_bytes(4));
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $timestamp . '_' . $random . '.' . $extension;
    $filepath = VOICE_UPLOAD_DIR . $filename;

    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        return $filename;
    }

    error_log("Failed to move voice file to: " . $filepath);
    return null;
}

/**
 * Handle video file upload
 * @param array $file $_FILES array
 * @return string|null Filename on success, null on failure
 */
function handle_video_upload($file) {
    ensure_upload_directories();

    // Validate upload
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Check file size
    if ($file['size'] > MAX_VIDEO_SIZE) {
        error_log("Video file too large: " . $file['size']);
        return null;
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_VIDEO_TYPES)) {
        error_log("Invalid video MIME type: " . $mime);
        return null;
    }

    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $random = bin2hex(random_bytes(4));
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $timestamp . '_' . $random . '.' . $extension;
    $filepath = VIDEO_UPLOAD_DIR . $filename;

    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        // Log video upload for processing
        error_log("Video uploaded: " . $filename . " (" . filesize($filepath) . " bytes)");
        return $filename;
    }

    error_log("Failed to move video file to: " . $filepath);
    return null;
}

/**
 * Handle base64 encoded voice data
 * @param string $base64_data Base64 encoded audio data
 * @param string $event_id Event ID for naming
 * @return string|null Filename on success, null on failure
 */
function handle_voice_base64($base64_data, $event_id) {
    ensure_upload_directories();

    try {
        $data = base64_decode($base64_data);
        if ($data === false) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "event_" . $event_id . "_" . $timestamp . ".webm";
        $filepath = VOICE_UPLOAD_DIR . $filename;

        if (file_put_contents($filepath, $data)) {
            chmod($filepath, 0644);
            return $filename;
        }
    } catch (Exception $e) {
        error_log("Error handling base64 voice: " . $e->getMessage());
    }

    return null;
}

/**
 * Handle base64 encoded video data
 * @param string $base64_data Base64 encoded video data
 * @param string $event_id Event ID for naming
 * @return string|null Filename on success, null on failure
 */
function handle_video_base64($base64_data, $event_id) {
    ensure_upload_directories();

    try {
        $data = base64_decode($base64_data);
        if ($data === false) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "event_" . $event_id . "_" . $timestamp . ".webm";
        $filepath = VIDEO_UPLOAD_DIR . $filename;

        if (file_put_contents($filepath, $data)) {
            chmod($filepath, 0644);
            error_log("Base64 video saved: " . $filename . " (" . strlen($data) . " bytes)");
            return $filename;
        }
    } catch (Exception $e) {
        error_log("Error handling base64 video: " . $e->getMessage());
    }

    return null;
}

/**
 * Get voice file URL
 * @param string $filename Filename
 * @return string URL to voice file
 */
function get_voice_url($filename) {
    return '../uploads/voice/' . urlencode($filename);
}

/**
 * Get video file URL
 * @param string $filename Filename
 * @return string URL to video file
 */
function get_video_url($filename) {
    return '../uploads/video/' . urlencode($filename);
}

/**
 * Delete voice file
 * @param string $filename Filename
 * @return bool Success
 */
function delete_voice_file($filename) {
    $filepath = VOICE_UPLOAD_DIR . basename($filename);
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Delete video file
 * @param string $filename Filename
 * @return bool Success
 */
function delete_video_file($filename) {
    $filepath = VIDEO_UPLOAD_DIR . basename($filename);
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get file info for event
 * @param PDO $pdo Database connection
 * @param int $event_id Event ID
 * @return array Event media info
 */
function get_event_media($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT registration_method, voice_file, video_file FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Cleanup old media files (older than specified days)
 * @param int $days Delete files older than this many days
 * @return array Deleted files info
 */
function cleanup_old_media($days = 30) {
    $deleted = ['voice' => 0, 'video' => 0];
    $cutoff_time = time() - ($days * 86400);

    // Cleanup voice files
    if (is_dir(VOICE_UPLOAD_DIR)) {
        foreach (glob(VOICE_UPLOAD_DIR . '*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted['voice']++;
                }
            }
        }
    }

    // Cleanup video files
    if (is_dir(VIDEO_UPLOAD_DIR)) {
        foreach (glob(VIDEO_UPLOAD_DIR . '*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted['video']++;
                }
            }
        }
    }

    return $deleted;
}
