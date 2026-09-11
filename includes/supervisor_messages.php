<?php
/**
 * Direct messages between public (citizen) and supervisor on a report.
 * Supports text, emoji, image, voice, video.
 *
 * direction: 'to_public'  = supervisor → citizen (visible on track)
 *            'to_supervisor' = citizen → supervisor (from public track form)
 */

function supervisor_messages_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS supervisor_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        direction ENUM('to_public','to_supervisor') NOT NULL DEFAULT 'to_public',
        supervisor_id INT DEFAULT NULL,
        supervisor_name VARCHAR(150) DEFAULT NULL,
        citizen_name VARCHAR(150) DEFAULT NULL,
        citizen_phone VARCHAR(32) DEFAULT NULL,
        message TEXT NOT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        attachment_path VARCHAR(500) DEFAULT NULL,
        attachment_name VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sm_event (event_id),
        KEY idx_sm_dir (direction)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = [
        'direction'       => "ALTER TABLE supervisor_messages ADD COLUMN direction ENUM('to_public','to_supervisor') NOT NULL DEFAULT 'to_public' AFTER event_id",
        'citizen_name'    => "ALTER TABLE supervisor_messages ADD COLUMN citizen_name VARCHAR(150) DEFAULT NULL AFTER supervisor_name",
        'citizen_phone'   => "ALTER TABLE supervisor_messages ADD COLUMN citizen_phone VARCHAR(32) DEFAULT NULL AFTER citizen_name",
        'is_read'         => "ALTER TABLE supervisor_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER message",
        'message_type'    => "ALTER TABLE supervisor_messages ADD COLUMN message_type VARCHAR(20) NOT NULL DEFAULT 'text' AFTER message",
        'attachment_path' => "ALTER TABLE supervisor_messages ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER message_type",
        'attachment_name' => "ALTER TABLE supervisor_messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path",
    ];
    foreach ($cols as $col => $sql) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM supervisor_messages LIKE " . $pdo->quote($col))->fetch();
            if (!$chk) $pdo->exec($sql);
        } catch (Throwable $e) { /* ignore */ }
    }
}

function event_is_old_enough(array $event, int $days = 7): bool {
    if (empty($event['created_at'])) return false;
    $created = strtotime($event['created_at']);
    if ($created === false) return false;
    return $created <= (time() - ($days * 86400));
}

function supervisor_messages_for_event(PDO $pdo, int $eventId, ?string $direction = null): array {
    supervisor_messages_ensure($pdo);
    if ($direction) {
        $stmt = $pdo->prepare(
            "SELECT * FROM supervisor_messages WHERE event_id = ? AND direction = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$eventId, $direction]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM supervisor_messages WHERE event_id = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$eventId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Save attachment for supervisor messages */
function sm_save_attachment(array $file, string $type): ?string {
    $allowed = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'voice' => ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/mpeg', 'audio/ogg'],
        'video' => ['video/webm', 'video/mp4', 'video/quicktime'],
    ];
    $maxSize = [
        'image' => 10 * 1024 * 1024,
        'voice' => 20 * 1024 * 1024,
        'video' => 50 * 1024 * 1024,
    ];
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (!isset($allowed[$type])) return null;
    if (($file['size'] ?? 0) > $maxSize[$type]) return null;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed[$type], true)) return null;

    $dir = __DIR__ . '/../uploads/sm/' . $type . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    chmod($dest, 0644);
    return 'uploads/sm/' . $type . '/' . $name;
}

function sm_save_base64(string $b64, string $type, string $ext = 'webm'): ?string {
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 100) return null;
    $max = $type === 'video' ? 50 * 1024 * 1024 : 20 * 1024 * 1024;
    if (strlen($raw) > $max) return null;
    $dir = __DIR__ . '/../uploads/sm/' . $type . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $name;
    if (file_put_contents($dest, $raw) === false) return null;
    chmod($dest, 0644);
    return 'uploads/sm/' . $type . '/' . $name;
}

/** Supervisor → public */
function supervisor_message_add(
    PDO $pdo,
    int $eventId,
    string $message,
    ?int $userId,
    ?string $userName,
    string $messageType = 'text',
    ?string $attachmentPath = null,
    ?string $attachmentName = null
): bool {
    $message = trim($message);
    $messageType = in_array($messageType, ['text', 'emoji', 'image', 'voice', 'video'], true) ? $messageType : 'text';
    if ($message === '' && empty($attachmentPath)) return false;
    if ($message === '') {
        $labels = ['image' => '📷 Image', 'voice' => '🎤 Voice', 'video' => '🎬 Video', 'emoji' => '😊'];
        $message = $labels[$messageType] ?? '[media]';
    }
    supervisor_messages_ensure($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO supervisor_messages (event_id, direction, supervisor_id, supervisor_name, message, message_type, attachment_path, attachment_name)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    return $stmt->execute([$eventId, 'to_public', $userId, $userName, $message, $messageType, $attachmentPath, $attachmentName]);
}

/** Public (citizen) → supervisor */
function citizen_message_to_supervisor(
    PDO $pdo,
    int $eventId,
    string $message,
    ?string $citizenName = null,
    ?string $citizenPhone = null,
    string $messageType = 'text',
    ?string $attachmentPath = null,
    ?string $attachmentName = null
): bool {
    $message = trim($message);
    $messageType = in_array($messageType, ['text', 'emoji', 'image', 'voice', 'video'], true) ? $messageType : 'text';
    if ($message === '' && empty($attachmentPath)) return false;
    if ($message === '') {
        $labels = ['image' => '📷 Image', 'voice' => '🎤 Voice', 'video' => '🎬 Video', 'emoji' => '😊'];
        $message = $labels[$messageType] ?? '[media]';
    }
    supervisor_messages_ensure($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO supervisor_messages (event_id, direction, citizen_name, citizen_phone, message, message_type, attachment_path, attachment_name)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    return $stmt->execute([
        $eventId,
        'to_supervisor',
        $citizenName !== '' ? $citizenName : null,
        $citizenPhone !== '' ? $citizenPhone : null,
        $message,
        $messageType,
        $attachmentPath,
        $attachmentName,
    ]);
}

/** Render message body with media */
function sm_render_body(array $msg): string {
    $type = $msg['message_type'] ?? 'text';
    $path = $msg['attachment_path'] ?? '';
    $text = htmlspecialchars($msg['message'] ?? '');
    $html = '';

    if ($path !== '') {
        $url = $path; // relative from site root
        if ($type === 'image') {
            $html .= '<div style="margin-bottom:6px;"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener"><img src="' . htmlspecialchars($url) . '" alt="image" style="max-width:220px;max-height:160px;border-radius:8px;"></a></div>';
        } elseif ($type === 'voice') {
            $html .= '<div style="margin-bottom:6px;"><audio controls src="' . htmlspecialchars($url) . '" style="max-width:240px;"></audio></div>';
        } elseif ($type === 'video') {
            $html .= '<div style="margin-bottom:6px;"><video controls src="' . htmlspecialchars($url) . '" style="max-width:260px;max-height:180px;border-radius:8px;"></video></div>';
        }
    }

    if ($text !== '' && !preg_match('/^(📷|🎤|🎬)/u', $msg['message'] ?? '')) {
        $html .= '<div style="line-height:1.5;">' . nl2br($text) . '</div>';
    } elseif ($html === '') {
        $html = nl2br($text);
    }

    return $html;
}
