<?php
/**
 * Internal Direct Messages — any staff can message any other staff.
 * Supports text, emoji, image, voice and video.
 */
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

require_once __DIR__ . '/../includes/direct_messages.php';
require_once __DIR__ . '/../includes/security.php';

$dir = t_raw('dir');
$me = current_user();
$myId = (int) ($me['id'] ?? $_SESSION['user_id'] ?? 0);
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $to = (int) ($_POST['receiver_id'] ?? 0);
    $body = trim($_POST['message'] ?? '');
    $msgType = $_POST['message_type'] ?? 'text';
    if (!in_array($msgType, ['text', 'emoji', 'image', 'voice', 'video'], true)) {
        $msgType = 'text';
    }

    $attachPath = null;
    $attachName = null;

    // File upload (image / voice / video)
    if (!empty($_FILES['dm_file']['name']) && ($_FILES['dm_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $uploadType = $msgType;
        if ($uploadType === 'text' || $uploadType === 'emoji') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['dm_file']['tmp_name']);
            finfo_close($finfo);
            if (str_starts_with($mime, 'image/')) $uploadType = 'image';
            elseif (str_starts_with($mime, 'audio/')) $uploadType = 'voice';
            elseif (str_starts_with($mime, 'video/')) $uploadType = 'video';
            else $uploadType = 'image';
        }
        $attachPath = dm_save_attachment($_FILES['dm_file'], $uploadType);
        if ($attachPath) {
            $msgType = $uploadType;
            $attachName = $_FILES['dm_file']['name'] ?? null;
        } else {
            $error = 'File upload failed / Faayilli hin olkaa\'amne.';
        }
    }

    // Base64 from MediaRecorder
    if (!$attachPath && !empty($_POST['voice_b64'])) {
        $attachPath = dm_save_base64($_POST['voice_b64'], 'voice', 'webm');
        if ($attachPath) {
            $msgType = 'voice';
            $attachName = 'voice_recording.webm';
        }
    }
    if (!$attachPath && !empty($_POST['video_b64'])) {
        $attachPath = dm_save_base64($_POST['video_b64'], 'video', 'webm');
        if ($attachPath) {
            $msgType = 'video';
            $attachName = 'video_recording.webm';
        }
    }

    if (!$error) {
        if ($to <= 0 || ($body === '' && !$attachPath)) {
            $error = 'Receiver and message (or media) are required.';
        } elseif (dm_send($pdo, $myId, $to, $body, $msgType, $attachPath, $attachName)) {
            header('Location: direct_messages.php?with=' . $to);
            exit;
        } else {
            $error = 'Could not send message.';
        }
    }
}

$withId = isset($_GET['with']) ? (int) $_GET['with'] : 0;
$recipients = dm_list_recipients($pdo, $myId);
$inbox = dm_inbox($pdo, $myId);
$conversation = [];
$withUser = null;

