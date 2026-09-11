<?php
/**
 * Call Center Operator — New Event
 * Methods: Manual | Voice | Live Video
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/notifications.php';
require __DIR__ . '/../includes/maps.php';
require __DIR__ . '/../includes/call-center-handlers.php';
require_role(['administrator', 'operator']);

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency'];
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$wordLimitStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'description_word_limit'");
$wordLimitStmt->execute();
$WORD_LIMIT = (int) ($wordLimitStmt->fetchColumn() ?: 150);

$success = null;
$error = null;
$new_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $registration_method = in_array($_POST['registration_method'] ?? 'manual', ['manual', 'voice', 'live_stream'], true)
        ? $_POST['registration_method'] : 'manual';

    $category_id = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $caller_name = trim($_POST['caller_name'] ?? '');
    $caller_phone = normalize_et_phone($_POST['caller_phone'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'unspecified';
    $address_area = trim($_POST['address_area'] ?? '');
    $address_details = trim($_POST['address'] ?? '');
    $address = trim(implode(', ', array_filter([$address_area, $address_details])));
    $location = trim($_POST['location'] ?? '');
    $latitude = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
    $longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
    $priority = in_array($_POST['priority'] ?? '', ['low','medium','high','critical']) ? $_POST['priority'] : 'medium';
    $dept_id = (int) ($_POST['department_id'] ?? 0) ?: null;
    $voice_file = null;
    $video_file = null;

    if ($registration_method === 'voice') {
        $voice_b64 = $_POST['voice_file'] ?? '';
        if ($voice_b64 !== '') {
            $voice_file = handle_voice_base64($voice_b64, uniqid('op_', true));
            if (!$voice_file) {
                $error = t_raw('error_voice_upload') ?: 'Voice upload failed';
            }
        } else {
            $error = t_raw('error_voice_required') ?: 'Please record voice first';
        }
    } elseif ($registration_method === 'live_stream') {
        $video_b64 = $_POST['video_file'] ?? '';
        if ($video_b64 !== '') {
            $video_file = handle_video_base64($video_b64, uniqid('op_', true));
            if (!$video_file) {
                $error = t_raw('error_video_upload') ?: 'Video upload failed';
            }
        } else {
            $error = t_raw('error_video_required') ?: 'Please record video first';
        }
    }

    if (!$error && $category_id <= 0) {
        $error = t_raw('error_required_category') ?: 'Please select a category';
    } elseif (!$error && $registration_method === 'manual' && $description === '') {
        $error = t_raw('error_required_description') ?: t_raw('error_required');
    } elseif (!$error && $caller_phone !== '' && !is_valid_et_phone($caller_phone)) {
        $error = t_raw('error_phone_format');
    } elseif (!$error && $registration_method === 'manual' && str_word_count($description) > $WORD_LIMIT) {
        $error = sprintf(t_raw('error_word_limit'), $WORD_LIMIT);
    } else {
        // Do NOT replace media with text-only labels. Keep a short note;
        // the actual voice/video file is saved and shown to departments.
        if ($description === '' && $registration_method === 'voice') {
            $description = 'Operator voice recording (play the audio attachment below).';
        } elseif ($description === '' && $registration_method === 'live_stream') {
            $description = 'Operator live video recording (play the video attachment below).';
        }

        $tracking_code = generate_tracking_code();
        $status = $dept_id ? 'assigned' : 'new';

        try {
            $stmt = $pdo->prepare("INSERT INTO events (tracking_code, category_id, caller_name, caller_phone, gender, address, location, latitude, longitude, description, priority, status, assigned_department_id, operator_id, registration_method, voice_file, video_file)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tracking_code, $category_id, $caller_name ?: null, $caller_phone ?: null, $gender, $address ?: null, $location ?: null, $latitude, $longitude, $description, $priority, $status, $dept_id, $_SESSION['user_id'], $registration_method, $voice_file, $video_file]);
        } catch (PDOException $ex) {
            // Fallback if media columns not yet added
            $stmt = $pdo->prepare("INSERT INTO events (tracking_code, category_id, caller_name, caller_phone, gender, address, location, latitude, longitude, description, priority, status, assigned_department_id, operator_id)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tracking_code, $category_id, $caller_name ?: null, $caller_phone ?: null, $gender, $address ?: null, $location ?: null, $latitude, $longitude, $description, $priority, $status, $dept_id, $_SESSION['user_id']]);
        }
        $new_id = $pdo->lastInsertId();

        // Also store voice/video in event_attachments so every department
        // sees a playable player (not only the text description).
        try {
            if ($voice_file) {
                $path = 'uploads/voice/' . $voice_file;
                $full = __DIR__ . '/../' . $path;
                $size = is_file($full) ? filesize($full) : 0;
                $pdo->prepare("INSERT INTO event_attachments (event_id, file_path, original_name, file_type, file_size) VALUES (?, ?, ?, 'audio', ?)")
                    ->execute([$new_id, $path, $voice_file, $size]);
            }
            if ($video_file) {
                $path = 'uploads/video/' . $video_file;
                $full = __DIR__ . '/../' . $path;
                $size = is_file($full) ? filesize($full) : 0;
                $pdo->prepare("INSERT INTO event_attachments (event_id, file_path, original_name, file_type, file_size) VALUES (?, ?, ?, 'video', ?)")
                    ->execute([$new_id, $path, $video_file, $size]);
            }
        } catch (PDOException $e) {
            error_log('event_attachments media insert failed: ' . $e->getMessage());
        }

        $method_note = $registration_method === 'voice'
            ? 'Registered by voice input'
            : ($registration_method === 'live_stream' ? 'Registered by live stream video' : 'Registered by call center operator');
        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'created', ?, ?)");
        $log->execute([$new_id, $method_note, $_SESSION['user_id']]);

        $catName = '';
        foreach ($categories as $c) { if ($c['id'] == $category_id) { $catName = $c['name']; break; } }
        notify_new_event($pdo, $new_id, $priority, $catName, $tracking_code);
        if ($dept_id) {
            notify_escalation($pdo, $new_id, $dept_id, $tracking_code, $priority);
            notify_department_escalation_sms($pdo, $dept_id, $new_id, $tracking_code, $catName, $priority);
        }

        $success = $tracking_code;
    }

    if ($error) {
        if ($voice_file) { delete_voice_file($voice_file); }
        if ($video_file) { delete_video_file($video_file); }
    }
}

$activeNav = 'new_event';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('new_event_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/call-center-voice-video.css">
<?php leaflet_assets(); ?>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('new_event_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('new_event_subtitle') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <?php if ($success): ?>
        <div class="card">
            <div class="alert success"><?= t('new_event_success') ?> <strong class="mono"><?= htmlspecialchars($success) ?></strong></div>
            <a class="btn" href="new_event.php"><?= t('nav_new_event') ?></a>
            <a class="btn" href="report.php?id=<?= $new_id ?>"><?= t('dash_col_manage') ?></a>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="registration-tabs">
            <button type="button" class="tab-btn active" data-method="manual" onclick="switchMethod('manual')">
                📄 <?= t('reg_method_manual') ?: 'Barreeffamaan Galmeessi' ?>
            </button>
            <button type="button" class="tab-btn" data-method="voice" onclick="switchMethod('voice')">
                🎤 <?= t('reg_method_voice') ?: 'Sagaleen Galmeessi' ?>
            </button>
            <button type="button" class="tab-btn" data-method="live_stream" onclick="switchMethod('live_stream')">
                📹 <?= t('reg_method_video') ?: 'Viidiyoo Yeroo Dhihoo' ?>
            </button>
        </div>

        <form method="post" id="eventForm">
            <?= csrf_field() ?>
            <input type="hidden" name="registration_method" id="registrationMethod" value="manual">

            <!-- MANUAL -->
            <div id="manual-section" class="method-section active">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label><?= t('label_category') ?></label>
                        <select name="category_id" data-for="manual">
                            <option value=""><?= t('select_category') ?></option>
                            <?php foreach ($categories as $i => $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon'] ?? '') ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><?= t('label_severity') ?></label>
                        <select name="priority" data-for="manual">
                            <option value="low"><?= t('priority_low') ?></option>
                            <option value="medium" selected><?= t('priority_medium') ?></option>
                            <option value="high"><?= t('priority_high') ?></option>
                            <option value="critical"><?= t('priority_critical') ?></option>
                        </select>
                    </div>
                </div>

                <label><?= t('label_description') ?></label>
                <textarea name="description" id="descField" placeholder="<?= t('placeholder_description') ?>" oninput="updateWordCount()"></textarea>
                <div class="hint" id="wordCountHint">0 / <?= $WORD_LIMIT ?> words</div>

                <!-- Operator AI Copilot: suggests category/priority/address/department
                     and flags possible duplicates from the description text.
                     Rule/keyword based, runs locally — no API key, no auto-send. -->
                <div class="ai-copilot" id="aiCopilotPanel">
                    <div class="ai-copilot-head">
                        <span class="ai-copilot-title">🤖 Operator AI Copilot</span>
                        <button type="button" class="btn-ai" id="aiAnalyzeBtn" onclick="aiAnalyzeReport()">Analyze report</button>
                    </div>
                    <div class="ai-copilot-body" id="aiCopilotBody" style="display:none;"></div>
                    <div class="ai-copilot-note">🤖 <strong>AI Copilot v2</strong> — description keessaa category, priority, bakka fi department tilmaama. “Apply suggestions” tuquun form irratti ni galcha; ati mirkaneessitee “<?= t('btn_register_event') ?>” tuqxee qofa gabaasni ni galma’a. High confidence = baay’ee amanamaa; medium = ilaali.</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div><label><?= t('label_name') ?></label><input type="text" name="caller_name"></div>
                    <div><label><?= t('label_phone') ?></label><input type="tel" name="caller_phone" placeholder="0988997733 ykn 0722998855" pattern="^(?:\+251|0)[97]\d{8}$"></div>
                    <div><label><?= t('label_gender') ?></label>
                        <select name="gender">
                            <option value="unspecified" selected><?= t('gender_unspecified') ?></option>
                            <option value="male"><?= t('gender_male') ?></option>
                            <option value="female"><?= t('gender_female') ?></option>
                        </select>
                    </div>
                    <div><label><?= t('label_location') ?></label><input type="text" name="location" placeholder="<?= t('placeholder_location') ?>"></div>
                </div>
            </div>

            <!-- VOICE -->
            <div id="voice-section" class="method-section">
                <div class="voice-recorder">
                    <div class="voice-status" id="voiceStatus"><?= t('voice_ready') ?: 'Ready' ?></div>
                    <div class="voice-timer" id="voiceTimer">00:00</div>
                    <div class="voice-controls">
                        <button type="button" class="btn-voice" id="startRecordBtn" onclick="startVoiceRecord()">🎤 <?= t('voice_start') ?: 'Start' ?></button>
                        <button type="button" class="btn-voice stop" id="stopRecordBtn" onclick="stopVoiceRecord()" style="display:none;">⏹️ <?= t('voice_stop') ?: 'Stop' ?></button>
                        <button type="button" class="btn-voice cancel" id="cancelRecordBtn" onclick="cancelVoiceRecord()" style="display:none;">✕ <?= t('voice_cancel') ?: 'Cancel' ?></button>
                    </div>
                    <div id="voicePlayback" style="display:none;margin-top:16px;">
                        <audio id="voiceAudio" controls style="width:100%;"></audio>
                    </div>
                    <div style="margin-top:14px;padding-top:12px;border-top:1px dashed #cbd5e1;text-align:left;">
                        <label style="font-size:13px;color:#64748b;">Mic hin jiru? Faayilii sagalee olkaa'i (mp3, wav, webm, m4a)</label>
                        <input type="file" id="voiceFileUpload" accept="audio/*,.mp3,.wav,.webm,.m4a,.ogg" style="margin-top:6px;width:100%;"
                               onchange="loadMediaFileAsBase64(this, 'voiceFileInput', 'voiceAudio', 'voicePlayback', 'voiceStatus')">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px;">
                    <div>
                        <label><?= t('label_category') ?></label>
                        <select name="category_id" data-for="voice" disabled>
                            <option value=""><?= t('select_category') ?></option>
                            <?php foreach ($categories as $i => $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon'] ?? '') ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><?= t('label_severity') ?></label>
                        <select name="priority" data-for="voice" disabled>
                            <option value="low"><?= t('priority_low') ?></option>
                            <option value="medium" selected><?= t('priority_medium') ?></option>
                            <option value="high"><?= t('priority_high') ?></option>
                            <option value="critical"><?= t('priority_critical') ?></option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="voiceFileInput" name="voice_file">
            </div>

            <!-- VIDEO -->
            <div id="live_stream-section" class="method-section">
                <div class="video-recorder">
                    <div class="video-preview" id="videoPreview">
                        <video id="liveVideo" autoplay muted playsinline></video>
                        <div class="video-status" id="videoStatus"><?= t('video_ready') ?: 'Ready' ?></div>
                    </div>
                    <div class="video-controls" style="margin-top:12px;">
                        <button type="button" class="btn-video" id="startVideoBtn" onclick="startVideoCapture()">📹 <?= t('video_start') ?: 'Start' ?></button>
                        <button type="button" class="btn-video stop" id="stopVideoBtn" onclick="stopVideoCapture()" style="display:none;">⏹️ <?= t('video_stop') ?: 'Stop' ?></button>
                        <button type="button" class="btn-video cancel" id="cancelVideoBtn" onclick="cancelVideoCapture()" style="display:none;">✕ <?= t('video_cancel') ?: 'Cancel' ?></button>
                    </div>
                    <div id="videoPlayback" style="display:none;margin-top:16px;">
                        <video id="videoPlayer" controls style="width:100%;"></video>
                    </div>
                    <div style="margin-top:14px;padding-top:12px;border-top:1px dashed #cbd5e1;text-align:left;">
                        <label style="font-size:13px;color:#64748b;">Camera hin jiru? Faayilii viidiyoo olkaa'i (mp4, webm, mov)</label>
                        <input type="file" id="videoFileUpload" accept="video/*,.mp4,.webm,.mov" style="margin-top:6px;width:100%;"
                               onchange="loadMediaFileAsBase64(this, 'videoFileInput', 'videoPlayer', 'videoPlayback', 'videoStatus')">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px;">
                    <div>
                        <label><?= t('label_category') ?></label>
                        <select name="category_id" data-for="live_stream" disabled>
                            <option value=""><?= t('select_category') ?></option>
                            <?php foreach ($categories as $i => $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon'] ?? '') ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><?= t('label_severity') ?></label>
                        <select name="priority" data-for="live_stream" disabled>
                            <option value="low"><?= t('priority_low') ?></option>
                            <option value="medium" selected><?= t('priority_medium') ?></option>
                            <option value="high"><?= t('priority_high') ?></option>
                            <option value="critical"><?= t('priority_critical') ?></option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="videoFileInput" name="video_file">
            </div>

            <!-- Shared GPS + department (all methods) -->
            <div class="shared-fields">
                <div style="margin-bottom:14px;">
                    <label><?= t('label_address') ?></label>
                    <select name="address_area" id="operatorAddressArea">
                        <option value="">— Kutaa magaalaa Adaamaa filadhu / Select Adama area —</option>
                        <?php foreach (adama_places() as $place): ?>
                            <option value="<?= htmlspecialchars($place['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($place['name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Other area in Adama / Kutaa biraa Adaamaa keessaa">Other area in Adama / Kutaa biraa Adaamaa keessaa</option>
                    </select>
                    <input type="text" name="address" placeholder="Street, house number, or extra details (optional)">
                </div>
                <?php render_location_picker('newEventMap'); ?>
                <div style="margin-top:14px;">
                    <label><?= t('escalate_to_department') ?></label>
                    <select name="department_id">
                        <option value="">-- <?= t('dash_filter_all') ?> --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" id="registerBtn" style="margin-top:16px;"><?= t('btn_register_event') ?></button>
            </div>
        </form>
    </div>
</main>
</div>
<script>
const WORD_LIMIT = <?= (int)$WORD_LIMIT ?>;

function switchMethod(method) {
    document.querySelectorAll('.method-section').forEach(function (s) { s.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
    var sec = document.getElementById(method + '-section');
    if (sec) sec.classList.add('active');
    var tab = document.querySelector('[data-method="' + method + '"]');
    if (tab) tab.classList.add('active');
    document.getElementById('registrationMethod').value = method;

    // Only active method's category/priority are submitted
    document.querySelectorAll('select[data-for]').forEach(function (sel) {
        var on = sel.getAttribute('data-for') === method;
        sel.disabled = !on;
    });

    var btn = document.getElementById('registerBtn');
    if (btn) {
        btn.textContent = method === 'voice'
            ? '<?= t('btn_register_event') ?> (Voice)'
            : (method === 'live_stream' ? '<?= t('btn_register_event') ?> (Video)' : '<?= t('btn_register_event') ?>');
    }
}

function updateWordCount() {
    var text = document.getElementById('descField').value.trim();
    var words = text === '' ? 0 : text.split(/\s+/).length;
    var hint = document.getElementById('wordCountHint');
    if (hint) {
        hint.textContent = words + ' / ' + WORD_LIMIT + ' words';
        hint.style.color = words > WORD_LIMIT ? 'var(--red)' : '';
    }
}

/* ---------------------------------------------------------------- *
 * Operator AI Copilot                                                *
 * Suggests fields from the description text. Never submits anything  *
 * on its own — the operator must press "Apply suggestions", then      *
 * still press the normal register button themselves.                 *
 * ---------------------------------------------------------------- */
