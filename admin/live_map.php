<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/maps.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency']; // matches insert order in schema.sql

// Fixed colour per category id (1..4), matching the civic palette used
// elsewhere on the dashboard — Illegal Acts=gold, Security=blue,
// Service Delivery=cyan, Accident/Disaster=red (and pulses: fire, traffic,
// and other emergencies need to stand out immediately on the map).
$categoryColors = [
    1 => '#E3B23C', // illegal acts
    2 => '#2E7DAF', // security problem
    3 => '#2FAE76', // service delivery
    4 => '#E2574C', // accident / disaster
];

$activeNav = 'live_map';
$dir = t_raw('dir');

// Labels handed to JS so popups render translated text without another round-trip
$statusLabels = [];
foreach (['new','assigned','ongoing','solved','unsolved'] as $s) { $statusLabels[$s] = t_raw('status_' . $s); }
$priorityLabels = [];
foreach (['low','medium','high','critical'] as $p) { $priorityLabels[$p] = t_raw('severity_' . $p); }
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('live_map_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<?php leaflet_assets(); ?>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('live_map_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('live_map_subtitle') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <form id="mapFilters" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; padding:16px 16px 0;">
            <div style="min-width:200px;">
                <label><?= t('live_map_filter_category') ?></label>
                <select name="category" id="filterCategory">
                    <option value="0"><?= t('dash_filter_all') ?></option>
                    <?php foreach ($categories as $i => $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon']) ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:220px;">
                <label><?= t('live_map_filter_status') ?></label>
                <select name="active_only" id="filterStatus">
                    <option value="1" selected><?= t('live_map_active_only') ?></option>
                    <option value="0"><?= t('live_map_all') ?></option>
                </select>
            </div>
            <div style="padding-bottom:10px;">
                <button type="button" class="btn btn-ghost btn-sm" id="locateMeBtn">📍 <?= t('live_map_locate_me') ?></button>
            </div>
            <div class="live-map-status" style="padding-bottom:10px; margin-left:auto;">
                <span class="dot-live"></span>
                <span id="mapCount">0</span> <?= t('live_map_count') ?>
                &nbsp;·&nbsp; <?= t('live_map_updated') ?>: <span id="mapUpdated">—</span>
                <span id="myLocStatus"></span>
            </div>
        </form>

        <div id="liveIncidentMap" class="map-canvas map-canvas-lg" style="margin-top:12px;"></div>

        <div class="live-map-legend">
            <strong style="color:var(--text);"><?= t('live_map_legend') ?>:</strong>
            <?php foreach ($categories as $i => $c): ?>
                <span><i class="legend-dot" style="background:<?= $categoryColors[$c['id']] ?? '#888' ?>;"></i><?= htmlspecialchars($c['icon']) ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</main>
</div>

<script>
(function(){
    var map = L.map('liveIncidentMap').setView([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], <?= ADAMA_DEFAULT_ZOOM ?>);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var colors = <?= json_encode($categoryColors) ?>;
    var statusLabels = <?= json_encode($statusLabels) ?>;
    var priorityLabels = <?= json_encode($priorityLabels) ?>;
    var canManage = <?= in_array(current_role(), ['administrator','operator','supervisor','department_officer','camera_operator'], true) ? 'true' : 'false' ?>;
    var emptyText = <?= json_encode(t_raw('live_map_empty')) ?>;

    var markersLayer = L.layerGroup().addTo(map);
    var noDataMarker = null;
    var myLoc = null;
    var myLocMarker = null;
    var lastData = null;
    var distanceUnit = <?= json_encode(t_raw('live_map_km_short')) ?>;
    var locatingText = <?= json_encode(t_raw('gps_locating')) ?>;
    var locateLabel = <?= json_encode(t_raw('live_map_locate_me')) ?>;
    var deniedText = <?= json_encode(t_raw('gps_denied')) ?>;
    var unsupportedText = <?= json_encode(t_raw('gps_unsupported')) ?>;

    // Great-circle distance between two lat/lng points, in kilometres.
    function distanceKm(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    var myIcon = L.divIcon({
        className: '',
        html: '<div class="incident-pin you-are-here"><span>📍</span></div>',
        iconSize: [26, 26], iconAnchor: [13, 26], popupAnchor: [0, -24]
    });

    document.getElementById('locateMeBtn').addEventListener('click', function () {
        if (!navigator.geolocation) { alert(unsupportedText); return; }
        var btn = this;
        btn.disabled = true;
        btn.textContent = '📡 ' + locatingText;
        navigator.geolocation.getCurrentPosition(function (pos) {
            myLoc = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            if (myLocMarker) myLocMarker.remove();
            myLocMarker = L.marker([myLoc.lat, myLoc.lng], { icon: myIcon }).addTo(map);
            map.setView([myLoc.lat, myLoc.lng], 14);
            btn.disabled = false;
            btn.textContent = '📍 ' + locateLabel;
            if (lastData) render(lastData);
        }, function () {
            alert(deniedText);
            btn.disabled = false;
            btn.textContent = '📍 ' + locateLabel;
        }, { enableHighAccuracy: true, timeout: 10000 });
    });

    function timeAgo(iso) {
        var then = new Date(iso.replace(' ', 'T'));
        var diffMin = Math.round((Date.now() - then.getTime()) / 60000);
        if (diffMin < 1) return '<1m';
        if (diffMin < 60) return diffMin + 'm';
        var h = Math.floor(diffMin / 60);
        if (h < 24) return h + 'h';
        return Math.floor(h / 24) + 'd';
    }

    function makeIcon(ev) {
        var color = colors[ev.category_id] || '#888';
        var urgent = ev.priority === 'critical' || ev.priority === 'high';
        return L.divIcon({
            className: '',
            html: '<div class="incident-pin' + (urgent ? ' urgent' : '') + '" style="background:' + color + ';"><span>' + (ev.icon || '📍') + '</span></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 26],
            popupAnchor: [0, -24]
        });
    }

    function render(data) {
        lastData = data;
        markersLayer.clearLayers();
        document.getElementById('mapCount').textContent = data.count;
        document.getElementById('mapUpdated').textContent = data.updated;
        var statusEl = document.getElementById('myLocStatus');
        statusEl.textContent = myLoc ? (' · 📍 ' + locateLabel + ' ✓') : '';
        // myLocMarker lives directly on `map` (not markersLayer), so it survives clearLayers() untouched.

        if (!data.events.length) {
            noDataMarker = L.circleMarker([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], { radius: 6, color: '#888' })
                .bindPopup(emptyText).addTo(markersLayer);
            return;
        }

        data.events.forEach(function(ev){
            var distRow = '';
            if (myLoc) {
                var d = distanceKm(myLoc.lat, myLoc.lng, ev.lat, ev.lng);
                var distText = d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' ' + distanceUnit;
                distRow = '<div class="ip-row ip-distance">📏 ' + distText + '</div>';
            }
            var popup = '<div class="incident-popup">'
                + '<div class="ip-title">' + (ev.icon || '') + ' ' + ev.category_name + '</div>'
                + '<div class="ip-row"><strong>' + ev.code + '</strong></div>'
                + '<div class="ip-row">' + (priorityLabels[ev.priority] || ev.priority) + ' · ' + (statusLabels[ev.status] || ev.status) + '</div>'
                + (ev.address ? '<div class="ip-row">' + ev.address + '</div>' : '')
                + (ev.department ? '<div class="ip-row">' + ev.department + '</div>' : '')
                + '<div class="ip-row">' + timeAgo(ev.created_at) + ' ago</div>'
                + distRow
                + (canManage ? '<div class="ip-row"><a href="report.php?id=' + ev.id + '">→</a></div>' : '')
                + '</div>';

            L.marker([ev.lat, ev.lng], { icon: makeIcon(ev) }).bindPopup(popup).addTo(markersLayer);
        });
    }

    function load() {
        var category = document.getElementById('filterCategory').value;
        var activeOnly = document.getElementById('filterStatus').value;
        fetch('api_map_events.php?category=' + encodeURIComponent(category) + '&active_only=' + encodeURIComponent(activeOnly))
            .then(function(r){ return r.json(); })
            .then(render)
            .catch(function(){});
    }

    document.getElementById('filterCategory').addEventListener('change', load);
    document.getElementById('filterStatus').addEventListener('change', load);

    load();
    setInterval(load, 20000);
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
