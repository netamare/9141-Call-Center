<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'supervisor']);

// SLA targets, for compliance calculation
$sla_rows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'sla_hours_%'")->fetchAll();
$SLA_HOURS = ['critical' => 1, 'high' => 4, 'medium' => 24, 'low' => 72];
foreach ($sla_rows as $row) {
    $SLA_HOURS[str_replace('sla_hours_', '', $row['setting_key'])] = (int) $row['setting_value'];
}

// Daily trend, last 30 days
$trendStmt = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM events
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY d ORDER BY d");
$trendRaw = [];
foreach ($trendStmt->fetchAll() as $r) { $trendRaw[$r['d']] = (int) $r['c']; }
$trendLabels = []; $trendData = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trendLabels[] = date('M j', strtotime($d));
    $trendData[] = $trendRaw[$d] ?? 0;
}

// SLA compliance: of events that reached a final state, how many were resolved within target?
$finalEvents = $pdo->query("SELECT priority, created_at, resolved_at FROM events WHERE status IN ('solved','unsolved') AND resolved_at IS NOT NULL")->fetchAll();
$withinSla = 0; $overSla = 0;
foreach ($finalEvents as $e) {
    $hours = $SLA_HOURS[$e['priority']] ?? 24;
    $mins = (strtotime($e['resolved_at']) - strtotime($e['created_at'])) / 60;
    if ($mins <= $hours * 60) $withinSla++; else $overSla++;
}

