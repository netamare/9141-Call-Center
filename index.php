<?php
require 'config.php';
require 'includes/lang.php';
require 'includes/security.php';
require 'includes/notifications.php';
require 'includes/maps.php';
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency']; // matches insert order in schema.sql

$wordLimitStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'description_word_limit'");
$wordLimitStmt->execute();
$WORD_LIMIT = (int) ($wordLimitStmt->fetchColumn() ?: 150);

function word_count($text) { return str_word_count(trim($text)); }

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Honeypot: a hidden field real users never fill in; bots usually do.
    if (!empty($_POST['website'])) {
        header('Location: index.php');
        exit;
    }

    $category_id = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $caller_name = trim($_POST['caller_name'] ?? '');
    $caller_phone = normalize_et_phone($_POST['caller_phone'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'unspecified';
    $address = trim($_POST['address'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $latitude = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
    $longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
    $priority = in_array($_POST['severity'] ?? '', ['low','medium','high','critical']) ? $_POST['severity'] : 'medium';

    if ($category_id <= 0 || $description === '' || $location === '' || $caller_phone === '') {
        $error = t_raw('error_required');
    } elseif (!is_valid_et_phone($caller_phone)) {
        $error = t_raw('error_phone_format');
    } elseif (word_count($description) > $WORD_LIMIT) {
        $error = sprintf(t_raw('error_word_limit'), $WORD_LIMIT);
    } else {
        $tracking_code = generate_tracking_code();
        $stmt = $pdo->prepare("INSERT INTO events (tracking_code, category_id, caller_name, caller_phone, gender, address, location, latitude, longitude, description, priority, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')");
        $stmt->execute([$tracking_code, $category_id, $caller_name ?: null, $caller_phone ?: null, $gender, $address ?: null, $location ?: null, $latitude, $longitude, $description, $priority]);
        $event_id = $pdo->lastInsertId();

        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note) VALUES (?, 'created', 'Event submitted via public web form')");
        $log->execute([$event_id]);

        $catName = '';
        foreach ($categories as $c) { if ($c['id'] == $category_id) { $catName = $c['name']; break; } }
        notify_new_event($pdo, $event_id, $priority, $catName, $tracking_code);
        send_sms($pdo, $caller_phone, sprintf(t_raw('sms_confirmation'), $tracking_code), $event_id);

        // Handle uploaded files (image, video, voice/audio, document) - up to MAX_FILES_PER_REPORT
        if (!empty($_FILES['attachments']['name'][0])) {
            $count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < min($count, MAX_FILES_PER_REPORT); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = [
                    'name' => $_FILES['attachments']['name'][$i],
                    'type' => $_FILES['attachments']['type'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'error' => $_FILES['attachments']['error'][$i],
                    'size' => $_FILES['attachments']['size'][$i],
                ];
                save_report_attachment($pdo, $event_id, $file);
            }
        }

        $success = $tracking_code;
    }
}

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
<?php leaflet_assets(); ?>
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="Adama City Administration emblem" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= t('site_title') ?></div>
            <div class="brand-subtitle"><?= t('site_subtitle') ?></div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">

    <?php if ($success): ?>
    <div class="card">
        <div class="alert success">
            <?= t('success_prefix') ?> <strong><?= htmlspecialchars($success) ?></strong>.
            <?= t('success_suffix') ?>
        </div>
        <a class="btn" href="index.php"><?= t('btn_submit_another') ?></a>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2><?= t('home_heading') ?></h2>
        <p><?= t('home_intro') ?></p>

        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                <label>Website</label>
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label><?= t('label_category') ?> <span class="required-star">*</span></label>
            <select name="category_id" required>
                <option value=""><?= t('select_category') ?></option>
                <?php foreach ($categories as $i => $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon'] ?? '') ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><?= t('label_description') ?> <span class="required-star">*</span></label>
            <textarea name="description" id="descField" required placeholder="<?= t('placeholder_description') ?>" oninput="updateWordCount()"></textarea>
            <div class="hint" id="wordCountHint">0 / <?= $WORD_LIMIT ?> words</div>

            <label><?= t('label_location') ?> <span class="required-star">*</span></label>
            <input type="text" name="location" placeholder="<?= t('placeholder_location') ?>" required>

            <label><?= t('label_address') ?></label>
            <input type="text" name="address">

            <?php render_location_picker('reportMap'); ?>

            <label><?= t('label_severity') ?></label>
            <select name="severity">
                <option value="low"><?= t('severity_low') ?></option>
                <option value="medium" selected><?= t('severity_medium') ?></option>
                <option value="high"><?= t('severity_high') ?></option>
                <option value="critical"><?= t('severity_critical') ?></option>
            </select>

            <label><?= t('label_name') ?></label>
            <input type="text" name="caller_name">
<label><?= t('label_phone') ?> <span class="required-star">*</span></label>
<input type="tel" name="caller_phone" id="caller_phone" inputmode="tel" autocomplete="tel"
       placeholder="09xxxxxxxx or 0722157790"
       title="<?= htmlspecialchars(t_raw('phone_format_hint')) ?>"
       required>

            <label><?= t('label_gender') ?></label>
            <select name="gender">
                <option value="unspecified" selected><?= t('gender_unspecified') ?></option>
                <option value="male"><?= t('gender_male') ?></option>
                <option value="female"><?= t('gender_female') ?></option>
            </select>

            <label><?= t('label_attachments') ?></label>
            <input type="file" name="attachments[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx" capture>
            <div class="hint"><?= t('attachments_hint') ?></div>

            <button type="submit"><?= t('btn_submit') ?></button>
        </form>
    </div>

    <div class="card">
        <h2><?= t('track_heading') ?></h2>
        <form method="get" action="track.php">
            <input type="hidden" name="lang" value="<?= $CURRENT_LANG ?>">
            <label><?= t('label_tracking_code') ?></label>
            <input type="text" name="code" placeholder="<?= t('placeholder_tracking_code') ?>" required>
            <button type="submit"><?= t('btn_check') ?></button>
        </form>
    </div>
    <div class="card" style="margin-top:16px;">
        <div class="public-action-btns">
            <a class="btn public-action-btn" href="about.php">ℹ️ <?= t('btn_about_short') ?></a>
            <a class="btn public-action-btn" href="citizen_feedback.php">💬 <?= t('btn_feedback_short') ?></a>
            <a class="btn public-action-btn" href="citizen_help.php">🆘 <?= t('btn_help_short') ?></a>
            <a class="btn public-action-btn public-action-btn--supervisor" href="track.php">📞 <?= t('contact supervisor') ?></a>
        </div>
    </div>

    <div class="card about-card" style="margin-top:30px;">
        <h2 style="font-size:16px;">ℹ️ <?= t('about_title') ?></h2>
        <p class="muted" style="font-size:13.5px;"><?= t('about_body') ?></p>

        <div class="about-stats">
            <?php
                $aboutTotal = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
                $aboutSolved = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status='solved'")->fetchColumn();
                $aboutRate = $aboutTotal > 0 ? round($aboutSolved / $aboutTotal * 100) : 0;
            ?>
            <div class="about-stat"><div class="num"><?= $aboutTotal ?></div><div class="lbl"><?= t('about_stat_total') ?></div></div>
            <div class="about-stat"><div class="num"><?= $aboutRate ?>%</div><div class="lbl"><?= t('about_stat_rate') ?></div></div>
            <div class="about-stat"><div class="num">4</div><div class="lbl"><?= t('about_stat_categories') ?></div></div>
            <div class="about-stat"><div class="num">7</div><div class="lbl"><?= t('about_stat_languages') ?></div></div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-top:14px;">
            <?php foreach ($categories as $i => $c): ?>
                <div style="background:var(--panel-2); border:1px solid var(--border); border-radius:10px; padding:12px; text-align:center; font-size:12.5px;">
                    <div style="font-size:22px;"><?= htmlspecialchars($c['icon'] ?? '') ?></div>
                    <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="about-coverage">
            <div>
                <div style="font-family:var(--display); font-weight:700; margin-bottom:4px;">📍 <?= t('about_coverage_title') ?></div>
                <p class="muted" style="font-size:12.5px; margin:0;"><?= t('about_coverage_body') ?></p>
            </div>
            <div class="map-card" style="margin:0;">
                <div id="aboutMap" class="map-canvas map-canvas-sm"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var m = L.map('aboutMap', { scrollWheelZoom: false, dragging: !L.Browser.mobile, zoomControl: false })
        .setView([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(m);
    L.circleMarker([<?= ADAMA_LAT ?>, <?= ADAMA_LNG ?>], { radius: 9, color: '#22D3EE', fillColor: '#22D3EE', fillOpacity: 0.35, weight: 2 }).addTo(m).bindPopup('Adama');
})();
</script>
<script>
const WORD_LIMIT = <?= $WORD_LIMIT ?>;
function updateWordCount() {
    const text = document.getElementById('descField').value.trim();
    const words = text === '' ? 0 : text.split(/\s+/).length;
    const hint = document.getElementById('wordCountHint');
    hint.textContent = words + ' / ' + WORD_LIMIT + ' words';
    hint.style.color = words > WORD_LIMIT ? 'var(--red)' : '';
}
</script>

<script>
(function () {
  var form = document.querySelector('form[method="post"]');
  var phone = document.getElementById('caller_phone');
  if (!form || !phone) return;
  var etMsg = <?= json_encode(t_raw('phone_format_hint'), JSON_UNESCAPED_UNICODE) ?>;
  function normalizeEt(v) {
    v = (v || '').replace(/[\s\-\(\)]/g, '');
    var m;
    if ((m = v.match(/^\+?251([97]\d{8})$/))) return '0' + m[1];
    if ((m = v.match(/^([97]\d{8})$/))) return '0' + m[1];
    if ((m = v.match(/^0([97]\d{8})$/))) return '0' + m[1];
    return v;
  }
  function isEt(v) {
    return /^0[97]\d{8}$/.test(normalizeEt(v));
  }
  form.addEventListener('submit', function (e) {
    var n = normalizeEt(phone.value);
    if (!isEt(n)) {
      e.preventDefault();
      phone.setCustomValidity(etMsg);
      phone.reportValidity();
      return false;
    }
    phone.setCustomValidity('');
    phone.value = n;
  });
  phone.addEventListener('input', function () { phone.setCustomValidity(''); });
})();
</script>

<footer style="text-align:center; padding:22px 16px; margin-top:50px; background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%); border-top:1px solid #e2e8f0; font-family:system-ui,-apple-system,sans-serif;">
    <div style="font-size:13.5px; font-weight:600; color:#334155; letter-spacing:0.3px;">
        © 2026 MNAN. All Rights Reserved.
    </div>
    <div style="margin-top:6px; font-size:12px; color:#64748b;">
        Designed &amp; Developed by <span style="color:#0ea5e9; font-weight:600;">MNAN</span>
    </div>
    <div style="margin-top:8px; font-size:11px; color:#94a3b8;">
        Adama City Administration · Call Center 9141
    </div>
</footer>
<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>