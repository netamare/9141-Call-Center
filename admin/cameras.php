<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/ai_detection.php';
require __DIR__ . '/../includes/cameras.php';
// Room Camera: ANY logged-in user may view (live / recorded / Upload Taateewwanii).
// auth.php already requires login — no require_role, so no role is blocked.

$tab = $_GET['tab'] ?? 'live';
if (!in_array($tab, ['live', 'recorded', 'upload'], true)) $tab = 'live';

// Backend Upload bootstrap: folder + .htaccess + event_attachments table
ensure_upload_backend($pdo);

// ---------- Upload new media (System Upload Taateewwan – with auto GPS) ----------
$uploadMessage = null;
$uploadError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_media') {
    verify_csrf();
    $location = trim($_POST['location'] ?? '');
    $latitude = is_numeric($_POST['latitude'] ?? null) ? (float)$_POST['latitude'] : null;
    $longitude = is_numeric($_POST['longitude'] ?? null) ? (float)$_POST['longitude'] : null;
    $category_id = (int)($_POST['category_id'] ?? 4); // default emergency
    $description = trim($_POST['description'] ?? '');
    $fileTypeHint = $_POST['file_type'] ?? 'video';

    if ($location === '' || empty($_FILES['media_file']['name'])) {
        $uploadError = t('cameras_upload_error');
        $tab = 'upload';
    } elseif (!empty($_FILES['media_file']['error']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'Faayilli guddaa dha (php.ini limit).',
            UPLOAD_ERR_FORM_SIZE  => 'Faayilli guddaa dha (form limit).',
            UPLOAD_ERR_PARTIAL    => 'Faayilli guutummaatti hin olkaa\'amne.',
            UPLOAD_ERR_NO_FILE    => 'Faayilli hin filatamne.',
            UPLOAD_ERR_NO_TMP_DIR => 'Tmp directory hin jiru.',
            UPLOAD_ERR_CANT_WRITE => 'Disk irratti barreessuun hin danda\'amne.',
            UPLOAD_ERR_EXTENSION  => 'Extension PHP faayila dhowwe.',
        ];
        $code = (int)$_FILES['media_file']['error'];
        $uploadError = t('cameras_upload_error') . ' ' . ($errMap[$code] ?? ('Error #' . $code));
        $tab = 'upload';
    } else {
        // Create a new event so it appears in Recorded + Live Map + folder uploads/
        $tracking_code = 'CAM-' . strtoupper(bin2hex(random_bytes(4)));
        $userName = current_user()['name'] ?? 'Camera Operator';
        $stmt = $pdo->prepare("INSERT INTO events (tracking_code, category_id, caller_name, caller_phone, address, location, latitude, longitude, description, priority, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'high', 'new', NOW())");
        $stmt->execute([
            $tracking_code,
            $category_id > 0 ? $category_id : 4,
            $userName,
            null,
            $location,
            $location,
            $latitude,
            $longitude,
            $description !== '' ? $description : ('Camera Room upload – ' . $fileTypeHint . ' – ' . $userName)
        ]);
        $eventId = (int)$pdo->lastInsertId();

        if ($eventId && !empty($_FILES['media_file'])) {
            $saveResult = save_report_attachment($pdo, $eventId, $_FILES['media_file']);
            if ($saveResult === true) {
                $uploadMessage = t('cameras_upload_success') . ' (' . $tracking_code . ')';
                $tab = 'recorded';
            } else {
                // Backend returned a detailed error string
                $uploadError = t('cameras_upload_error') . ' ' . (is_string($saveResult) ? $saveResult : '');
                $tab = 'upload';
            }
        } else {
            $uploadError = t('cameras_upload_error');
            $tab = 'upload';
        }
    }
}