// Avg response time by department (minutes)
$deptRespStmt = $pdo->query("SELECT COALESCE(d.name,'Unassigned') name, AVG(e.response_time_minutes) avg_min, COUNT(*) c
    FROM events e LEFT JOIN departments d ON d.id = e.assigned_department_id
    WHERE e.response_time_minutes IS NOT NULL GROUP BY d.id ORDER BY avg_min DESC");
$deptResp = $deptRespStmt->fetchAll();

// Category x priority matrix
$matrixStmt = $pdo->query("SELECT c.name cat, e.priority, COUNT(*) c FROM events e
    JOIN categories c ON c.id = e.category_id GROUP BY c.id, e.priority");
$matrix = [];
$catNames = [];
foreach ($matrixStmt->fetchAll() as $r) {
    $matrix[$r['cat']][$r['priority']] = (int) $r['c'];
    if (!in_array($r['cat'], $catNames, true)) $catNames[] = $r['cat'];
}
$priorities = ['low', 'medium', 'high', 'critical'];

// Top reported locations (free-text field, so grouped by exact string — still useful for hot spots)
$topLocStmt = $pdo->query("SELECT location, COUNT(*) c FROM events WHERE location IS NOT NULL AND location != ''
    GROUP BY location ORDER BY c DESC LIMIT 8");
$topLocations = $topLocStmt->fetchAll();

// Overall satisfaction
$avgRating = $pdo->query("SELECT AVG(satisfaction_rating) FROM events WHERE satisfaction_rating IS NOT NULL")->fetchColumn();
$ratingCount = $pdo->query("SELECT COUNT(*) FROM events WHERE satisfaction_rating IS NOT NULL")->fetchColumn();

// Department performance: volume, solved/unsolved split, solve rate, avg resolution time
$deptPerfStmt = $pdo->query("SELECT COALESCE(d.name,'Unassigned') name,
    COUNT(*) total,
    SUM(e.status='solved') solved,
    SUM(e.status='unsolved') unsolved,
    AVG(CASE WHEN e.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, e.created_at, e.resolved_at) END) avg_resolve_min
    FROM events e LEFT JOIN departments d ON d.id = e.assigned_department_id
    GROUP BY d.id ORDER BY total DESC");
$deptPerf = $deptPerfStmt->fetchAll();

$activeNav = 'analytics';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('analytics_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('analytics_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('analytics_subtitle') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="grid4">
        <div class="stat">
            <div class="num"><?= $withinSla + $overSla > 0 ? round($withinSla / ($withinSla + $overSla) * 100) : 0 ?>%</div>
            <div class="lbl"><?= t('analytics_sla_compliance') ?></div>
        </div>
        <div class="stat">
            <div class="num"><?= $avgRating ? round($avgRating, 1) . ' ★' : '—' ?></div>
            <div class="lbl"><?= t('analytics_avg_satisfaction') ?> (<?= (int)$ratingCount ?>)</div>
        </div>
        <div class="stat">
            <div class="num"><?= array_sum($trendData) ?></div>
            <div class="lbl"><?= t('analytics_last_30_days') ?></div>
        </div>
        <div class="stat">
            <div class="num"><?= count($topLocations) ? htmlspecialchars(mb_strimwidth($topLocations[0]['location'], 0, 18, '…')) : '—' ?></div>
            <div class="lbl"><?= t('analytics_top_location') ?></div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-box" style="grid-column: 1 / -1;">
            <h3><?= t('analytics_trend_title') ?></h3>
            <canvas id="chartTrend" height="90"></canvas>
        </div>
        <div class="chart-box">
            <h3><?= t('analytics_sla_title') ?></h3>
            <canvas id="chartSla" height="180"></canvas>
        </div>
        <div class="chart-box">
            <h3><?= t('analytics_response_by_dept_title') ?></h3>
            <canvas id="chartDeptResp" height="180"></canvas>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('analytics_dept_perf_title') ?></h2>
        <div class="chart-grid" style="margin-top:8px;">
            <div class="chart-box">
                <h3><?= t('analytics_dept_volume_title') ?></h3>
                <canvas id="chartDeptVolume" height="200"></canvas>
            </div>
            <div class="chart-box">
                <h3><?= t('analytics_dept_solverate_title') ?></h3>
                <canvas id="chartDeptSolveRate" height="200"></canvas>
            </div>
        </div>
        <table style="margin-top:6px;">
            <tr>
                <th><?= t('dash_col_department') ?></th>
                <th><?= t('dash_total') ?></th>
                <th><?= t('analytics_col_solved') ?></th>
                <th><?= t('analytics_col_unsolved') ?></th>
                <th><?= t('analytics_col_solve_rate') ?></th>
                <th><?= t('analytics_col_avg_resolution') ?></th>
            </tr>
            <?php foreach ($deptPerf as $d): $rate = $d['total'] ? round($d['solved'] / $d['total'] * 100) : 0; ?>
            <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td class="mono"><?= (int)$d['total'] ?></td>
                <td class="mono"><?= (int)$d['solved'] ?></td>
                <td class="mono"><?= (int)$d['unsolved'] ?></td>
                <td class="mono"><span class="badge <?= $rate >= 70 ? 'solved' : ($rate >= 40 ? 'medium' : 'high') ?>"><?= $rate ?>%</span></td>
                <td class="mono"><?= $d['avg_resolve_min'] ? round($d['avg_resolve_min'] / 60, 1) . 'h' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$deptPerf): ?><tr><td colspan="6"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('analytics_matrix_title') ?></h2>
        <table>
            <tr>
                <th><?= t('dash_col_category') ?></th>
                <?php foreach ($priorities as $p): ?><th><span class="badge <?= $p ?>"><?= t('severity_' . $p) ?></span></th><?php endforeach; ?>
                <th><?= t('dash_total') ?></th>
            </tr>
            <?php foreach ($catNames as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat) ?></td>
                <?php $rowTotal = 0; ?>
                <?php foreach ($priorities as $p): ?>
                    <?php $v = $matrix[$cat][$p] ?? 0; $rowTotal += $v; ?>
                    <td class="mono"><?= $v ?: '—' ?></td>
                <?php endforeach; ?>
                <td class="mono" style="font-weight:700;"><?= $rowTotal ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$catNames): ?><tr><td colspan="6"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('analytics_top_locations_title') ?></h2>
        <table>
            <tr><th><?= t('label_location') ?></th><th><?= t('dash_total') ?></th></tr>
            <?php foreach ($topLocations as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['location']) ?></td>
                <td class="mono"><?= (int)$l['c'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$topLocations): ?><tr><td colspan="2"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
        <div class="hint"><?= t('analytics_top_locations_hint') ?></div>
    </div>
</main>
</div>
<script>
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

new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{ label: 'Events/day', data: <?= json_encode($trendData) ?>, borderColor: '#21C7A8', backgroundColor: (c) => fadeFill(c, '#21C7A8', '4D', '02'), fill: true, tension: 0.4, borderWidth: 3, pointRadius: 0 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { ...gridOpt, ticks: { maxTicksLimit: 10 } }, y: gridOpt } }
});
new Chart(document.getElementById('chartSla'), {
    type: 'doughnut',
    data: { labels: ['<?= addslashes(t_raw('analytics_within_sla')) ?>', '<?= addslashes(t_raw('analytics_over_sla')) ?>'], datasets: [{ data: [<?= $withinSla ?>, <?= $overSla ?>], backgroundColor: ['#34D399', '#DC2626'], borderColor: '#FFFFFF', borderWidth: 3, hoverOffset: 8 }] },
    options: { cutout: '68%', plugins: { legend: { position: 'bottom', ...legendStyle } } }
});
new Chart(document.getElementById('chartDeptResp'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($deptResp, 'name')) ?>,
        datasets: [{ label: 'Avg minutes', data: <?= json_encode(array_map(fn($r) => round($r['avg_min']), $deptResp)) ?>, backgroundColor: '#FFB648', borderRadius: 10, maxBarThickness: 30 }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: gridOpt, y: gridOpt } }
});
new Chart(document.getElementById('chartDeptVolume'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($deptPerf, 'name')) ?>,
        datasets: [
            { label: '<?= addslashes(t_raw('analytics_col_solved')) ?>', data: <?= json_encode(array_map(fn($r) => (int)$r['solved'], $deptPerf)) ?>, backgroundColor: '#34D399', borderRadius: 8, maxBarThickness: 26 },
            { label: '<?= addslashes(t_raw('analytics_col_unsolved')) ?>', data: <?= json_encode(array_map(fn($r) => (int)$r['unsolved'], $deptPerf)) ?>, backgroundColor: '#DC2626', borderRadius: 8, maxBarThickness: 26 },
            { label: 'Pending', data: <?= json_encode(array_map(fn($r) => (int)$r['total'] - (int)$r['solved'] - (int)$r['unsolved'], $deptPerf)) ?>, backgroundColor: '#FFB648', borderRadius: 8, maxBarThickness: 26 }
        ]
    },
    options: { plugins: { legend: { position: 'bottom', ...legendStyle } }, scales: { x: { ...gridOpt, stacked: true }, y: { ...gridOpt, stacked: true } } }
});
new Chart(document.getElementById('chartDeptSolveRate'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($deptPerf, 'name')) ?>,
        datasets: [{ label: '% solved', data: <?= json_encode(array_map(fn($r) => $r['total'] ? round($r['solved'] / $r['total'] * 100) : 0, $deptPerf)) ?>, backgroundColor: '#21C7A8', borderRadius: 10, maxBarThickness: 30 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: gridOpt, y: { ...gridOpt, max: 100, ticks: { callback: v => v + '%' } } } }
});
</script>
<footer style="
    text-align: center;
    padding: 22px 16px;
    margin-top: 50px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-top: 1px solid #e2e8f0;
    font-family: system-ui, -apple-system, sans-serif;
">
    <div style="
        font-size: 13.5px;
        font-weight: 600;
        color: #334155;
        letter-spacing: 0.3px;
    ">
        © 2026 MNAN. All Rights Reserved.
    </div>
    <div style="
        margin-top: 6px;
        font-size: 12px;
        color: #64748b;
    ">
        Designed &amp; Developed by <span style="color:#0ea5e9; font-weight:600;">MNAN</span>
    </div>
    <div style="
        margin-top: 8px;
        font-size: 11px;
        color: #94a3b8;
    ">
        Adama City Administration · Call Center 9141
    </div>
</footer>
</body>
</html>
