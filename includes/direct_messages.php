<?php
/**
 * Internal Direct Messaging between any staff users.
 * Works for: administrator, operator, supervisor,
 *            department_officer, camera_operator
 *
 * Supports: text, emoji, image, voice, video attachments.
 */

function dm_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        attachment_path VARCHAR(500) DEFAULT NULL,
        attachment_name VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_dm_sender (sender_id),
        KEY idx_dm_receiver (receiver_id),
        KEY idx_dm_pair (sender_id, receiver_id),
        KEY idx_dm_unread (receiver_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upgrade older tables
    $cols = [
        'message_type'    => "ALTER TABLE direct_messages ADD COLUMN message_type VARCHAR(20) NOT NULL DEFAULT 'text' AFTER message",
        'attachment_path' => "ALTER TABLE direct_messages ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER message_type",
        'attachment_name' => "ALTER TABLE direct_messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path",
    ];
    foreach ($cols as $col => $sql) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM direct_messages LIKE " . $pdo->quote($col))->fetch();
            if (!$chk) $pdo->exec($sql);
        } catch (Throwable $e) { /* ignore */ }
    }
}

/** All active users except the current one (for recipient dropdown) */
function dm_list_recipients(PDO $pdo, int $myId): array {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.username, u.role, u.department_id, d.name AS dept_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.status = 'active' AND u.id != ?
         ORDER BY u.role, u.full_name"
    );
    $stmt->execute([$myId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Conversation between two users (ordered by time) */
function dm_conversation(PDO $pdo, int $userA, int $userB, int $limit = 200): array {
    dm_ensure_table($pdo);
    $limit = max(1, min(500, (int)$limit));
    $stmt = $pdo->prepare(
        "SELECT m.*, s.full_name AS sender_name, s.role AS sender_role
         FROM direct_messages m
         JOIN users s ON s.id = m.sender_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC
         LIMIT $limit"
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Save a DM media attachment (image / voice / video).
 * Returns relative path on success, null on failure.
 */
function dm_save_attachment(array $file, string $type): ?string {
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
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!isset($allowed[$type])) return null;
    if (($file['size'] ?? 0) > $maxSize[$type]) return null;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed[$type], true)) return null;

    $dir = __DIR__ . '/../uploads/dm/' . $type . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps)$\">\nDeny from all\n</FilesMatch>\n");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: ($type === 'image' ? 'jpg' : ($type === 'voice' ? 'webm' : 'webm')));
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    chmod($dest, 0644);
    return 'uploads/dm/' . $type . '/' . $name;
}

/**
 * Save base64-encoded media (from browser MediaRecorder).
 */
function dm_save_base64(string $b64, string $type, string $ext = 'webm'): ?string {
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 100) return null;
    $max = $type === 'video' ? 50 * 1024 * 1024 : 20 * 1024 * 1024;
    if (strlen($raw) > $max) return null;

    $dir = __DIR__ . '/../uploads/dm/' . $type . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $name;
    if (file_put_contents($dest, $raw) === false) return null;
    chmod($dest, 0644);
    return 'uploads/dm/' . $type . '/' . $name;
}

/**
 * Send a message (text / emoji / media).
 * $messageType: text | emoji | image | voice | video
 */
function dm_send(
    PDO $pdo,
    int $senderId,
    int $receiverId,
    string $message,
    string $messageType = 'text',
    ?string $attachmentPath = null,
    ?string $attachmentName = null
): bool {
    dm_ensure_table($pdo);
    $message = trim($message);
    $messageType = in_array($messageType, ['text', 'emoji', 'image', 'voice', 'video'], true) ? $messageType : 'text';

    // Allow empty text only when media is attached
    if ($message === '' && empty($attachmentPath) && $messageType === 'text') return false;
    if ($senderId === $receiverId) return false;

    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $chk->execute([$receiverId]);
    if (!$chk->fetch()) return false;

    // Placeholder text for media-only messages
    if ($message === '') {
        $labels = ['image' => '📷 Image', 'voice' => '🎤 Voice', 'video' => '🎬 Video', 'emoji' => '😊'];
        $message = $labels[$messageType] ?? '[media]';
    }

    $stmt = $pdo->prepare(
        "INSERT INTO direct_messages (sender_id, receiver_id, message, message_type, attachment_path, attachment_name)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    return $stmt->execute([
        $senderId,
        $receiverId,
        $message,
        $messageType,
        $attachmentPath,
        $attachmentName,
    ]);
}

