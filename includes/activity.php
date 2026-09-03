<?php
/**
 * includes/activity.php — system-wide Recent Activity log.
 * Call log_activity() after important actions (login, event create, camera update, etc.).
 */

function activity_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT DEFAULT NULL,
        user_name   VARCHAR(150) DEFAULT NULL,
        role        VARCHAR(64) DEFAULT NULL,
        action      VARCHAR(64) NOT NULL,
        entity_type VARCHAR(64) DEFAULT NULL,
        entity_id   INT DEFAULT NULL,
        summary     VARCHAR(500) NOT NULL,
        details     TEXT DEFAULT NULL,
        ip_address  VARCHAR(45) DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_act_created (created_at),
        KEY idx_act_user (user_id),
        KEY idx_act_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Write one activity row.
 *
 * @param array|null $asUser Optional override: ['id'=>, 'name'=>, 'role'=>] (needed on login page)
 */
function log_activity(
    PDO $pdo,
    string $action,
    string $summary,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null,
    ?array $asUser = null
): void {
    try {
        activity_ensure_table($pdo);
        if ($asUser) {
            $uid  = isset($asUser['id']) ? (int)$asUser['id'] : null;
            $name = $asUser['name'] ?? ($asUser['full_name'] ?? null);
            $role = $asUser['role'] ?? null;
        } else {
            $user = function_exists('current_user') ? current_user() : null;
            $uid  = isset($user['id']) ? (int)$user['id'] : null;
            $name = $user['full_name'] ?? ($user['name'] ?? null);
            $role = function_exists('current_role') ? current_role() : ($user['role'] ?? null);
        }
        // Fallback to session if helpers missing
        if ($uid === null && !empty($_SESSION['user_id'])) {
            $uid  = (int)$_SESSION['user_id'];
            $name = $name ?: ($_SESSION['user_name'] ?? null);
            $role = $role ?: ($_SESSION['user_role'] ?? null);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip && strlen($ip) > 45) $ip = substr($ip, 0, 45);

        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, user_name, role, action, entity_type, entity_id, summary, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $uid,
            $name,
            $role,
            $action,
            $entityType,
            $entityId,
            mb_substr($summary, 0, 500),
            $details,
            $ip,
        ]);
    } catch (Throwable $e) {
        // Never break the main flow if logging fails
    }
}

/**
 * Fetch recent activity rows (newest first).
 * If $userId is set, only that user's activity is returned (me only).
 */
function activity_recent(PDO $pdo, int $limit = 50, ?string $actionFilter = null, ?int $userId = null): array {
    activity_ensure_table($pdo);
    $limit = max(1, min(200, $limit));
    $sql = "SELECT * FROM activity_logs WHERE 1=1";
    $params = [];
    if ($userId !== null && $userId > 0) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    if ($actionFilter) {
        $sql .= " AND action = ?";
        $params[] = $actionFilter;
    }
    $sql .= " ORDER BY created_at DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
