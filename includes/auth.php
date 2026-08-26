<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../config.php';
require __DIR__ . '/lang.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Idle-session auto-logout: every role must re-authenticate after a period
// of inactivity, rather than staying logged in indefinitely.
$idleLimitStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'session_idle_minutes'");
$idleLimitStmt->execute();
$idleMinutes = (int) ($idleLimitStmt->fetchColumn() ?: 20);

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleMinutes * 60) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

/**
 * Role-based access control.
 * Roles: administrator, operator, supervisor, department_officer, camera_operator
 * (camera_operator = Control Room role for video cameras + AI detection of the 4 problem categories).
 */
function current_role() {
    return strtolower(trim((string)($_SESSION['user_role'] ?? '')));
}

/** Restrict a page to specific roles. Call after requiring auth.php.
 *  Empty $roles list still denies (used for explicit deny). */
function require_role(array $roles) {
    $role = current_role();
    $allowed = array_map(static function ($r) {
        return strtolower(trim((string)$r));
    }, $roles);
    if ($role === '' || !in_array($role, $allowed, true)) {
        http_response_code(403);
        require __DIR__ . '/../admin/includes/access_denied.php';
        exit;
    }
}

function is_administrator() { return current_role() === 'administrator'; }
function is_operator() { return current_role() === 'operator'; }
function is_supervisor() { return current_role() === 'supervisor'; }
function is_department_officer() { return current_role() === 'department_officer'; }
function is_camera_operator() { return current_role() === 'camera_operator'; }

/** Department officers only ever see events assigned to their own department. */
function current_user_department_id() {
    return $_SESSION['user_department_id'] ?? null;
}

/** Current logged-in user summary (name, role, etc.) for display / uploads. */
function current_user() {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'department_id' => $_SESSION['user_department_id'] ?? null,
    ];
}
