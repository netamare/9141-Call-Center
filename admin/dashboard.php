<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

$role = current_role();
$myDeptId = current_user_department_id();

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency'];

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Department officers only ever see events assigned to their own department
$deptScopeSql = '';
$deptScopeParams = [];
if ($role === 'department_officer' && $myDeptId) {
    $deptScopeSql = " AND r.assigned_department_id = ?";
    $deptScopeParams[] = $myDeptId;
}

// Per-category counts for the services panel
$counts_stmt = $pdo->query("SELECT category_id, COUNT(*) AS c FROM events GROUP BY category_id");
$counts_by_cat = [];
foreach ($counts_stmt->fetchAll() as $row) {
    $counts_by_cat[$row['category_id']] = $row['c'];
}

// Most recent uploaded photos per category (column is created_at, not uploaded_at)
$img_stmt = $pdo->query("SELECT r.category_id, ra.file_path, ra.event_id, ra.created_at AS uploaded_at
                          FROM event_attachments ra
                          JOIN events r ON r.id = ra.event_id
                          WHERE ra.file_type = 'image'
                          ORDER BY ra.created_at DESC");
$images_by_cat = [];
foreach ($img_stmt->fetchAll() as $row) {
    $cid = $row['category_id'];
    if (!isset($images_by_cat[$cid])) $images_by_cat[$cid] = [];
    if (count($images_by_cat[$cid]) < 3) $images_by_cat[$cid][] = $row;
}

// Stats (per the project spec's dashboard requirements)
$statsSql = "SELECT
    SUM(status='new') AS new_count,
    SUM(status IN ('assigned','ongoing')) AS active_count,
    SUM(status='solved') AS solved_count,
    SUM(status='unsolved') AS unsolved_count,
    SUM(DATE(created_at) = CURDATE()) AS today_count,
    SUM(MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) AS month_count,
    AVG(response_time_minutes) AS avg_response,
    COUNT(*) AS total_count
    FROM events r WHERE 1=1" . $deptScopeSql;
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($deptScopeParams);
$stats = $statsStmt->fetch();

$operator_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='operator' AND status='active'")->fetchColumn();

// SLA response-time targets by severity/priority (hours), loaded from settings
$sla_rows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'sla_hours_%'")->fetchAll();
$SLA_HOURS = ['critical' => 1, 'high' => 4, 'medium' => 24, 'low' => 72];
foreach ($sla_rows as $row) {
    $level = str_replace('sla_hours_', '', $row['setting_key']);
    $SLA_HOURS[$level] = (int) $row['setting_value'];
}

$overdue_stmt = $pdo->prepare("SELECT COUNT(*) FROM events r WHERE status NOT IN ('solved','unsolved') AND (
    (priority='critical' AND created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['critical']} HOUR)) OR
    (priority='high' AND created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['high']} HOUR)) OR
    (priority='medium' AND created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['medium']} HOUR)) OR
    (priority='low' AND created_at < DATE_SUB(NOW(), INTERVAL {$SLA_HOURS['low']} HOUR))
){$deptScopeSql}");
$overdue_stmt->execute($deptScopeParams);
$overdue_count = (int) $overdue_stmt->fetchColumn();

// Filters
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_overdue = isset($_GET['overdue']) && $_GET['overdue'] === '1';
$filter_search = trim($_GET['q'] ?? '');

$sql = "SELECT r.*, c.name AS category_name, c.icon, d.name AS department_name, u.full_name AS operator_name
        FROM events r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN departments d ON d.id = r.assigned_department_id
        LEFT JOIN users u ON u.id = r.operator_id
        WHERE 1=1" . $deptScopeSql;
$params = $deptScopeParams;

if ($filter_category !== '') { $sql .= " AND r.category_id = ?"; $params[] = $filter_category; }
if ($filter_status !== '')   { $sql .= " AND r.status = ?"; $params[] = $filter_status; }
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
$sql .= " ORDER BY r.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

function is_overdue($report) {
    global $SLA_HOURS;
    if (in_array($report['status'], ['solved','unsolved'], true)) return false;
    $hours = $SLA_HOURS[$report['priority']] ?? 24;
    return strtotime($report['created_at']) < strtotime("-$hours hours");
}

// ---- Chart data ----
// Events by month (last 6 months)
$byMonthStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM events r
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $deptScopeSql GROUP BY ym ORDER BY ym");
$byMonthStmt->execute($deptScopeParams);
$byMonth = $byMonthStmt->fetchAll();

