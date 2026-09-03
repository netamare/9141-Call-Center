<?php
/**
 * Internal Direct Messaging between any staff users.
 * Works for: administrator, operator, supervisor,
 *            department_officer, camera_operator
 */

function dm_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_dm_sender (sender_id),
        KEY idx_dm_receiver (receiver_id),
        KEY idx_dm_pair (sender_id, receiver_id),
        KEY idx_dm_unread (receiver_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
    $limit = max(1, min(500, (int)$limit)); // safety clamp — LIMIT cannot be a bound string on MariaDB
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

/** Send a message */
function dm_send(PDO $pdo, int $senderId, int $receiverId, string $message): bool {
    dm_ensure_table($pdo);
    $message = trim($message);
    if ($message === '' || $senderId === $receiverId) return false;

    // Receiver must exist and be active
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $chk->execute([$receiverId]);
    if (!$chk->fetch()) return false;

    $stmt = $pdo->prepare(
        "INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)"
    );
    return $stmt->execute([$senderId, $receiverId, $message]);
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
        ':me1' => $myId, ':me2' => $myId, ':me3' => $myId, ':me4' => $myId,
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
