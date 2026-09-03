<?php
/**
 * Direct messages between public (citizen) and supervisor on a report.
 * Allowed only when the event was reported at least 7 days ago.
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
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sm_event (event_id),
        KEY idx_sm_dir (direction)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upgrade older table (missing columns)
    $cols = [
        'direction' => "ALTER TABLE supervisor_messages ADD COLUMN direction ENUM('to_public','to_supervisor') NOT NULL DEFAULT 'to_public' AFTER event_id",
        'citizen_name' => "ALTER TABLE supervisor_messages ADD COLUMN citizen_name VARCHAR(150) DEFAULT NULL AFTER supervisor_name",
        'citizen_phone' => "ALTER TABLE supervisor_messages ADD COLUMN citizen_phone VARCHAR(32) DEFAULT NULL AFTER citizen_name",
        'is_read' => "ALTER TABLE supervisor_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER message",
    ];
    foreach ($cols as $col => $sql) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM supervisor_messages LIKE " . $pdo->quote($col))->fetch();
            if (!$chk) $pdo->exec($sql);
        } catch (Throwable $e) { /* ignore */ }
    }
}

/** True if event is at least $days old (default 7). */
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

/** Supervisor → public */
function supervisor_message_add(PDO $pdo, int $eventId, string $message, ?int $userId, ?string $userName): bool {
    $message = trim($message);
    if ($message === '') return false;
    supervisor_messages_ensure($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO supervisor_messages (event_id, direction, supervisor_id, supervisor_name, message)
         VALUES (?,?,?,?,?)"
    );
    return $stmt->execute([$eventId, 'to_public', $userId, $userName, $message]);
}

/** Public (citizen) → supervisor */
function citizen_message_to_supervisor(
    PDO $pdo,
    int $eventId,
    string $message,
    ?string $citizenName = null,
    ?string $citizenPhone = null
): bool {
    $message = trim($message);
    if ($message === '') return false;
    supervisor_messages_ensure($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO supervisor_messages (event_id, direction, citizen_name, citizen_phone, message)
         VALUES (?,?,?,?,?)"
    );
    return $stmt->execute([
        $eventId,
        'to_supervisor',
        $citizenName !== '' ? $citizenName : null,
        $citizenPhone !== '' ? $citizenPhone : null,
        $message,
    ]);
}
