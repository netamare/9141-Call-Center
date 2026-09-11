<?php
/**
 * Public citizen help request → saves + urgent notify to administrator, operator, supervisor
 * Supports: text, emoji, voice (record or upload)
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/lang.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/notifications.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS citizen_help (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tracking_code VARCHAR(32) DEFAULT NULL,
        name VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(32) NOT NULL,
        message TEXT NOT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        attachment_path VARCHAR(500) DEFAULT NULL,
        attachment_name VARCHAR(255) DEFAULT NULL,
        status ENUM('new','seen','answered') NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

foreach ([
    'reply_message'   => "ALTER TABLE citizen_help ADD COLUMN reply_message TEXT DEFAULT NULL",
    'replied_by'      => "ALTER TABLE citizen_help ADD COLUMN replied_by INT DEFAULT NULL",
    'replied_name'    => "ALTER TABLE citizen_help ADD COLUMN replied_name VARCHAR(150) DEFAULT NULL",
    'replied_at'      => "ALTER TABLE citizen_help ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL",
    'message_type'    => "ALTER TABLE citizen_help ADD COLUMN message_type VARCHAR(20) NOT NULL DEFAULT 'text' AFTER message",
    'attachment_path' => "ALTER TABLE citizen_help ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER message_type",
    'attachment_name' => "ALTER TABLE citizen_help ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path",
] as $col => $sql) {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM citizen_help LIKE " . $pdo->quote($col))->fetch();
        if (!$chk) $pdo->exec($sql);
    } catch (Throwable $e) {}
}

/** Save voice file upload */
function ch_save_voice(array $file): ?string {
    if (empty($file['name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 20 * 1024 * 1024) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/mpeg', 'audio/ogg', 'audio/x-m4a', 'audio/mp4'];
    if (!in_array($mime, $allowed, true)) return null;
    $dir = __DIR__ . '/uploads/help/voice/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'webm');
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return null;
    chmod($dir . $name, 0644);
    return 'uploads/help/voice/' . $name;
}

/** Save base64 voice from MediaRecorder */
function ch_save_voice_b64(string $b64): ?string {
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 100 || strlen($raw) > 20 * 1024 * 1024) return null;
    $dir = __DIR__ . '/uploads/help/voice/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.webm';
    if (file_put_contents($dir . $name, $raw) === false) return null;
    chmod($dir . $name, 0644);
    return 'uploads/help/voice/' . $name;
}

/** Render help message body (text + voice) */
function ch_render_body(array $h): string {
    $type = $h['message_type'] ?? 'text';
    $path = $h['attachment_path'] ?? '';
    $text = htmlspecialchars($h['message'] ?? '');
    $html = '';
    if ($path !== '' && $type === 'voice') {
        $html .= '<div style="margin-bottom:6px;"><audio controls src="' . htmlspecialchars($path) . '" style="max-width:260px;"></audio></div>';
    }
    if ($text !== '' && !preg_match('/^🎤/u', $h['message'] ?? '')) {
        $html .= '<div style="line-height:1.5;">' . nl2br($text) . '</div>';
    } elseif ($html === '') {
        $html = nl2br($text);
    }
    return $html;
}

$ok = null;
$err = null;
$myHelps = [];
$lookupPhone = trim($_GET['phone'] ?? $_POST['lookup_phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_replies'])) {
    verify_csrf();
    $lookupPhone = trim($_POST['lookup_phone'] ?? '');
}