/** Mark conversation as read for the current user */
function dm_mark_read(PDO $pdo, int $myId, int $otherId): void {
    dm_ensure_table($pdo);
    $stmt = $pdo->prepare(
        "UPDATE direct_messages SET is_read = 1
         WHERE receiver_id = ? AND sender_id = ? AND is_read = 0"
    );
    $stmt->execute([$myId, $otherId]);
}

/** Unread count for badge */
function dm_unread_count(PDO $pdo, int $myId): int {
    dm_ensure_table($pdo);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0"
    );
    $stmt->execute([$myId]);
    return (int) $stmt->fetchColumn();
}

/** List of people I have chatted with (latest message first) */
function dm_inbox(PDO $pdo, int $myId): array {
    dm_ensure_table($pdo);
    $sql = "
        SELECT
            u.id AS user_id,
            u.full_name,
            u.username,
            u.role,
            d.name AS dept_name,
            (SELECT message FROM direct_messages m2
             WHERE (m2.sender_id = u.id AND m2.receiver_id = :me1)
                OR (m2.sender_id = :me2 AND m2.receiver_id = u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
            (SELECT message_type FROM direct_messages m2
             WHERE (m2.sender_id = u.id AND m2.receiver_id = :me1b)
                OR (m2.sender_id = :me2b AND m2.receiver_id = u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_type,
            (SELECT created_at FROM direct_messages m2
             WHERE (m2.sender_id = u.id AND m2.receiver_id = :me3)
                OR (m2.sender_id = :me4 AND m2.receiver_id = u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_at,
            (SELECT COUNT(*) FROM direct_messages m3
             WHERE m3.sender_id = u.id AND m3.receiver_id = :me5 AND m3.is_read = 0) AS unread
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.status = 'active'
          AND u.id != :me6
          AND EXISTS (
              SELECT 1 FROM direct_messages m
              WHERE (m.sender_id = u.id AND m.receiver_id = :me7)
                 OR (m.sender_id = :me8 AND m.receiver_id = u.id)
          )
        ORDER BY last_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':me1' => $myId, ':me1b' => $myId, ':me2' => $myId, ':me2b' => $myId,
        ':me3' => $myId, ':me4' => $myId,
        ':me5' => $myId, ':me6' => $myId, ':me7' => $myId, ':me8' => $myId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dm_role_label(string $role): string {
    $map = [
        'administrator'      => 'Admin',
        'operator'           => 'Operator',
        'supervisor'         => 'Supervisor',
        'department_officer' => 'Dept Officer',
        'camera_operator'    => 'Camera Room',
    ];
    return $map[$role] ?? $role;
}

/** Render message body (text + media) for display */
function dm_render_body(array $msg): string {
    $type = $msg['message_type'] ?? 'text';
    $path = $msg['attachment_path'] ?? '';
    $text = htmlspecialchars($msg['message'] ?? '');
    $html = '';

    if ($path !== '') {
        $url = '../' . ltrim($path, '/');
        if ($type === 'image') {
            $html .= '<div class="dm-media"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener"><img src="' . htmlspecialchars($url) . '" alt="image" style="max-width:240px;max-height:180px;border-radius:8px;"></a></div>';
        } elseif ($type === 'voice') {
            $html .= '<div class="dm-media"><audio controls src="' . htmlspecialchars($url) . '" style="max-width:260px;"></audio></div>';
        } elseif ($type === 'video') {
            $html .= '<div class="dm-media"><video controls src="' . htmlspecialchars($url) . '" style="max-width:280px;max-height:200px;border-radius:8px;"></video></div>';
        }
    }

    if ($type === 'emoji' || ($type === 'text' && $text !== '')) {
        $html .= '<div class="dm-text">' . nl2br($text) . '</div>';
    } elseif ($type !== 'text' && $text !== '' && !preg_match('/^(📷|🎤|🎬)/u', $msg['message'] ?? '')) {
        $html .= '<div class="dm-text">' . nl2br($text) . '</div>';
    }

    return $html !== '' ? $html : nl2br($text);
}
