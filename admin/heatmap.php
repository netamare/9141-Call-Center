<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/maps.php';
require_role(['administrator', 'supervisor']);

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency'];
$filter_category = $_GET['category'] ?? '';

// Geo points for the heat layer - every event with a captured GPS pin,
// weighted a little heavier for higher priority so hot spots reflect
// urgency as well as raw volume.
$geoSql = "SELECT latitude, longitude, priority FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$geoParams = [];
if ($filter_category !== '') { $geoSql .= " AND category_id = ?"; $geoParams[] = $filter_category; }
$geoStmt = $pdo->prepare($geoSql);
$geoStmt->execute($geoParams);
$geoRows = $geoStmt->fetchAll();
$weightMap = ['low' => 0.4, 'medium' => 0.6, 'high' => 0.85, 'critical' => 1.0];
$geoPoints = array_map(function ($r) use ($weightMap) {
    return [(float)$r['latitude'], (float)$r['longitude'], $weightMap[$r['priority']] ?? 0.6];
}, $geoRows);

$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$geoCount = count($geoRows);

// DAYOFWEEK: 1=Sunday .. 7=Saturday - kept from v3/v4 as a second, time-based
// view: useful for staffing decisions even once GPS data is rich.
$rows = $pdo->query("SELECT DAYOFWEEK(created_at) dow, HOUR(created_at) hr, COUNT(*) c FROM events GROUP BY dow, hr")->fetchAll();
$grid = array_fill(1, 7, array_fill(0, 24, 0));
$max = 1;
foreach ($rows as $r) {
    $grid[(int)$r['dow']][(int)$r['hr']] = (int)$r['c'];
    if ($r['c'] > $max) $max = (int)$r['c'];
}
$dayNames = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];

$activeNav = 'heatmap';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('heatmap_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<?php leaflet_assets(); leaflet_heat_asset(); ?>
<style>
.heat-wrap { overflow-x: auto; }
.heat-grid { display: grid; grid-template-columns: 50px repeat(24, 1fr); gap: 3px; min-width: 900px; font-family: var(--mono); font-size: 10px; }
.heat-cell { aspect-ratio: 1; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #06101F; font-weight: 600; }
.heat-label { color: var(--muted); display: flex; align-items: center; justify-content: flex-end; padding-right: 6px; }
.heat-hour-label { color: var(--faint); text-align: center; }
</style>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('heatmap_geo_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('heatmap_geo_subtitle') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <form method="get" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
            <div style="flex:1; min-width:200px;">
                <label><?= t('dash_filter_category') ?></label>
                <select name="category" onchange="this.form.submit()">
                    <option value=""><?= t('dash_filter_all') ?></option>
                    <?php foreach ($categories as $i => $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_category == $c['id'] ? 'selected' : '' ?>><?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="muted" style="font-size:12.5px; padding-bottom:10px;">
                <?= $geoCount ?> / <?= $totalEvents ?> <?= t('heatmap_geo_tagged') ?>
            </div>
        </form>

        <?php if ($geoCount === 0): ?>
            <div class="alert" style="background:rgba(245,185,66,0.1); color:var(--amber); border-color:rgba(245,185,66,0.3);">
                <?= t('heatmap_geo_empty') ?>
            </div>
        <?php endif; ?>

        <div class="map-card" style="margin:0;">
            <div id="geoHeatMap" class="map-canvas map-canvas-lg"></div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('heatmap_time_title') ?></h2>
        <div class="muted" style="font-size:12.5px; margin-bottom:14px;"><?= t('heatmap_subtitle') ?></div>
        <div class="heat-wrap">
            <div class="heat-grid">
                <div></div>
                <?php for ($h = 0; $h < 24; $h++): ?><div class="heat-hour-label"><?= $h ?></div><?php endfor; ?>

                <?php foreach ($dayNames as $dow => $label): ?>
                    <div class="heat-label"><?= $label ?></div>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <?php
                            $count = $grid[$dow][$h];
                            $intensity = $count / $max;
                            $bg = $count === 0 ? 'var(--panel-2)' : 'rgba(34,211,238,' . max(0.15, $intensity) . ')';
                        ?>
                        <div class="heat-cell" style="background:<?= $bg ?>;" title="<?= $label ?> <?= $h ?>:00 - <?= $count ?> events"><?= $count > 0 ? $count : '' ?></div>
                    <?php endfor; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
</div>
<script>
(function(){
    var map = L.map('geoHeatMap').setView([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], <?= ADAMA_DEFAULT_ZOOM ?>);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var points = <?= json_encode($geoPoints) ?>;
    if (points.length) {
        L.heatLayer(points, { radius: 28, blur: 22, maxZoom: 17, gradient: { 0.2: '#4F46E5', 0.5: '#2563EB', 0.8: '#D97706', 1.0: '#DC2626' } }).addTo(map);
    } else {
        L.circleMarker([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], { radius: 8, color: '#4F46E5' }).addTo(map);
    }
})();
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