var aiLastResult = null;

function aiEsc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function aiChip(label, value, confidence) {
    if (!value) {
        return '<span class="ai-field-row"><span class="ai-field-label">' + aiEsc(label) + '</span>' +
               '<span class="ai-chip review">Review manually</span></span>';
    }
    return '<span class="ai-field-row"><span class="ai-field-label">' + aiEsc(label) + '</span>' +
           '<span class="ai-chip ' + (confidence === 'high' ? 'ok' : 'maybe') + '">' + aiEsc(value) + '</span></span>';
}

/* ---- Operator AI Copilot v2 ---- */
var _aiLast = null;

function aiConfBadge(c) {
  c = (c || 'none').toLowerCase();
  var color = c === 'high' ? '#16a34a' : (c === 'medium' ? '#d97706' : '#94a3b8');
  return '<span style="font-size:11px;padding:1px 7px;border-radius:8px;background:'+color+';color:#fff;margin-left:6px;">' + c + '</span>';
}

function aiAnalyzeReport() {
  var desc = (document.getElementById('descField') || {}).value || '';
  desc = desc.trim();
  var body = document.getElementById('aiCopilotBody');
  var btn = document.getElementById('aiAnalyzeBtn');
  if (!desc) {
    body.style.display = 'block';
    body.innerHTML = '<div class="ai-empty">Duraan dursii description barreessi, ergasii Analyze report tuqi.</div>';
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Analyzing…';
  body.style.display = 'block';
  body.innerHTML = '<div class="ai-empty">⏳ Analyzing…</div>';

  var fd = new FormData();
  fd.append('description', desc);
  var locEl = document.querySelector('[name="location"]');
  if (locEl && locEl.value) fd.append('location', locEl.value);
  var csrf = document.querySelector('input[name="csrf_token"]');
  if (csrf) fd.append('csrf_token', csrf.value);

  fetch('api_ai_copilot.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      btn.disabled = false;
      btn.textContent = 'Analyze report';
      if (!data || !data.ok) {
        body.innerHTML = '<div class="ai-empty">Analysis failed. Irra deebi\'ii yaali.</div>';
        return;
      }
      _aiLast = data;
      var html = '';
      if (data.summary) {
        html += '<div class="ai-row"><strong>Summary:</strong> ' + escapeHtml(data.summary) + '</div>';
      }
      if (data.suggested_action) {
        html += '<div class="ai-row" style="color:var(--cyan);"><strong>→ Action:</strong> ' + escapeHtml(data.suggested_action) + '</div>';
      }
      // Category
      var cat = data.category || {};
      html += '<div class="ai-row"><strong>Category:</strong> ';
      if (cat.id) {
        html += escapeHtml(cat.name || cat.slug || '') + aiConfBadge(cat.confidence);
        if (cat.matched_on) html += ' <span class="muted" style="font-size:12px;">(keyword: ' + escapeHtml(cat.matched_on) + ')</span>';
      } else {
        html += '<span class="muted">Review manually</span>';
      }
      html += '</div>';
      // Priority
      var pri = data.priority || {};
      html += '<div class="ai-row"><strong>Priority:</strong> ';
      if (pri.value) {
        html += escapeHtml(pri.value) + aiConfBadge(pri.confidence);
      } else {
        html += '<span class="muted">Review manually</span>';
      }
      html += '</div>';
      // Address
      var addr = data.address || {};
      html += '<div class="ai-row"><strong>Location:</strong> ';
      if (addr.name) {
        html += escapeHtml(addr.name) + aiConfBadge(addr.confidence);
      } else {
        html += '<span class="muted">Review manually</span>';
      }
      html += '</div>';
      // Department
      var dep = data.department || {};
      html += '<div class="ai-row"><strong>Department:</strong> ';
      if (dep.id) {
        html += escapeHtml(dep.name) + aiConfBadge(dep.confidence);
      } else {
        html += '<span class="muted">Review manually</span>';
      }
      html += '</div>';
      // Duplicates
      if (data.duplicates && data.duplicates.length) {
        html += '<div class="ai-row" style="color:#b45309;"><strong>⚠ Possible duplicates:</strong><ul style="margin:4px 0 0 18px;">';
        data.duplicates.forEach(function (d) {
          html += '<li>' + escapeHtml(d.tracking_code || '') + ' · ' + escapeHtml(d.address || '') + ' · sim ' + (d.similarity || 0) + '</li>';
        });
        html += '</ul></div>';
      }
      html += '<div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">';
      html += '<button type="button" class="btn-ai apply" onclick="aiApplySuggestions(false)">✓ Apply suggestions</button>';
      html += '<button type="button" class="btn-ai" style="background:#0f766e;" onclick="aiApplySuggestions(true)">✓ Apply high-confidence only</button>';
      html += '</div>';
      body.innerHTML = html;
    })
    .catch(function () {
      btn.disabled = false;
      btn.textContent = 'Analyze report';
      body.innerHTML = '<div class="ai-empty">Network error.</div>';
    });
}