// ---------- AI analysis (recorded tab) ----------
$aiMessage = null;
$aiError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_ai') {
    verify_csrf();
    $attId = (int)($_POST['attachment_id'] ?? 0);
    $eventId = (int)($_POST['event_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT a.*, e.location, e.category_id FROM event_attachments a JOIN events e ON e.id = a.event_id WHERE a.id = ? AND a.event_id = ?");
    $stmt->execute([$attId, $eventId]);
    $att = $stmt->fetch();
    if ($att) {
        $absPath = realpath(__DIR__ . '/../' . $att['file_path']);
        if ($absPath && is_file($absPath)) {
            $hintMap = [1 => 'illegal', 2 => 'security', 3 => 'service', 4 => 'emergency'];
            $hint = $hintMap[(int)$att['category_id']] ?? '';
            $result = ai_run_detection($absPath, $att['location'] ?? '', $hint);
            if (!empty($result['ok'])) {
                ai_save_detections($pdo, $eventId, $attId, $result);
                $aiMessage = $result['summary'] ?? t('cameras_ai_done');
            } else {
                $aiError = $result['error'] ?? 'AI detection failed';
            }
        } else {
            $aiError = 'File not found on disk';
        }
    } else {
        $aiError = 'Attachment not found';
    }
    $tab = 'recorded';
}

// ---------- Live cameras ----------
cameras_ensure_table($pdo);
$liveCams = cameras_list($pdo, true);

// ---------- Recorded media filters ----------
$categories = $pdo->query("SELECT id, name, icon FROM categories ORDER BY id")->fetchAll();
$catFilter = $_GET['category'] ?? '';
$dateFrom  = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo    = $_GET['date_to'] ?? date('Y-m-d');
$location  = trim($_GET['location'] ?? '');
$fileType  = $_GET['type'] ?? 'all';

$media = [];
$aiByEvent = [];
if ($tab === 'recorded') {
    $sql = "SELECT a.*, e.tracking_code, e.location, e.address, e.created_at, e.priority, e.status,
                   c.name AS category_name, c.icon AS category_icon
            FROM event_attachments a
            JOIN events e ON e.id = a.event_id
            LEFT JOIN categories c ON c.id = e.category_id
            WHERE a.file_type IN ('video', 'image')
              AND DATE(e.created_at) BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];
    if ($fileType === 'video') { $sql .= " AND a.file_type = 'video'"; }
    elseif ($fileType === 'image') { $sql .= " AND a.file_type = 'image'"; }
    if ($catFilter !== '') { $sql .= " AND e.category_id = ?"; $params[] = (int)$catFilter; }
    if ($location !== '') {
        $sql .= " AND (e.location LIKE ? OR e.address LIKE ?)";
        $params[] = '%' . $location . '%'; $params[] = '%' . $location . '%';
    }
    $sql .= " ORDER BY e.created_at DESC LIMIT 120";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $media = $stmt->fetchAll();

    $eventIds = array_unique(array_column($media, 'event_id'));
    if ($eventIds) {
        try {
            $in = implode(',', array_fill(0, count($eventIds), '?'));
            $st = $pdo->prepare("SELECT * FROM ai_detections WHERE event_id IN ($in) ORDER BY created_at DESC");
            $st->execute(array_values($eventIds));
            foreach ($st->fetchAll() as $row) {
                if (!isset($aiByEvent[$row['event_id']])) $aiByEvent[$row['event_id']] = $row;
            }
        } catch (PDOException $e) {}
    }
}