// Events by department
$byDeptStmt = $pdo->prepare("SELECT COALESCE(d.name,'Unassigned') name, COUNT(*) c FROM events r
    LEFT JOIN departments d ON d.id = r.assigned_department_id WHERE 1=1 $deptScopeSql GROUP BY d.id ORDER BY c DESC");
$byDeptStmt->execute($deptScopeParams);
$byDept = $byDeptStmt->fetchAll();

// Events by category
$byCatStmt = $pdo->prepare("SELECT c.name, COUNT(*) cnt FROM events r JOIN categories c ON c.id=r.category_id WHERE 1=1 $deptScopeSql GROUP BY c.id");
$byCatStmt->execute($deptScopeParams);
$byCat = $byCatStmt->fetchAll();

require __DIR__ . '/../includes/notifications.php';
$stale_info = in_array($role, ['administrator', 'operator'], true) ? count_stale_new_events($pdo) : ['count' => 0, 'minutes' => 5];

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('dash_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="shell">
<?php $activeNav = 'dashboard'; include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php if ($stale_info['count'] > 0): ?>
    <div class="escalation-banner" role="alert">
        <div class="escalation-banner-icon">⏳</div>
        <div>
            <strong><?= $stale_info['count'] ?></strong>
            <?= $stale_info['count'] === 1 ? t('escalate_banner_one') : t('escalate_banner_many') ?>
            <?= sprintf(t_raw('escalate_banner_threshold'), $stale_info['minutes']) ?>
        </div>
        <a class="btn btn-sm" href="dashboard.php?status=new">⏳ <?= t('escalate_banner_view') ?></a>
    </div>
    <?php endif; ?>

    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <div class="eyebrow" style="font-family:var(--mono); font-size:10.5px; letter-spacing:2px; text-transform:uppercase; color:var(--cyan); margin-bottom:6px;">
                <?= t('role_' . $role) ?> <?= t('nav_dashboard') ?>
            </div>
            <h2 style="margin:0;"><?= t('dash_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;">
                <?= $role === 'administrator' ? t('site_subtitle') : ($role === 'department_officer' ? htmlspecialchars($departments[array_search($myDeptId, array_column($departments,'id'))]['name'] ?? '') : t('site_subtitle')) ?>
            </div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <h2 style="margin-top:0"><?= t('dash_services') ?></h2>
    <div class="services-grid">
        <?php foreach ($categories as $i => $c): ?>
            <div class="service-card">
                <div class="icon"><?= htmlspecialchars($c['icon'] ?? '📋') ?></div>
                <div class="name"><?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></div>
                <div class="desc"><?= htmlspecialchars($c['description'] ?? '') ?></div>
                <div class="count"><?= (int)($counts_by_cat[$c['id']] ?? 0) ?></div>
                <?php if (!empty($images_by_cat[$c['id']])): ?>
                <div class="service-photos">
                    <?php foreach ($images_by_cat[$c['id']] as $img): ?>
                        <a href="report.php?id=<?= $img['event_id'] ?>" title="Open event">
                            <img src="../<?= htmlspecialchars($img['file_path']) ?>" alt="Reported photo" class="service-photo-thumb">
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="service-photos-empty">No photos submitted yet</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid4">
        <div class="stat"><div class="num"><?= (int)$stats['total_count'] ?></div><div class="lbl"><?= t('dash_total') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['new_count'] ?></div><div class="lbl"><?= t('dash_new') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['active_count'] ?></div><div class="lbl"><?= t('dash_active') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['solved_count'] ?></div><div class="lbl"><?= t('dash_resolved') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['unsolved_count'] ?></div><div class="lbl"><?= t('dash_unsolved') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['today_count'] ?></div><div class="lbl"><?= t('dash_today') ?></div></div>
        <div class="stat"><div class="num"><?= (int)$stats['month_count'] ?></div><div class="lbl"><?= t('dash_monthly') ?></div></div>
        <div class="stat"><div class="num"><?= $stats['avg_response'] ? round($stats['avg_response']) . 'm' : '—' ?></div><div class="lbl"><?= t('dash_avg_response') ?></div></div>
        <?php if (in_array($role, ['administrator','supervisor'], true)): ?>
        <div class="stat"><div class="num"><?= (int)$operator_count ?></div><div class="lbl"><?= t('dash_operators') ?></div></div>
        <?php endif; ?>
        <div class="stat" style="<?= $overdue_count > 0 ? 'background:rgba(241,101,101,0.10); border-color:rgba(241,101,101,0.35);' : '' ?>"><div class="num" style="<?= $overdue_count > 0 ? 'color:var(--red);' : '' ?>"><?= $overdue_count ?></div><div class="lbl">⏰ Overdue (SLA)</div></div>
    </div>

    <?php if (in_array($role, ['administrator','supervisor'], true)): ?>
    <div class="chart-grid">
        <div class="chart-box"><h3><?= t('dash_chart_by_month') ?></h3><div class="chart-wrap"><canvas id="chartMonth"></canvas></div></div>
        <div class="chart-box"><h3><?= t('dash_chart_by_dept') ?></h3><div class="chart-wrap"><canvas id="chartDept"></canvas></div></div>
        <div class="chart-box">
            <h3><?= t('dash_chart_solved_unsolved') ?></h3>
            <div class="chart-wrap donut-wrap">
                <canvas id="chartSolved"></canvas>
                <?php
                    $solvedTotal = (int)$stats['solved_count'] + (int)$stats['unsolved_count'];
                    $solvedPct = $solvedTotal > 0 ? round(($stats['solved_count'] / $solvedTotal) * 100) : 0;
                ?>
                <div class="donut-center"><div class="big"><?= $solvedPct ?>%</div><div class="small"><?= t('dash_resolved') ?></div></div>
            </div>
        </div>
        <div class="chart-box"><h3><?= t('dash_chart_categories') ?></h3><div class="chart-wrap"><canvas id="chartCat"></canvas></div></div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert success"><?= t('delete_report_success') ?></div>
    <?php endif; ?>

    <div class="card" id="events">
        <form method="get" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
            <div style="flex:2; min-width:200px;">
                <label>🔎 <?= t('search_label') ?></label>
                <input type="text" name="q" value="<?= htmlspecialchars($filter_search) ?>" placeholder="<?= t('search_placeholder') ?>">
            </div>
            <div style="flex:1">
                <label><?= t('dash_filter_category') ?></label>
                <select name="category">
                    <option value=""><?= t('dash_filter_all') ?></option>
                    <?php foreach ($categories as $i => $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1">
                <label><?= t('dash_filter_status') ?></label>
                <select name="status">
                    <option value=""><?= t('dash_filter_all') ?></option>
                    <?php foreach (['new','assigned','ongoing','solved','unsolved'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= t('status_' . $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:6px; padding-bottom:10px;">
                <input type="checkbox" name="overdue" value="1" id="overdueChk" <?= $filter_overdue ? 'checked' : '' ?> style="width:auto;">
                <label for="overdueChk" style="margin:0;">⏰ Overdue only</label>
            </div>
            <div><button type="submit"><?= t('dash_filter_btn') ?></button></div>
            <?php if (in_array($role, ['administrator','supervisor'], true)): ?>
            <div><a class="btn" href="export_csv.php?category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&overdue=<?= $filter_overdue ? 1 : 0 ?>&q=<?= urlencode($filter_search) ?>">⬇ Export CSV</a></div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2><?= t('dash_reports') ?></h2>
        <table>
            <tr>
                <th><?= t('dash_col_code') ?></th><th><?= t('dash_col_category') ?></th><th><?= t('dash_col_severity') ?></th>
                <th><?= t('dash_col_status') ?></th><th><?= t('dash_col_department') ?></th><th><?= t('label_operator') ?></th><th><?= t('dash_col_submitted') ?></th><th><?= t('dash_col_manage') ?></th>
            </tr>
            <?php foreach ($reports as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['tracking_code']) ?></td>
                <td><?= htmlspecialchars($r['icon'] ?? '') ?> <?= htmlspecialchars($r['category_name']) ?></td>
                <td><span class="badge <?= $r['priority'] ?>"><?= t('severity_' . $r['priority']) ?></span></td>
                <td><span class="badge <?= $r['status'] ?>"><?= t('status_' . $r['status']) ?></span>
                    <?php if (is_overdue($r)): ?><span class="badge unsolved" title="Past SLA response target">⏰ OVERDUE</span><?php endif; ?>
                    <?php if ($r['status'] === 'new' && in_array($role, ['administrator','operator'], true)): ?>
                        <span class="timer-badge" data-created="<?= htmlspecialchars($r['created_at']) ?>" data-limit="<?= (int)$stale_info['minutes'] ?>" title="<?= htmlspecialchars(t_raw('escalate_countdown_title')) ?>">--:--</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['department_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['operator_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
                <td><a href="report.php?id=<?= $r['id'] ?>"><?= t('dash_col_manage') ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$reports): ?><tr><td colspan="8"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>

</main>
</div>

<script>
// Escalating alert countdown badges: every "new" (unhandled) event counts
// down from the operator-alert threshold (default 5 min, set in Settings).
// It turns amber inside the last minute and red + pulsing once escalated —
// matching the urgent notification that fires server-side at the same mark.
(function(){
    function tick(){
        document.querySelectorAll('.timer-badge').forEach(function(el){
            var created = new Date(el.dataset.created.replace(' ', 'T')).getTime();
            var limitMs = parseInt(el.dataset.limit, 10) * 60000;
            var remaining = created + limitMs - Date.now();
            el.classList.remove('warn', 'escalated');
            if (remaining <= 0) {
                el.textContent = '⚠ ' + '<?= addslashes(t_raw('escalate_countdown_now')) ?>';
                el.classList.add('escalated');
            } else {
                var m = Math.floor(remaining / 60000);
                var s = Math.floor((remaining % 60000) / 1000);
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                if (remaining < 60000) el.classList.add('warn');
            }
        });
    }
    if (document.querySelector('.timer-badge')) { tick(); setInterval(tick, 1000); }
})();
</script>

<?php if (in_array($role, ['administrator','supervisor'], true)): ?>
<script>
const monthLabels = <?= json_encode(array_column($byMonth, 'ym')) ?>;
const monthData = <?= json_encode(array_map('intval', array_column($byMonth, 'c'))) ?>;
const deptLabels = <?= json_encode(array_column($byDept, 'name')) ?>;
const deptData = <?= json_encode(array_map('intval', array_column($byDept, 'c'))) ?>;
const catLabels = <?= json_encode(array_column($byCat, 'name')) ?>;
const catData = <?= json_encode(array_map('intval', array_column($byCat, 'cnt'))) ?>;

Chart.defaults.color = '#64748B';
Chart.defaults.borderColor = 'rgba(148,163,184,0.3)';
Chart.defaults.font.family = "'Manrope', sans-serif";
const gridOpt = { grid: { color: 'rgba(148,163,184,0.25)' } };
const legendStyle = { labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 } };

function fadeFill(ctx, color, alphaTop, alphaBottom) {
    const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
    g.addColorStop(0, color + alphaTop);
    g.addColorStop(1, color + alphaBottom);
    return g;
}

new Chart(document.getElementById('chartMonth'), {
    type: 'line',
    data: { labels: monthLabels, datasets: [{ label: 'Events', data: monthData, borderColor: '#4F46E5', backgroundColor: (c) => fadeFill(c, '#4F46E5', '55', '02'), fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#4F46E5', pointBorderColor: '#0B0F1E', pointBorderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: gridOpt, y: gridOpt } }
});
new Chart(document.getElementById('chartDept'), {
    type: 'bar',
    data: { labels: deptLabels, datasets: [{ label: 'Events', data: deptData, backgroundColor: '#D97706', borderRadius: 8, maxBarThickness: 28 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: gridOpt, y: gridOpt } }
});
new Chart(document.getElementById('chartSolved'), {
    type: 'doughnut',
    data: { labels: ['Solved', 'Unsolved'], datasets: [{ data: [<?= (int)$stats['solved_count'] ?>, <?= (int)$stats['unsolved_count'] ?>], backgroundColor: ['#34D399', '#DC2626'], borderColor: '#FFFFFF', borderWidth: 3, hoverOffset: 8 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { position: 'bottom', ...legendStyle } } }
});
new Chart(document.getElementById('chartCat'), {
    type: 'polarArea',
    data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: ['#4F46E5AA','#D97706AA','#2563EBAA','#34D399AA'], borderColor: '#FFFFFF', borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', ...legendStyle } }, scales: { r: { grid: { color: 'rgba(148,163,184,0.25)' }, ticks: { display: false } } } }
});
</script>
<?php endif; ?>
</body>
</html>