function escapeHtml(s) {
  return String(s || '').replace(/[&<>"']/g, function (c) {
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
  });
}

function aiApplySuggestions(highOnly) {
  if (!_aiLast) return;
  highOnly = !!highOnly;
  var data = _aiLast;
  var applied = [];

  function confOk(c) {
    c = (c || 'none').toLowerCase();
    if (highOnly) return c === 'high';
    return c === 'high' || c === 'medium';
  }

  // Category select
  if (data.category && data.category.id && confOk(data.category.confidence)) {
    var catSel = document.querySelector('[name="category_id"]');
    if (catSel) { catSel.value = String(data.category.id); applied.push('category'); }
  }
  // Priority
  if (data.priority && data.priority.value && confOk(data.priority.confidence)) {
    var priSel = document.querySelector('[name="priority"]');
    if (priSel) { priSel.value = data.priority.value; applied.push('priority'); }
  }
  // Location / address
  if (data.address && data.address.name && confOk(data.address.confidence)) {
    var loc = document.querySelector('[name="location"]');
    if (loc && !loc.value.trim()) { loc.value = data.address.name; applied.push('location'); }
    else if (loc && loc.value.trim() === '') { loc.value = data.address.name; applied.push('location'); }
    // lat/lng if fields exist
    if (data.address.lat != null) {
      var lat = document.querySelector('[name="latitude"]');
      var lng = document.querySelector('[name="longitude"]');
      if (lat) lat.value = data.address.lat;
      if (lng) lng.value = data.address.lng;
    }
  }
  // Department
  if (data.department && data.department.id && confOk(data.department.confidence)) {
    var depSel = document.querySelector('[name="assigned_department_id"], [name="department_id"]');
    if (depSel) { depSel.value = String(data.department.id); applied.push('department'); }
  }

  var body = document.getElementById('aiCopilotBody');
  var msg = applied.length
    ? '✓ Applied: ' + applied.join(', ') + '. Please review, then register the event.'
    : 'No fields met the confidence filter — review manually.';
  var note = document.createElement('div');
  note.className = 'ai-row';
  note.style.color = applied.length ? '#16a34a' : '#b45309';
  note.style.marginTop = '8px';
  note.textContent = msg;
  body.appendChild(note);
}


</script>
<script src="../assets/call-center-voice-video.js"></script>
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
