<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

$role = current_role();
$myDeptId = current_user_department_id();
$tab = $_GET['tab'] ?? 'ongoing';
if (!in_array($tab, ['ongoing', 'solved', 'unsolved'], true)) $tab = 'ongoing';

$statusMap = ['ongoing' => ['assigned', 'ongoing'], 'solved' => ['solved'], 'unsolved' => ['unsolved']];
$placeholders = implode(',', array_fill(0, count($statusMap[$tab]), '?'));

$sql = "SELECT r.*, c.name AS category_name, c.icon, d.name AS department_name, u.full_name AS operator_name
        FROM events r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN departments d ON d.id = r.assigned_department_id
        LEFT JOIN users u ON u.id = r.operator_id
        WHERE r.status IN ($placeholders)";
$params = $statusMap[$tab];

if ($role === 'department_officer' && $myDeptId) {
    $sql .= " AND r.assigned_department_id = ?";
    $params[] = $myDeptId;
}
$sql .= " ORDER BY r.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$activeNav = 'monitoring';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('monitoring_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('monitoring_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="tabs">
        <a href="?tab=ongoing" class="<?= $tab==='ongoing'?'active':'' ?>"><?= t('monitoring_ongoing') ?></a>
        <a href="?tab=solved" class="<?= $tab==='solved'?'active':'' ?>"><?= t('monitoring_solved') ?></a>
        <a href="?tab=unsolved" class="<?= $tab==='unsolved'?'active':'' ?>"><?= t('monitoring_unsolved') ?></a>
    </div>

    <div class="card">
        <table>
            <tr>
                <th><?= t('dash_col_code') ?></th><th><?= t('dash_col_category') ?></th><th><?= t('dash_col_severity') ?></th>
                <th><?= t('dash_col_status') ?></th><th><?= t('dash_col_department') ?></th><th><?= t('label_operator') ?></th>
                <th><?= t('dash_col_submitted') ?></th><th><?= t('dash_col_manage') ?></th>
            </tr>
            <?php foreach ($events as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['tracking_code']) ?></td>
                <td><?= htmlspecialchars($r['icon'] ?? '') ?> <?= htmlspecialchars($r['category_name']) ?></td>
                <td><span class="badge <?= $r['priority'] ?>"><?= t('severity_' . $r['priority']) ?></span></td>
                <td><span class="badge <?= $r['status'] ?>"><?= t('status_' . $r['status']) ?></span></td>
                <td><?= htmlspecialchars($r['department_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['operator_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
                <td><a href="report.php?id=<?= $r['id'] ?>"><?= t('dash_col_manage') ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="8"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>
</main>
</div>
</body>
</html>