$activeNav = 'cameras';
$dir = t_raw('dir');
$isAdmin = current_role() === 'administrator';
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('nav_cameras') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
.tabs { display:flex; gap:8px; margin-bottom:18px; }
.tabs a { padding:8px 16px; border-radius:10px; border:1px solid var(--border); color:var(--muted); text-decoration:none; font-size:14px; }
.tabs a.active { background:var(--cyan); color:#fff; border-color:var(--cyan); }
.camera-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.camera-card { background: var(--panel-solid); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; display:flex; flex-direction:column; }
.camera-card video, .camera-card img, .camera-card .video-placeholder {
    width: 100%; height: 200px; object-fit: cover; background: #000; display: block;
}
.video-placeholder { display:flex; align-items:center; justify-content:center; color: var(--muted); font-size:13px; flex-direction:column; gap:6px; }
.camera-meta { padding: 12px 14px; flex:1; display:flex; flex-direction:column; }
.camera-meta .code { font-family: var(--mono); font-size: 12px; color: var(--cyan); }
.camera-meta .loc { font-size: 13px; margin-top: 4px; color: var(--text); }
.camera-meta .time { font-size: 12px; color: var(--muted); margin-top: 2px; }
.live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444; margin-right:6px; animation: pulse 1.2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.ai-box { margin-top:10px; padding:8px 10px; border-radius:8px; background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.2); font-size:12px; }
.ai-box .sum { font-weight:600; color:var(--cyan); margin-bottom:4px; }
.ai-box .det { color:var(--muted); }
.ai-badge { display:inline-block; margin-top:6px; padding:2px 8px; border-radius:6px; font-size:11px; background:rgba(59,130,246,0.15); color:var(--cyan); }
.filter-row { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:18px; align-items:flex-end; }
.filter-row label { display:block; font-size:12px; color:var(--muted); margin-bottom:4px; }
.filter-row input, .filter-row select { padding:8px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text); }
.cat-pills { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.cat-pill { padding:6px 14px; border-radius:20px; border:1px solid var(--border); font-size:13px; color:var(--muted); text-decoration:none; }
.cat-pill.active, .cat-pill:hover { background:var(--cyan); color:#fff; border-color:var(--cyan); }
.btn-ai { background:var(--cyan); color:#fff; border:none; padding:6px 12px; border-radius:8px; font-size:12px; cursor:pointer; margin-top:8px; }
.btn-ai:hover { opacity:0.9; }
.alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; }
.alert.ok { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:var(--green); }
.alert.err { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); color:var(--red); }
.stream-error { color:#f87171; font-size:12px; margin-top:6px; }
</style>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:16px;">
        <h2 style="margin:0;"><?= t('nav_cameras') ?></h2>
        <div class="topbar-controls">
            <?php if ($isAdmin): ?>
                <a href="cameras_manage.php" class="btn" style="padding:6px 12px;font-size:13px;background:var(--panel-2);margin-right:8px;"><?= t('cameras_manage_title') ?></a>
            <?php endif; ?>
            <?php render_topbar_controls(); render_lang_switcher(); ?>
        </div>
    </div>

    <div class="tabs">
        <a href="?tab=live" class="<?= $tab==='live'?'active':'' ?>"><span class="live-dot"></span><?= t('cameras_tab_live') ?></a>
        <a href="?tab=recorded" class="<?= $tab==='recorded'?'active':'' ?>"><?= t('cameras_tab_recorded') ?></a>
        <a href="?tab=upload" class="<?= $tab==='upload'?'active':'' ?>"><?= t('cameras_tab_upload') ?></a>
    </div>

    <?php if ($aiMessage): ?><div class="alert ok"><?= htmlspecialchars($aiMessage) ?></div><?php endif; ?>
    <?php if ($aiError): ?><div class="alert err"><?= htmlspecialchars($aiError) ?></div><?php endif; ?>
    <?php if ($uploadMessage): ?><div class="alert ok"><?= htmlspecialchars($uploadMessage) ?></div><?php endif; ?>
    <?php if ($uploadError): ?><div class="alert err"><?= htmlspecialchars($uploadError) ?></div><?php endif; ?>

    <?php if ($tab === 'upload'): ?>
        <!-- ========== UPLOAD NEW MEDIA WITH AUTO GPS ========== -->
        <div class="card">
            <h2 style="margin-top:0;"><?= t('cameras_upload_title') ?></h2>
            <p style="color:var(--muted); font-size:14px; margin-bottom:20px;"><?= t('cameras_upload_intro') ?></p>
            <form method="post" enctype="multipart/form-data" id="uploadMediaForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="upload_media">
                <input type="hidden" name="latitude" id="upLat" value="">
                <input type="hidden" name="longitude" id="upLng" value="">

                <label><?= t('cameras_upload_file') ?> <span class="required-star">*</span></label>
                <input type="file" name="media_file" accept="video/*,image/*,.mp4,.mov,.webm,.mkv,.avi,.m4v,.jpg,.jpeg,.png,.gif,.webp" required>
                <p style="font-size:12px; color:var(--muted); margin:6px 0 14px;">
                    Viidiyoo: hanga 50 GB · Suuraa: hanga 100 MB · Faayilli folder <code>uploads/</code> keessatti olkaa’ama
                </p>

                <label><?= t('cameras_upload_type') ?></label>
                <select name="file_type">
                    <option value="video">Video</option>
                    <option value="image">Image / Suuraa</option>
                </select>

                <label><?= t('cameras_upload_category') ?></label>
                <select name="category_id">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===4 ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label><?= t('cameras_upload_location') ?> <span class="required-star">*</span></label>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <input type="text" name="location" id="upLocation" placeholder="Bakka / Location" required style="flex:1; min-width:200px;">
                    <button type="button" class="btn" id="btnGps" style="margin-top:0; white-space:nowrap;"><?= t('cameras_upload_gps') ?></button>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:10px; align-items:flex-end;">
                    <div>
                        <label style="margin:0 0 4px; font-size:11px;"><?= t('cameras_gps_accuracy') ?></label>
                        <select id="gpsAccuracyMode" style="padding:7px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text); font-size:13px;">
                            <option value="high" selected><?= t('cameras_gps_high') ?></option>
                            <option value="balanced"><?= t('cameras_gps_balanced') ?></option>
                            <option value="low"><?= t('cameras_gps_low') ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="margin:0 0 4px; font-size:11px;"><?= t('cameras_gps_timeout') ?></label>
                        <select id="gpsTimeout" style="padding:7px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text); font-size:13px;">
                            <option value="8000">8s</option>
                            <option value="15000" selected>15s</option>
                            <option value="25000">25s</option>
                            <option value="40000">40s</option>
                        </select>
                    </div>
                    <div>
                        <label style="margin:0 0 4px; font-size:11px;"><?= t('cameras_gps_maxage') ?></label>
                        <select id="gpsMaxAge" style="padding:7px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text); font-size:13px;">
                            <option value="0" selected><?= t('cameras_gps_fresh') ?></option>
                            <option value="30000">30s</option>
                            <option value="60000">1 min</option>
                            <option value="300000">5 min</option>
                        </select>
                    </div>
                </div>
                <div id="gpsStatus" style="font-size:12px; color:var(--muted); margin-top:8px;"></div>
                <div id="gpsAccuracyInfo" style="font-size:11px; color:var(--faint); margin-top:4px; display:none;"></div>

                <label><?= t('cameras_upload_desc') ?></label>
                <textarea name="description" rows="3" placeholder="Ibsa..."></textarea>

                <button type="submit" class="btn" style="margin-top:18px;"><?= t('cameras_upload_btn') ?></button>
            </form>
        </div>
        <script>
        (function(){
            const btn = document.getElementById('btnGps');
            const status = document.getElementById('gpsStatus');
            const accInfo = document.getElementById('gpsAccuracyInfo');
            const latEl = document.getElementById('upLat');
            const lngEl = document.getElementById('upLng');
            const locEl = document.getElementById('upLocation');
            const modeEl = document.getElementById('gpsAccuracyMode');
            const timeoutEl = document.getElementById('gpsTimeout');
            const maxAgeEl = document.getElementById('gpsMaxAge');
            if (!btn) return;

            function getGeoOptions() {
                const mode = modeEl ? modeEl.value : 'high';
                const timeout = timeoutEl ? parseInt(timeoutEl.value, 10) : 15000;
                const maximumAge = maxAgeEl ? parseInt(maxAgeEl.value, 10) : 0;
                return {
                    enableHighAccuracy: mode === 'high',
                    timeout: timeout,
                    maximumAge: maximumAge
                };
            }

            function showAccuracy(meters) {
                if (!accInfo) return;
                accInfo.style.display = 'block';
                let level = '<?= t_raw('cameras_gps_acc_unknown') ?>';
                let color = 'var(--faint)';
                if (meters <= 20) { level = '<?= t_raw('cameras_gps_acc_excellent') ?>'; color = 'var(--green)'; }
                else if (meters <= 50) { level = '<?= t_raw('cameras_gps_acc_good') ?>'; color = 'var(--cyan)'; }
                else if (meters <= 150) { level = '<?= t_raw('cameras_gps_acc_fair') ?>'; color = 'var(--amber)'; }
                else { level = '<?= t_raw('cameras_gps_acc_poor') ?>'; color = 'var(--red)'; }
                accInfo.innerHTML = 'Accuracy: <strong style="color:' + color + '">±' + Math.round(meters) + ' m</strong> (' + level + ')';
            }

            btn.addEventListener('click', function(){
                status.textContent = '…';
                status.style.color = 'var(--muted)';
                if (accInfo) accInfo.style.display = 'none';
                if (!navigator.geolocation) {
                    status.textContent = <?= json_encode(t_raw('cameras_upload_gps_fail')) ?>;
                    status.style.color = 'var(--red)';
                    return;
                }
                const opts = getGeoOptions();
                navigator.geolocation.getCurrentPosition(function(pos){
                    latEl.value = pos.coords.latitude.toFixed(6);
                    lngEl.value = pos.coords.longitude.toFixed(6);
                    const acc = pos.coords.accuracy || 0;
                    status.textContent = <?= json_encode(t_raw('cameras_upload_gps_ok')) ?> + ' (' + latEl.value + ', ' + lngEl.value + ')';
                    status.style.color = 'var(--green)';
                    showAccuracy(acc);
                    // Reverse geocode (Nominatim – free, no key)
                    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + latEl.value + '&lon=' + lngEl.value + '&zoom=16&addressdetails=1', {
                        headers: { 'Accept-Language': 'om,en' }
                    }).then(r => r.json()).then(data => {
                        if (data && data.display_name && !locEl.value) {
                            locEl.value = data.display_name.split(',').slice(0,3).join(',').trim();
                        }
                    }).catch(()=>{});
                }, function(err){
                    let msg = <?= json_encode(t_raw('cameras_upload_gps_fail')) ?>;
                    if (err && err.code === 1) msg += ' (Permission denied)';
                    else if (err && err.code === 2) msg += ' (Position unavailable)';
                    else if (err && err.code === 3) msg += ' (Timeout – try higher timeout or balanced mode)';
                    status.textContent = msg;
                    status.style.color = 'var(--red)';
                }, opts);
            });

            // Auto-try GPS on load with current settings
            setTimeout(function(){ btn.click(); }, 700);
        })();
        </script>

    <?php elseif ($tab === 'live'): ?>
        <!-- ========== LIVE STREAMS ========== -->
        <div class="card" style="margin-bottom:18px;">
            <p style="margin:0; color:var(--muted); font-size:14px;">
                <?= t('cameras_live_intro') ?>
            </p>
        </div>

        <div class="camera-grid" id="liveGrid">
            <?php if (!$liveCams): ?>
                <div class="card" style="grid-column:1/-1; text-align:center; padding:40px; color:var(--muted);">
                    <?= t('cameras_no_live') ?>
                    <?php if ($isAdmin): ?>
                        <div style="margin-top:12px;"><a href="cameras_manage.php" class="btn" style="background:var(--cyan);color:#fff;"><?= t('cameras_add') ?></a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($liveCams as $cam): ?>
            <div class="camera-card" data-cam-id="<?= (int)$cam['id'] ?>" data-type="<?= htmlspecialchars($cam['stream_type']) ?>" data-url="<?= htmlspecialchars($cam['stream_url']) ?>">
                <div class="player-wrap">
                    <video id="vid-<?= (int)$cam['id'] ?>" controls muted playsinline autoplay
                           style="width:100%;height:200px;background:#000;object-fit:contain;"></video>
                    <div class="video-placeholder" id="ph-<?= (int)$cam['id'] ?>" style="display:none;height:200px;">
                        <span>Connecting…</span>
                    </div>
                </div>
                <div class="camera-meta">
                    <div class="code"><span class="live-dot"></span>LIVE · <?= strtoupper(htmlspecialchars($cam['stream_type'])) ?></div>
                    <div class="loc"><?= htmlspecialchars($cam['name']) ?></div>
                    <div class="time"><?= htmlspecialchars($cam['location'] ?? '-') ?></div>
                    <div class="stream-error" id="err-<?= (int)$cam['id'] ?>"></div>
                    <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                        <select id="dur-<?= (int)$cam['id'] ?>" style="padding:6px 8px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);color:var(--text);font-size:12px;">
                            <option value="60">1 min</option>
                            <option value="180">3 min</option>
                            <option value="300" selected>5 min</option>
                            <option value="600">10 min</option>
                            <option value="1800">30 min</option>
                            <option value="3600">60 min</option>
                        </select>
                        <button type="button" class="btn-ai btn-record" data-cam="<?= (int)$cam['id'] ?>" data-loc="<?= htmlspecialchars($cam['location'] ?? $cam['name']) ?>">
                            <?= t('cameras_record') ?>
                        </button>
                    </div>
                    <div id="rec-status-<?= (int)$cam['id'] ?>" style="font-size:12px;margin-top:6px;color:var(--muted);"></div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Local webcam test card -->
            <div class="camera-card">
                <video id="localCam" autoplay muted playsinline style="width:100%;height:200px;background:#000;object-fit:cover;"></video>
                <div class="camera-meta">
                    <div class="code"><span class="live-dot"></span>LOCAL WEBCAM</div>
                    <div class="loc"><?= t('cameras_local_test') ?></div>
                    <button type="button" class="btn-ai" id="btnLocalCam" style="margin-top:10px;"><?= t('cameras_start_local') ?></button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            // HLS / HTTP / MJPEG players
            document.querySelectorAll('.camera-card[data-cam-id]').forEach(function(card) {
                var id = card.dataset.camId;
                var type = card.dataset.type;
                var url = card.dataset.url;
                var video = document.getElementById('vid-' + id);
                var errEl = document.getElementById('err-' + id);
                if (!video || !url) return;

                function showErr(msg) {
                    if (errEl) errEl.textContent = msg || 'Stream error';
                }

                if (type === 'hls' || url.indexOf('.m3u8') !== -1) {
                    if (Hls.isSupported()) {
                        var hls = new Hls({ enableWorker: true, lowLatencyMode: true });
                        hls.loadSource(url);
                        hls.attachMedia(video);
                        hls.on(Hls.Events.ERROR, function(e, data) {
                            if (data.fatal) showErr('HLS error: ' + (data.type || 'unknown'));
                        });
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = url;
                    } else {
                        showErr('HLS not supported in this browser');
                    }
                } else if (type === 'http' || type === 'mjpeg') {
                    video.src = url;
                    video.addEventListener('error', function() { showErr('HTTP stream failed'); });
                } else if (type === 'rtsp') {
                    showErr('RTSP needs restream to HLS (use MediaMTX or ffmpeg). Configure HLS URL instead.');
                } else if (type === 'webrtc') {
                    showErr('WebRTC requires external SFU – use HLS for now.');
                } else {
                    video.src = url;
                }
            });

            // Local webcam
            var btn = document.getElementById('btnLocalCam');
            var localVideo = document.getElementById('localCam');
            if (btn && localVideo) {
                btn.addEventListener('click', function() {
                    if (localVideo.srcObject) {
                        localVideo.srcObject.getTracks().forEach(t => t.stop());
                        localVideo.srcObject = null;
                        btn.textContent = <?= json_encode(t_raw('cameras_start_local')) ?>;
                        return;
                    }
                    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                        .then(function(stream) {
                            localVideo.srcObject = stream;
                            btn.textContent = <?= json_encode(t_raw('cameras_stop_local')) ?>;
                        })
                        .catch(function(e) {
                            alert('Webcam: ' + e.message);
                        });
                });
            }

            // Real-time record from live stream (location saved with event)
            var csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
            document.querySelectorAll('.btn-record').forEach(function(b) {
                b.addEventListener('click', function() {
                    var camId = b.dataset.cam;
                    var loc = b.dataset.loc || '';
                    var durEl = document.getElementById('dur-' + camId);
                    var duration = durEl ? parseInt(durEl.value, 10) : 300;
                    var statusEl = document.getElementById('rec-status-' + camId);
                    b.disabled = true;
                    if (statusEl) statusEl.textContent = <?= json_encode(t_raw('cameras_recording')) ?> + '…';

                    var fd = new FormData();
                    fd.append('action', 'record_start');
                    fd.append('camera_id', camId);
                    fd.append('duration', duration);
                    fd.append('location', loc);
                    fd.append('csrf_token', csrfToken);

                    fetch('api_cameras.php', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data.ok) {
                                if (statusEl) statusEl.textContent = data.error || 'Error';
                                b.disabled = false;
                                return;
                            }
                            if (statusEl) {
                                statusEl.textContent = data.message + ' (#' + data.recording_id + ')';
                            }
                            // Poll until done
                            var rid = data.recording_id;
                            var tries = 0;
                            var maxTries = Math.ceil(duration / 5) + 12;
                            var timer = setInterval(function() {
                                tries++;
                                fetch('api_cameras.php?action=record_status&recording_id=' + rid)
                                    .then(function(r) { return r.json(); })
                                    .then(function(st) {
                                        if (st.status === 'done') {
                                            clearInterval(timer);
                                            if (statusEl) statusEl.textContent = <?= json_encode(t_raw('cameras_record_done')) ?> + ' → ' + (st.file_path || '');
                                            b.disabled = false;
                                        } else if (st.status === 'failed' || !st.ok) {
                                            clearInterval(timer);
                                            if (statusEl) statusEl.textContent = st.error || 'Failed';
                                            b.disabled = false;
                                        } else if (tries >= maxTries) {
                                            clearInterval(timer);
                                            if (statusEl) statusEl.textContent = <?= json_encode(t_raw('cameras_record_timeout')) ?>;
                                            b.disabled = false;
                                        }
                                    }).catch(function() {});
                            }, 5000);
                        })
                        .catch(function(e) {
                            if (statusEl) statusEl.textContent = e.message;
                            b.disabled = false;
                        });
                });
            });

            // Refresh camera list every 30s via API
            setInterval(function() {
                fetch('api_cameras.php?action=list')
                    .then(r => r.json())
                    .then(function(data) {
                        if (!data.ok) return;
                        // Could dynamically add/remove cards; for simplicity just log
                        console.log('Active cameras:', data.count);
                    }).catch(function(){});
            }, 30000);
        })();
        </script>

    <?php else: ?>
        <!-- ========== RECORDED MEDIA + AI ========== -->
        <div class="card" style="margin-bottom:18px;">
            <p style="margin:0 0 12px; color:var(--muted); font-size:14px;"><?= t('cameras_intro') ?></p>
            <div class="cat-pills">
                <a class="cat-pill <?= $catFilter===''?'active':'' ?>" href="?tab=recorded&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&location=<?= urlencode($location) ?>&type=<?= urlencode($fileType) ?>"><?= t('dash_filter_all') ?></a>
                <?php foreach ($categories as $c): ?>
                    <a class="cat-pill <?= $catFilter==$c['id']?'active':'' ?>"
                       href="?tab=recorded&category=<?= $c['id'] ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&location=<?= urlencode($location) ?>&type=<?= urlencode($fileType) ?>">
                        <?= htmlspecialchars($c['icon'] ?? '') ?> <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <form method="get" class="filter-row">
                <input type="hidden" name="tab" value="recorded">
                <?php if ($catFilter !== ''): ?><input type="hidden" name="category" value="<?= (int)$catFilter ?>"><?php endif; ?>
                <div><label><?= t('cameras_date_from') ?></label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                <div><label><?= t('cameras_date_to') ?></label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
                <div><label><?= t('label_location') ?></label><input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="<?= t('placeholder_location') ?>"></div>
                <div>
                    <label><?= t('cameras_type') ?></label>
                    <select name="type">
                        <option value="all" <?= $fileType==='all'?'selected':'' ?>><?= t('dash_filter_all') ?></option>
                        <option value="video" <?= $fileType==='video'?'selected':'' ?>>Video</option>
                        <option value="image" <?= $fileType==='image'?'selected':'' ?>>Image</option>
                    </select>
                </div>
                <div><button type="submit" class="btn" style="padding:9px 16px;"><?= t('dash_filter_btn') ?></button></div>
            </form>
        </div>

        <div class="camera-grid">
            <?php if (!$media): ?>
                <div class="card" style="grid-column:1/-1; text-align:center; padding:40px; color:var(--muted);"><?= t('cameras_no_videos') ?></div>
            <?php endif; ?>
            <?php foreach ($media as $v):
                $ai = $aiByEvent[$v['event_id']] ?? null;
                $dets = $ai ? (json_decode($ai['detections_json'] ?? '[]', true) ?: []) : [];
            ?>
            <div class="camera-card">
                <?php if ($v['file_type'] === 'video'): ?>
                    <?php if (file_exists(__DIR__ . '/../' . $v['file_path'])): ?>
                        <video controls preload="metadata" src="../<?= htmlspecialchars($v['file_path']) ?>"></video>
                    <?php else: ?><div class="video-placeholder">Video</div><?php endif; ?>
                <?php else: ?>
                    <?php if (file_exists(__DIR__ . '/../' . $v['file_path'])): ?>
                        <img src="../<?= htmlspecialchars($v['file_path']) ?>" alt="">
                    <?php else: ?><div class="video-placeholder">Image</div><?php endif; ?>
                <?php endif; ?>
                <div class="camera-meta">
                    <div class="code"><?= htmlspecialchars($v['tracking_code']) ?> · <?= strtoupper($v['file_type']) ?></div>
                    <div class="loc"><?= htmlspecialchars($v['category_icon'] ?? '') ?> <?= htmlspecialchars($v['category_name'] ?? '') ?> · <?= htmlspecialchars($v['location'] ?? '-') ?></div>
                    <div class="time"><?= htmlspecialchars($v['created_at']) ?></div>
                    <?php if ($ai): ?>
                        <div class="ai-box">
                            <div class="sum">AI: <?= htmlspecialchars($ai['summary']) ?></div>
                            <?php if ($dets): ?>
                                <div class="det">
                                    <?php foreach (array_slice($dets, 0, 4) as $d): ?>
                                        <?= htmlspecialchars($d['label']) ?> (<?= round(($d['confidence']??0)*100) ?>%) · <?= ai_category_label($d['category'] ?? '') ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="ai-badge"><?= t('cameras_ai_ready') ?></span>
                    <?php endif; ?>
                    <form method="post" style="margin-top:auto;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="run_ai">
                        <input type="hidden" name="attachment_id" value="<?= (int)$v['id'] ?>">
                        <input type="hidden" name="event_id" value="<?= (int)$v['event_id'] ?>">
                        <button type="submit" class="btn-ai"><?= $ai ? t('cameras_ai_rerun') : t('cameras_ai_run') ?></button>
                        <a href="report.php?id=<?= (int)$v['event_id'] ?>" style="font-size:12px; margin-left:10px;"><?= t('dash_col_manage') ?> →</a>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</div>
</body>
</html>
