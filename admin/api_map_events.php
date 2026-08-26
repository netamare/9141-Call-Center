<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
header('Content-Type: application/json');

$role = current_role();
$myDeptId = current_user_department_id();

$category = (int) ($_GET['category'] ?? 0);
$activeOnly = ($_GET['active_only'] ?? '1') !== '0';

$sql = "SELECT r.id, r.tracking_code, r.category_id, c.name AS category_name, c.icon,
               r.priority, r.status, r.address, r.location, r.latitude, r.longitude,
               r.created_at, d.name AS department_name
        FROM events r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN departments d ON d.id = r.assigned_department_id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL";
$params = [];

if ($activeOnly) {
    $sql .= " AND r.status IN ('new','assigned','ongoing')";
}
if ($category > 0) {
    $sql .= " AND r.category_id = ?";
    $params[] = $category;
}
if ($role === 'department_officer' && $myDeptId) {
    $sql .= " AND r.assigned_department_id = ?";
    $params[] = $myDeptId;
}
$sql .= " ORDER BY r.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo json_encode([
    'updated' => date('H:i:s'),
    'count' => count($rows),
    'events' => array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'code' => $r['tracking_code'],
            'category_id' => (int) $r['category_id'],
            'category_name' => $r['category_name'],
            'icon' => $r['icon'],
            'priority' => $r['priority'],
            'status' => $r['status'],
            'address' => $r['address'] ?: $r['location'],
            'lat' => (float) $r['latitude'],
            'lng' => (float) $r['longitude'],
            'created_at' => $r['created_at'],
            'department' => $r['department_name'],
        ];
    }, $rows),
]);
