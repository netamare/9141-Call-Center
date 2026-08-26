<?php
/**
 * Adama 9141 — Camera / Real-time streaming helpers
 *
 * Supports stream types:
 *   - hls   : native browser or hls.js (.m3u8)
 *   - http  : progressive HTTP / MP4 / MJPEG
 *   - rtsp  : RTSP source (must be restreamed to HLS via ffmpeg or MediaMTX)
 *   - mjpeg : multipart JPEG stream
 *   - webrtc: placeholder (requires external SFU)
 *
 * Table `cameras` is created on first use.
 */

function cameras_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cameras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        location VARCHAR(255) DEFAULT NULL,
        stream_url TEXT NOT NULL,
        stream_type ENUM('hls','http','rtsp','mjpeg','webrtc') NOT NULL DEFAULT 'hls',
        status ENUM('active','inactive','error') NOT NULL DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * List cameras (optionally only active).
 */
function cameras_list(PDO $pdo, bool $activeOnly = true): array {
    cameras_ensure_table($pdo);
    $sql = "SELECT * FROM cameras";
    if ($activeOnly) $sql .= " WHERE status = 'active'";
    $sql .= " ORDER BY name";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Get one camera by id.
 */
function cameras_get(PDO $pdo, int $id): ?array {
    cameras_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Save (insert or update) a camera definition.
 */
function cameras_save(PDO $pdo, array $data): int {
    cameras_ensure_table($pdo);
    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $location = trim($data['location'] ?? '') ?: null;
    $url = trim($data['stream_url'] ?? '');
    $type = in_array($data['stream_type'] ?? '', ['hls','http','rtsp','mjpeg','webrtc'], true)
        ? $data['stream_type'] : 'hls';
    $status = in_array($data['status'] ?? '', ['active','inactive','error'], true)
        ? $data['status'] : 'active';
    $notes = trim($data['notes'] ?? '') ?: null;

    if ($name === '' || $url === '') {
        throw new InvalidArgumentException('Name and stream URL are required');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE cameras SET name=?, location=?, stream_url=?, stream_type=?, status=?, notes=? WHERE id=?"
        );
        $stmt->execute([$name, $location, $url, $type, $status, $notes, $id]);
        return $id;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO cameras (name, location, stream_url, stream_type, status, notes) VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([$name, $location, $url, $type, $status, $notes]);
    return (int)$pdo->lastInsertId();
}

/**
 * Delete a camera.
 */
function cameras_delete(PDO $pdo, int $id): bool {
    cameras_ensure_table($pdo);
    $stmt = $pdo->prepare("DELETE FROM cameras WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Public JSON-safe camera payload (no internal notes).
 */
function cameras_public_payload(array $cam): array {
    return [
        'id'          => (int)$cam['id'],
        'name'        => $cam['name'],
        'location'    => $cam['location'],
        'stream_url'  => $cam['stream_url'],
        'stream_type' => $cam['stream_type'],
        'status'      => $cam['status'],
    ];
}
