<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'supervisor', 'operator']);

$role = current_role();
$myDeptId = current_user_department_id();

$SLA_HOURS = ['critical' => 1, 'high' => 4, 'medium' => 24, 'low' => 72];
$rows_s = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'sla_hours_%'")->fetchAll();
foreach ($rows_s as $row) { $SLA_HOURS[str_replace('sla_hours_', '', $row['setting_key'])] = (int) $row['setting_value']; }

$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_overdue = isset($_GET['overdue']) && $_GET['overdue'] === '1';
$filter_search = trim($_GET['q'] ?? '');
$filter_dept = $_GET['department'] ?? '';
$filter_from = $_GET['date_from'] ?? '';
$filter_to = $_GET['date_to'] ?? '';

$sql = "SELECT r.tracking_code, c.name AS category_name, r.priority, r.status, d.name AS department_name,
               r.location, r.address, r.gender, r.caller_name, r.caller_phone, r.response_time_minutes,
               r.created_at, r.updated_at, r.resolved_at, r.description
        FROM events r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN departments d ON d.id = r.assigned_department_id
        WHERE 1=1";
$params = [];

if ($role === 'department_officer' && $myDeptId) { $sql .= " AND r.assigned_department_id = ?"; $params[] = $myDeptId; }
if ($filter_category !== '') { $sql .= " AND r.category_id = ?"; $params[] = $filter_category; }
if ($filter_status !== '')   { $sql .= " AND r.status = ?"; $params[] = $filter_status; }
if ($filter_dept !== '')     { $sql .= " AND r.assigned_department_id = ?"; $params[] = $filter_dept; }
if ($filter_from !== '')     { $sql .= " AND r.created_at >= ?"; $params[] = $filter_from . ' 00:00:00'; }
if ($filter_to !== '')       { $sql .= " AND r.created_at <= ?"; $params[] = $filter_to . ' 23:59:59'; }
if ($filter_search !== '') {
    $sql .= " AND (r.tracking_code LIKE ? OR r.caller_name LIKE ? OR r.caller_phone LIKE ? OR r.location LIKE ? OR r.address LIKE ? OR r.description LIKE ?)";
    $like = '%' . $filter_search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($filter_overdue) {
    $sql .= " AND r.status NOT IN ('solved','unsolved') AND (
        (r.priority='critical' AND r.created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['critical']} HOUR)) OR
        (r.priority='high' AND r.created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['high']} HOUR)) OR
        (r.priority='medium' AND r.created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['medium']} HOUR)) OR
        (r.priority='low' AND r.created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['low']} HOUR))
    )";
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Log export for audit history — never block CSV if table/columns differ
try {
    $logStmt = $pdo->prepare("INSERT INTO report_generations (report_type, generated_by) VALUES ('csv_export', ?)");
    $logStmt->execute([$_SESSION['user_id'] ?? null]);
} catch (Throwable $e) {
    // ignore missing table / columns
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="9141_events_' . date('Y-m-d_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Tracking Code', 'Category', 'Priority', 'Status', 'Department', 'Location', 'Address', 'Gender', 'Caller Name', 'Caller Phone', 'Response Time (min)', 'Submitted', 'Last Updated', 'Resolved At', 'Description']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['tracking_code'], $r['category_name'], $r['priority'], $r['status'], $r['department_name'] ?? '',
        $r['location'] ?? '', $r['address'] ?? '', $r['gender'], $r['caller_name'] ?? '', $r['caller_phone'] ?? '',
        $r['response_time_minutes'] ?? '', $r['created_at'], $r['updated_at'], $r['resolved_at'] ?? '', $r['description'],
    ]);
}
fclose($out);
exit;