if ($withId > 0) {
    foreach ($recipients as $r) {
        if ((int)$r['id'] === $withId) { $withUser = $r; break; }
    }
    if (!$withUser) {
        $stmt = $pdo->prepare("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? AND u.status='active'");
        $stmt->execute([$withId]);
        $withUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($withUser) {
        $conversation = dm_conversation($pdo, $myId, $withId);
        dm_mark_read($pdo, $myId, $withId);
    }
}

$unreadTotal = dm_unread_count($pdo, $myId);
$activeNav = 'direct_messages';
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('dm_title') ?> — <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<style>
.dm-layout { display:grid; grid-template-columns: 280px 1fr; gap:16px; min-height:70vh; }
@media (max-width:800px){ .dm-layout{ grid-template-columns:1fr; } }
.dm-inbox { background:var(--panel-solid); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; display:flex; flex-direction:column; }
.dm-inbox-list { overflow-y:auto; flex:1; max-height:65vh; }
.dm-inbox-item { display:block; padding:12px 14px; border-bottom:1px solid var(--border); text-decoration:none; color:inherit; transition:background .15s; }
.dm-inbox-item:hover, .dm-inbox-item.active { background:var(--panel-2); }
.dm-inbox-item .name { font-weight:600; font-size:14px; }
.dm-inbox-item .preview { font-size:12px; color:var(--muted); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dm-badge { background:var(--cyan); color:#fff; font-size:11px; padding:1px 7px; border-radius:10px; margin-left:6px; }
.dm-chat { background:var(--panel-solid); border:1px solid var(--border); border-radius:var(--radius); display:flex; flex-direction:column; min-height:65vh; }
.dm-chat-header { padding:14px 16px; border-bottom:1px solid var(--border); }
.dm-thread { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; max-height:50vh; }
.dm-bubble-wrap { display:flex; flex-direction:column; max-width:75%; }
.dm-bubble-wrap.mine { align-self:flex-end; align-items:flex-end; }
.dm-bubble-wrap.theirs { align-self:flex-start; align-items:flex-start; }
.dm-bubble { padding:10px 14px; border-radius:14px; font-size:14px; line-height:1.45; word-break:break-word; }
.dm-bubble.mine { background:var(--cyan); color:#fff; border-bottom-right-radius:4px; }
.dm-bubble.theirs { background:var(--panel-2); border:1px solid var(--border); border-bottom-left-radius:4px; }
.dm-meta { font-size:11px; color:var(--muted); margin-top:3px; }
.dm-composer { padding:12px 14px; border-top:1px solid var(--border); }
.dm-composer textarea { width:100%; resize:vertical; min-height:48px; border-radius:10px; border:1px solid var(--border); padding:10px 12px; font:inherit; background:var(--panel-2); color:var(--text); box-sizing:border-box; }
.dm-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; }
.dm-toolbar button, .dm-toolbar label.dm-tool-btn {
  display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:8px;
  border:1px solid var(--border); background:var(--panel-2); cursor:pointer; font-size:13px; color:var(--text);
}
.dm-toolbar button:hover, .dm-toolbar label.dm-tool-btn:hover { border-color:var(--cyan); color:var(--cyan); }
.dm-toolbar button.recording { background:#fee2e2; border-color:#ef4444; color:#b91c1c; }
.emoji-picker { display:none; flex-wrap:wrap; gap:4px; padding:8px; background:var(--panel-2); border:1px solid var(--border); border-radius:10px; margin-bottom:8px; max-width:320px; }
.emoji-picker.open { display:flex; }
.emoji-picker span { font-size:22px; cursor:pointer; padding:4px; border-radius:6px; }
.emoji-picker span:hover { background:var(--panel-solid); }
.dm-media { margin-bottom:6px; }
.dm-preview { margin-top:8px; font-size:12px; color:var(--muted); }
.dm-preview audio, .dm-preview video { max-width:100%; margin-top:4px; }
</style>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="top-actions" style="margin-bottom:16px;">
    <div>
      <div class="eyebrow" style="font-family:var(--mono); font-size:10.5px; letter-spacing:2px; text-transform:uppercase; color:var(--cyan); margin-bottom:6px;">
        Staff
      </div>
      <h2 style="margin:0;"><?= t('dm_title') ?>
        <?php if ($unreadTotal > 0): ?><span class="dm-badge"><?= $unreadTotal ?></span><?php endif; ?>
      </h2>
    </div>
    <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
  </div>

  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="dm-layout">
    <aside class="dm-inbox">
      <div style="padding:12px 14px; border-bottom:1px solid var(--border); font-weight:600; font-size:13px;">
        <?= t('dm_inbox') ?: 'Inbox' ?>
      </div>
      <div class="dm-inbox-list">
        <?php if (empty($inbox)): ?>
          <p class="muted" style="padding:16px; font-size:13px;"><?= t('dm_no_convos') ?: 'No conversations yet.' ?></p>
        <?php else: ?>
          <?php foreach ($inbox as $row): ?>
            <a href="?with=<?= (int)$row['user_id'] ?>" class="dm-inbox-item <?= $withId === (int)$row['user_id'] ? 'active' : '' ?>">
              <div class="name">
                <?= htmlspecialchars($row['full_name']) ?>
                <?php if ((int)$row['unread'] > 0): ?><span class="dm-badge"><?= (int)$row['unread'] ?></span><?php endif; ?>
              </div>
              <div class="preview">
                <?php
                  $lt = $row['last_type'] ?? 'text';
                  $icons = ['image'=>'📷 ','voice'=>'🎤 ','video'=>'🎬 ','emoji'=>''];
                  echo ($icons[$lt] ?? '') . htmlspecialchars(mb_substr($row['last_message'] ?? '', 0, 40));
                ?>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div style="padding:12px 14px; border-top:1px solid var(--border);">
        <form method="get">
          <label style="font-size:12px; color:var(--muted);"><?= t('dm_new') ?: 'New message' ?></label>
          <select name="with" onchange="this.form.submit()" style="width:100%; margin-top:4px;">
            <option value="">— <?= t('dm_select') ?: 'Select staff' ?> —</option>
            <?php foreach ($recipients as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= $withId === (int)$r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['full_name']) ?> (<?= dm_role_label($r['role']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </aside>

    <section class="dm-chat">
      <?php if (!$withUser): ?>
        <div style="margin:auto; text-align:center; color:var(--muted); padding:40px;">
          <p><?= t('dm_select_prompt') ?: 'Select a conversation or start a new one.' ?></p>
        </div>
      <?php else: ?>
        <div class="dm-chat-header">
          <strong><?= htmlspecialchars($withUser['full_name']) ?></strong>
          <span style="font-size:12px; color:var(--muted); margin-left:8px;">
            <?= dm_role_label($withUser['role'] ?? '') ?>
            <?= !empty($withUser['dept_name']) ? ' · '.htmlspecialchars($withUser['dept_name']) : '' ?>
          </span>
        </div>

        <div id="dm-thread" class="dm-thread">
          <?php if (empty($conversation)): ?>
            <p class="muted" style="text-align:center; margin:auto;"><?= t('dm_no_msgs') ?></p>
          <?php else: ?>
            <?php foreach ($conversation as $msg): ?>
              <?php $mine = (int)$msg['sender_id'] === $myId; ?>
              <div class="dm-bubble-wrap <?= $mine ? 'mine' : 'theirs' ?>">
                <div class="dm-bubble <?= $mine ? 'mine' : 'theirs' ?>">
                  <?= dm_render_body($msg) ?>
                </div>
                <div class="dm-meta">
                  <?= htmlspecialchars($msg['created_at']) ?>
                  <?= $mine ? '' : ' · '.htmlspecialchars($msg['sender_name'] ?? '') ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data" class="dm-composer" id="dmForm">
          <?= csrf_field() ?>
          <input type="hidden" name="receiver_id" value="<?= (int)$withUser['id'] ?>">
          <input type="hidden" name="message_type" id="message_type" value="text">
          <input type="hidden" name="voice_b64" id="voice_b64" value="">
          <input type="hidden" name="video_b64" id="video_b64" value="">

          <div class="dm-toolbar">
            <button type="button" id="btnEmoji" title="Emoji">😊 Emoji</button>
            <label class="dm-tool-btn" title="Image">
              📷 Image
              <input type="file" name="dm_file" id="dmFileImage" accept="image/*" style="display:none;" onchange="onFilePick(this,'image')">
            </label>
            <button type="button" id="btnVoice" title="Record voice">🎤 Voice</button>
            <button type="button" id="btnVideo" title="Record video">🎬 Video</button>
            <label class="dm-tool-btn" title="Upload any media">
              📎 File
              <input type="file" name="dm_file" id="dmFileAny" accept="image/*,audio/*,video/*" style="display:none;" onchange="onFilePick(this,'auto')">
            </label>
          </div>

          <div id="emojiPicker" class="emoji-picker">
            <?php
            $emojis = ['😀','😂','😊','😍','🤔','👍','👎','👏','🙏','🔥','✅','❌','⚠️','🚨','📍','📞','💪','🙌','❤️','💙','🟢','🔴','⭐','🎉','📝','📷','🎤','🎬','🚗','🏥','👮','🚒'];
            foreach ($emojis as $e) echo '<span onclick="insertEmoji(\''.$e.'\')">'.$e.'</span>';
            ?>
          </div>

          <div id="mediaPreview" class="dm-preview" style="display:none;"></div>

          <textarea name="message" id="dmMessage" rows="2" placeholder="<?= htmlspecialchars(t('dm_placeholder')) ?>"></textarea>
          <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;">
            <button type="submit" class="btn"><?= t('dm_send') ?></button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>

</main>
</div>

<script>
(function () {
  var el = document.getElementById('dm-thread');
  if (el) el.scrollTop = el.scrollHeight;
})();

function insertEmoji(e) {
  var ta = document.getElementById('dmMessage');
  if (!ta) return;
  ta.value += e;
  document.getElementById('message_type').value = 'emoji';
  ta.focus();
}

document.getElementById('btnEmoji')?.addEventListener('click', function () {
  document.getElementById('emojiPicker').classList.toggle('open');
});

function onFilePick(input, forcedType) {
  var file = input.files && input.files[0];
  if (!file) return;
  var type = forcedType;
  if (type === 'auto') {
    if (file.type.startsWith('image/')) type = 'image';
    else if (file.type.startsWith('audio/')) type = 'voice';
    else if (file.type.startsWith('video/')) type = 'video';
    else type = 'image';
  }
  document.getElementById('message_type').value = type;
  var other = input.id === 'dmFileImage' ? document.getElementById('dmFileAny') : document.getElementById('dmFileImage');
  if (other) other.value = '';
  var prev = document.getElementById('mediaPreview');
  prev.style.display = 'block';
  prev.innerHTML = '📎 ' + file.name + ' (' + (file.size/1024).toFixed(0) + ' KB) · ' + type;
  if (type === 'image') {
    var url = URL.createObjectURL(file);
    prev.innerHTML += '<br><img src="'+url+'" style="max-width:160px;border-radius:6px;margin-top:4px;">';
  }
}

var voiceRecorder = null, voiceChunks = [], voiceStream = null;
document.getElementById('btnVoice')?.addEventListener('click', async function () {
  var btn = this;
  if (voiceRecorder && voiceRecorder.state === 'recording') {
    voiceRecorder.stop();
    btn.classList.remove('recording');
    btn.textContent = '🎤 Voice';
    return;
  }
  try {
    voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    voiceChunks = [];
    voiceRecorder = new MediaRecorder(voiceStream);
    voiceRecorder.ondataavailable = function (e) { if (e.data.size) voiceChunks.push(e.data); };
    voiceRecorder.onstop = function () {
      var blob = new Blob(voiceChunks, { type: 'audio/webm' });
      var reader = new FileReader();
      reader.onloadend = function () {
        var b64 = reader.result.split(',')[1] || '';
        document.getElementById('voice_b64').value = b64;
        document.getElementById('message_type').value = 'voice';
        document.getElementById('video_b64').value = '';
        var prev = document.getElementById('mediaPreview');
        prev.style.display = 'block';
        prev.innerHTML = '🎤 Voice recorded <audio controls src="'+URL.createObjectURL(blob)+'" style="display:block;margin-top:4px;max-width:240px;"></audio>';
      };
      reader.readAsDataURL(blob);
      voiceStream.getTracks().forEach(function (t) { t.stop(); });
    };
    voiceRecorder.start();
    btn.classList.add('recording');
    btn.textContent = '⏹ Stop';
  } catch (err) {
    alert('Microphone access failed: ' + (err.message || err.name));
  }
});

var videoRecorder = null, videoChunks = [], videoStream = null;
document.getElementById('btnVideo')?.addEventListener('click', async function () {
  var btn = this;
  if (videoRecorder && videoRecorder.state === 'recording') {
    videoRecorder.stop();
    btn.classList.remove('recording');
    btn.textContent = '🎬 Video';
    return;
  }
  try {
    videoStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
    videoChunks = [];
    videoRecorder = new MediaRecorder(videoStream);
    videoRecorder.ondataavailable = function (e) { if (e.data.size) videoChunks.push(e.data); };
    videoRecorder.onstop = function () {
      var blob = new Blob(videoChunks, { type: 'video/webm' });
      var reader = new FileReader();
      reader.onloadend = function () {
        var b64 = reader.result.split(',')[1] || '';
        document.getElementById('video_b64').value = b64;
        document.getElementById('message_type').value = 'video';
        document.getElementById('voice_b64').value = '';
        var prev = document.getElementById('mediaPreview');
        prev.style.display = 'block';
        prev.innerHTML = '🎬 Video recorded <video controls src="'+URL.createObjectURL(blob)+'" style="display:block;margin-top:4px;max-width:240px;max-height:140px;"></video>';
      };
      reader.readAsDataURL(blob);
      videoStream.getTracks().forEach(function (t) { t.stop(); });
    };
    videoRecorder.start();
    btn.classList.add('recording');
    btn.textContent = '⏹ Stop';
  } catch (err) {
    alert('Camera/mic access failed: ' + (err.message || err.name));
  }
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
    <div style="font-size: 13.5px; font-weight: 600; color: #334155; letter-spacing: 0.3px;">
        © 2026 MNAN. All Rights Reserved.
    </div>
    <div style="margin-top: 6px; font-size: 12px; color: #64748b;">
        Designed &amp; Developed by <span style="color:#0ea5e9; font-weight:600;">MNAN</span>
    </div>
    <div style="margin-top: 8px; font-size: 11px; color: #94a3b8;">
        Adama City Administration · Call Center 9141
    </div>
</footer>
</body>
</html>