if ($lookupPhone !== '') {
    try {
        $st = $pdo->prepare("SELECT * FROM citizen_help WHERE phone = ? ORDER BY created_at DESC LIMIT 20");
        $st->execute([$lookupPhone]);
        $myHelps = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $myHelps = []; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['lookup_replies'])) {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $code = trim($_POST['tracking_code'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $msgType = $_POST['message_type'] ?? 'text';
    if (!in_array($msgType, ['text', 'emoji', 'voice'], true)) $msgType = 'text';

    $attachPath = null;
    $attachName = null;

    // Voice file upload
    if (!empty($_FILES['voice_file']['name']) && ($_FILES['voice_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $attachPath = ch_save_voice($_FILES['voice_file']);
        if ($attachPath) {
            $msgType = 'voice';
            $attachName = $_FILES['voice_file']['name'] ?? 'voice';
        } else {
            $err = 'Voice upload failed / Sagaleen hin olkaa\'amne.';
        }
    }
    // Base64 from browser recorder
    if (!$attachPath && !empty($_POST['voice_b64'])) {
        $attachPath = ch_save_voice_b64($_POST['voice_b64']);
        if ($attachPath) {
            $msgType = 'voice';
            $attachName = 'voice_recording.webm';
        }
    }

    if (!$err) {
        if ($phone === '' || ($message === '' && !$attachPath)) {
            $err = t_raw('error_required');
        } else {
            if ($message === '' && $attachPath) {
                $message = '🎤 Voice message';
            }
            $stmt = $pdo->prepare(
                "INSERT INTO citizen_help (tracking_code, name, phone, message, message_type, attachment_path, attachment_name)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $code !== '' ? $code : null,
                $name !== '' ? $name : null,
                $phone,
                $message,
                $msgType,
                $attachPath,
                $attachName,
            ]);

            $title = t_raw('citizen_help_notify_title');
            $preview = $message !== '' ? $message : '[voice]';
            $body = trim(($name ? $name . ' · ' : '') . $phone . ($code ? ' · ' . $code : '') . ' — ' . mb_substr($preview, 0, 180));
            try {
                notify_roles($pdo, ['administrator', 'operator', 'supervisor'], null, 'citizen_help', $title, $body, true);
            } catch (Throwable $e) {}

            $ok = true;
            $lookupPhone = $phone;
            try {
                $st = $pdo->prepare("SELECT * FROM citizen_help WHERE phone = ? ORDER BY created_at DESC LIMIT 20");
                $st->execute([$phone]);
                $myHelps = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }
    }
}

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('citizen_help_page_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
<style>
.ch-toolbar { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
.ch-toolbar button, .ch-toolbar label.ch-btn {
  display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border-radius:8px;
  border:1px solid var(--border); background:var(--panel-2); cursor:pointer; font-size:13px; color:var(--text);
}
.ch-toolbar button:hover, .ch-toolbar label.ch-btn:hover { border-color:var(--cyan); color:var(--cyan); }
.ch-toolbar button.recording { background:#fee2e2; border-color:#ef4444; color:#b91c1c; }
.ch-emoji { display:none; flex-wrap:wrap; gap:4px; padding:8px; background:var(--panel-2); border:1px solid var(--border); border-radius:10px; margin-bottom:8px; max-width:320px; }
.ch-emoji.open { display:flex; }
.ch-emoji span { font-size:22px; cursor:pointer; padding:4px; border-radius:6px; }
.ch-emoji span:hover { background:var(--panel-solid); }
.ch-preview { font-size:12px; color:var(--muted); margin-bottom:8px; display:none; }
</style>
</head>
<body>
<?php
$header_title = t('citizen_help_heading_public');
$header_subtitle = '';
$active_nav = 'help';
require __DIR__ . '/includes/public_header.php';
?>
<div class="container">
    <div class="card">
        <?php if ($ok): ?>
            <div class="alert success"><?= t('citizen_help_success') ?></div>
            <a class="btn" href="index.php"><?= t('btn_back_home') ?></a>
        <?php else: ?>
            <?php if ($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <p class="muted"><?= t('citizen_help_intro') ?></p>
            <form method="post" enctype="multipart/form-data" id="chForm">
                <?= csrf_field() ?>
                <input type="hidden" name="message_type" id="ch_msg_type" value="text">
                <input type="hidden" name="voice_b64" id="ch_voice_b64" value="">

                <label><?= t('citizen_help_name') ?></label>
                <input type="text" name="name" maxlength="150">
                <label><?= t('citizen_help_phone') ?> *</label>
                <input type="text" name="phone" required placeholder="09xxxxxxxx">
                <label><?= t('citizen_help_tracking') ?></label>
                <input type="text" name="tracking_code" placeholder="9141-XXXXXX">

                <label><?= t('citizen_help_message') ?></label>
                <div class="ch-toolbar">
                    <button type="button" id="chBtnEmoji" title="Emoji">😊 Emoji</button>
                    <button type="button" id="chBtnVoice" title="Record voice">🎤 Voice</button>
                    <label class="ch-btn" title="Upload voice file">
                        📎 Voice file
                        <input type="file" name="voice_file" id="chVoiceFile" accept="audio/*" style="display:none;" onchange="chOnVoiceFile(this)">
                    </label>
                </div>
                <div id="chEmoji" class="ch-emoji">
                    <?php
                    $emojis = ['😀','😂','😊','😍','🤔','👍','👎','👏','🙏','🔥','✅','❌','⚠️','🚨','📍','📞','💪','🙌','❤️','💙','🟢','🔴','⭐','🎉','📝','📷','🎤','🚗','🏥','👮','🚒','🆘'];
                    foreach ($emojis as $e) echo '<span onclick="chInsertEmoji(\''.$e.'\')">'.$e.'</span>';
                    ?>
                </div>
                <div id="chPreview" class="ch-preview"></div>
                <textarea name="message" id="chMessage" rows="4" placeholder="<?= t_raw('citizen_help_message_ph') ?>"></textarea>
                <button type="submit" style="margin-top:10px;"><?= t('citizen_help_submit') ?></button>
            </form>
            <p style="margin-top:16px;"><a class="btn" href="index.php"><?= t('btn_back_home') ?></a></p>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top:16px;">
        <h2 style="font-size:16px; margin-top:0;"><?= t('citizen_help_check_replies') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('citizen_help_check_intro') ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="lookup_replies" value="1">
            <label><?= t('citizen_help_phone') ?> *</label>
            <input type="text" name="lookup_phone" required value="<?= htmlspecialchars($lookupPhone) ?>" placeholder="09xxxxxxxx">
            <button type="submit"><?= t('citizen_help_check_btn') ?></button>
        </form>
        <?php if ($lookupPhone !== ''): ?>
            <?php if (!$myHelps): ?>
                <p class="muted" style="margin-top:14px;"><?= t('citizen_help_no_requests') ?></p>
            <?php else: ?>
                <?php foreach ($myHelps as $h): ?>
                    <div style="margin-top:14px; padding:12px; border:1px solid var(--border); border-radius:10px; background:var(--panel-2);">
                        <div style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($h['created_at']) ?> · <?= htmlspecialchars($h['status'] ?? '') ?>
                            <?php if (($h['message_type'] ?? '') === 'voice'): ?> · 🎤<?php endif; ?>
                        </div>
                        <div style="margin-top:6px;"><strong><?= t('citizen_help_message') ?>:</strong><br><?= ch_render_body($h) ?></div>
                        <?php if (!empty($h['reply_message'])): ?>
                            <div style="margin-top:10px; padding:10px; border-left:3px solid var(--green); background:rgba(16,185,129,.1); border-radius:6px;">
                                <div style="font-size:12px; color:var(--muted);"><?= t('citizen_help_staff_reply') ?> · <?= htmlspecialchars($h['replied_name'] ?? '') ?> · <?= htmlspecialchars($h['replied_at'] ?? '') ?></div>
                                <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($h['reply_message'])) ?></div>
                            </div>
                        <?php else: ?>
                            <p class="muted" style="margin:8px 0 0; font-size:13px;"><?= t('citizen_help_waiting_reply') ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
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

<script>
function chInsertEmoji(e) {
  var ta = document.getElementById('chMessage');
  if (!ta) return;
  ta.value += e;
  document.getElementById('ch_msg_type').value = 'emoji';
  ta.focus();
}
document.getElementById('chBtnEmoji')?.addEventListener('click', function () {
  document.getElementById('chEmoji').classList.toggle('open');
});
function chOnVoiceFile(input) {
  var f = input.files && input.files[0];
  if (!f) return;
  document.getElementById('ch_msg_type').value = 'voice';
  document.getElementById('ch_voice_b64').value = '';
  var prev = document.getElementById('chPreview');
  prev.style.display = 'block';
  prev.innerHTML = '🎤 ' + f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
}
var chRec = null, chChunks = [], chStream = null;
document.getElementById('chBtnVoice')?.addEventListener('click', async function () {
  var btn = this;
  if (chRec && chRec.state === 'recording') {
    chRec.stop();
    btn.classList.remove('recording');
    btn.textContent = '🎤 Voice';
    return;
  }
  try {
    chStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    chChunks = [];
    chRec = new MediaRecorder(chStream);
    chRec.ondataavailable = function (e) { if (e.data.size) chChunks.push(e.data); };
    chRec.onstop = function () {
      var blob = new Blob(chChunks, { type: 'audio/webm' });
      var reader = new FileReader();
      reader.onloadend = function () {
        document.getElementById('ch_voice_b64').value = (reader.result || '').split(',')[1] || '';
        document.getElementById('ch_msg_type').value = 'voice';
        var prev = document.getElementById('chPreview');
        prev.style.display = 'block';
        prev.innerHTML = '🎤 Voice recorded <audio controls src="' + URL.createObjectURL(blob) + '" style="display:block;margin-top:4px;max-width:240px;"></audio>';
      };
      reader.readAsDataURL(blob);
      chStream.getTracks().forEach(function (t) { t.stop(); });
    };
    chRec.start();
    btn.classList.add('recording');
    btn.textContent = '⏹ Stop';
  } catch (err) {
    alert('Microphone: ' + (err.message || err.name));
  }
});
</script>
<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>
