<?php
require 'config.php';
require 'includes/lang.php';
require 'includes/security.php';
require 'includes/maps.php';
require 'includes/supervisor_messages.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$report = null;
$attachments = [];
$rateSuccess = false;
$dmSuccess = false;
$dmError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    verify_csrf();
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating >= 1 && $rating <= 5 && $code !== '') {
        $stmt = $pdo->prepare("UPDATE events SET satisfaction_rating = ?, satisfaction_comment = ?
                                WHERE tracking_code = ? AND status IN ('solved','unsolved') AND satisfaction_rating IS NULL");
        $stmt->execute([$rating, $comment ?: null, $code]);
        $rateSuccess = true;
    }
}

// Public → supervisor direct message (any time while case is still open)
// Supports text, emoji, image, voice, video
$dmSuccess = false;
$dmError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_citizen_dm'])) {
    verify_csrf();
    $code = trim($_POST['code'] ?? $code);
    $dmText = trim($_POST['citizen_dm'] ?? '');
    $dmName = trim($_POST['citizen_dm_name'] ?? '');
    $dmPhone = trim($_POST['citizen_dm_phone'] ?? '');
    $dmConsent = !empty($_POST['citizen_dm_consent']);
    $msgType = $_POST['message_type'] ?? 'text';
    if (!in_array($msgType, ['text', 'emoji', 'image', 'voice', 'video'], true)) $msgType = 'text';

    $attachPath = null;
    $attachName = null;

    // File upload
    if (!empty($_FILES['sm_file']['name']) && ($_FILES['sm_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $uploadType = $msgType;
        if ($uploadType === 'text' || $uploadType === 'emoji') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['sm_file']['tmp_name']);
            finfo_close($finfo);
            if (str_starts_with($mime, 'image/')) $uploadType = 'image';
            elseif (str_starts_with($mime, 'audio/')) $uploadType = 'voice';
            elseif (str_starts_with($mime, 'video/')) $uploadType = 'video';
            else $uploadType = 'image';
        }
        $attachPath = sm_save_attachment($_FILES['sm_file'], $uploadType);
        if ($attachPath) {
            $msgType = $uploadType;
            $attachName = $_FILES['sm_file']['name'] ?? null;
        } else {
            $dmError = 'File upload failed / Faayilli hin olkaa\'amne.';
        }
    }
    // Base64 recordings
    if (!$attachPath && !empty($_POST['voice_b64'])) {
        $attachPath = sm_save_base64($_POST['voice_b64'], 'voice', 'webm');
        if ($attachPath) { $msgType = 'voice'; $attachName = 'voice.webm'; }
    }
    if (!$attachPath && !empty($_POST['video_b64'])) {
        $attachPath = sm_save_base64($_POST['video_b64'], 'video', 'webm');
        if ($attachPath) { $msgType = 'video'; $attachName = 'video.webm'; }
    }

    if (!$dmError) {
        if (!$dmConsent) {
            $dmError = t_raw('sup_dm_consent_required');
        } elseif ($code !== '' && ($dmText !== '' || $attachPath)) {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE tracking_code = ?");
            $stmt->execute([$code]);
            $ev = $stmt->fetch();
            $stillOpen = $ev && !in_array($ev['status'] ?? '', ['solved', 'unsolved'], true);
            if ($ev && $stillOpen) {
                if (citizen_message_to_supervisor($pdo, (int)$ev['id'], $dmText, $dmName, $dmPhone, $msgType, $attachPath, $attachName)) {
                    $dmSuccess = true;
                    try {
                        require_once __DIR__ . '/includes/notifications.php';
                        $title = t_raw('sup_dm_from_public_notify');
                        $preview = $dmText !== '' ? $dmText : ('[' . $msgType . ']');
                        $body = trim(($dmName ? $dmName . ' · ' : '') . ($dmPhone ? $dmPhone . ' · ' : '') . $code . ' — ' . mb_substr($preview, 0, 160));
                        notify_roles($pdo, ['supervisor'], (int)$ev['id'], 'citizen_dm', $title, $body, true);
                    } catch (Throwable $e) {}
                } else {
                    $dmError = t_raw('error_required');
                }
            } else {
                $dmError = t_raw('track_case_no_dm_resolved');
            }
        } else {
            $dmError = t_raw('error_required');
        }
    } else {
        $dmError = t_raw('error_required');
    }
}

if ($code !== '') {
    $stmt = $pdo->prepare("SELECT r.*, c.name AS category_name, c.icon, d.name AS department_name
                            FROM events r
                            LEFT JOIN categories c ON c.id = r.category_id
                            LEFT JOIN departments d ON d.id = r.assigned_department_id
                            WHERE r.tracking_code = ?");
    $stmt->execute([$code]);
    $report = $stmt->fetch();

    if ($report) {
        $a = $pdo->prepare("SELECT * FROM event_attachments WHERE event_id = ?");
        $a->execute([$report['id']]);
        $attachments = $a->fetchAll();
        $supMsgs = supervisor_messages_for_event($pdo, (int)$report['id'], 'to_public');
        $citizenToSup = supervisor_messages_for_event($pdo, (int)$report['id'], 'to_supervisor');
    } else {
        $supMsgs = [];
    }
}

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('track_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
<?php if ($report && $report['latitude'] !== null && $report['longitude'] !== null) leaflet_assets(); ?>
</head>
<body>
<?php
$header_title = t('track_heading');
$header_subtitle = '';
$active_nav = 'supervisor';
require __DIR__ . '/includes/public_header.php';
?>
<div class="container">
    <div class="card">
        <?php if ($code === ''): ?>
            <p><?= t('track_no_code') ?></p>
            <p class="muted" style="font-size:13px; margin:10px 0 14px;"><?= t('contact_supervisor_hint') ?></p>
            <form method="get" action="track.php">
                <label><?= t('label_tracking_code') ?></label>
                <input type="text" name="code" placeholder="<?= t('placeholder_tracking_code') ?>" required>
                <button type="submit"><?= t('btn_check') ?> / <?= t('btn_contact_supervisor') ?></button>
            </form>
        <?php elseif (!$report): ?>
            <div class="alert error"><?= t('track_not_found') ?></div>
        <?php else: ?>
            <h2><?= htmlspecialchars($report['icon'] ?? '') ?> <?= htmlspecialchars($report['tracking_code']) ?></h2>
            <p><strong><?= t('track_category') ?>:</strong> <?= htmlspecialchars($report['category_name']) ?></p>
            <p><strong><?= t('track_status') ?>:</strong> <span class="badge <?= $report['status'] ?>"><?= t('status_' . $report['status']) ?></span></p>
            <p><strong><?= t('track_department') ?>:</strong> <?= htmlspecialchars($report['department_name'] ?? t_raw('track_not_assigned')) ?></p>
            <p><strong><?= t('track_submitted') ?>:</strong> <?= htmlspecialchars($report['created_at']) ?></p>
            <p><strong><?= t('track_updated') ?>:</strong> <?= htmlspecialchars($report['updated_at']) ?></p>

            <?php if ($report['latitude'] !== null && $report['longitude'] !== null): ?>
                <p><strong>📍 <?= t('label_gps') ?>:</strong></p>
                <?php render_location_view($report['latitude'], $report['longitude'], 'trackViewMap'); ?>
            <?php endif; ?>

            <?php if ($attachments): ?>
            <p><strong><?= t('track_attachments') ?>:</strong> <?= count($attachments) ?></p>
            <div class="attachment-grid">
                <?php foreach ($attachments as $att): ?>
                    <?php if ($att['file_type'] === 'image'): ?>
                        <img src="<?= htmlspecialchars($att['file_path']) ?>" alt="<?= htmlspecialchars($att['original_name']) ?>" class="attachment-thumb">
                    <?php else: ?>
                        <a class="attachment-file" href="<?= htmlspecialchars($att['file_path']) ?>" target="_blank" rel="noopener">
                            <?= $att['file_type'] === 'video' ? '🎬' : ($att['file_type'] === 'audio' ? '🔊' : '📄') ?>
                            <?= htmlspecialchars($att['original_name']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>


            <?php if (!empty($supMsgs)): ?>
            <hr style="border-color:var(--border); margin:20px 0;">
            <h2 style="font-size:16px;"><?= t('sup_dm_public_title') ?></h2>
            <p class="muted" style="font-size:13px;"><?= t('sup_dm_public_intro') ?></p>
            <?php foreach ($supMsgs as $sm): ?>
                <div style="padding:12px 14px; border:1px solid var(--border); border-radius:10px; margin-top:10px; background:var(--panel-2);">
                    <div style="font-size:12px; color:var(--muted); font-weight:600;">
                        <?= htmlspecialchars($sm['supervisor_name'] ?? 'Supervisor') ?>
                        · <?= htmlspecialchars($sm['created_at']) ?>
                    </div>
                    <div style="margin-top:6px;"><?= sm_render_body($sm) ?></div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>


            <?php
            $statusOpen = !in_array($report['status'] ?? '', ['solved','unsolved'], true);
            $daysOld = 0;
            if (!empty($report['created_at'])) {
                $ts = strtotime($report['created_at']);
                if ($ts) $daysOld = (int) floor(max(0, time() - $ts) / 86400);
            }
            // Message supervisor any time while case is still open
            $canCitizenDm = $report && $statusOpen;
            ?>
            <hr style="border-color:var(--border); margin:20px 0;">
            <div style="padding:12px 14px; border-radius:10px; margin-bottom:14px; border:1px solid var(--border); background:var(--panel-2);">
                <div style="font-weight:600; margin-bottom:6px;"><?= t('track_case_status_title') ?></div>
                <?php if (!$statusOpen): ?>
                    <p style="margin:0; color:var(--green);"><?= t('track_case_resolved') ?></p>
                <?php else: ?>
                    <p style="margin:0 0 4px; color:var(--amber);"><?= t('track_case_open') ?> · <?= (int)$daysOld ?> <?= t('track_days') ?></p>
                    <p style="margin:0; font-size:13px;"><?= t('track_case_can_message') ?></p>
                <?php endif; ?>
            </div>

            <h2 style="font-size:16px;" id="contact-supervisor"><?= t('sup_dm_citizen_form_title') ?></h2>
            <?php if ($dmSuccess): ?>
                <div class="alert success"><?= t('sup_dm_citizen_sent') ?></div>
            <?php elseif (!$statusOpen): ?>
                <p class="muted"><?= t('track_case_no_dm_resolved') ?></p>
            <?php else: ?>
                <?php if ($dmError): ?><div class="alert error"><?= htmlspecialchars($dmError) ?></div><?php endif; ?>
                <p class="muted" style="font-size:13px;"><?= t('sup_dm_citizen_form_intro') ?></p>
                <form method="post" enctype="multipart/form-data" id="citizenDmForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <input type="hidden" name="message_type" id="sm_message_type" value="text">
                    <input type="hidden" name="voice_b64" id="sm_voice_b64" value="">
                    <input type="hidden" name="video_b64" id="sm_video_b64" value="">
                    <label><?= t('citizen_fb_name') ?></label>
                    <input type="text" name="citizen_dm_name" maxlength="150" value="<?= htmlspecialchars($report['caller_name'] ?? '') ?>">
                    <label><?= t('citizen_fb_phone') ?></label>
                    <input type="text" name="citizen_dm_phone" value="<?= htmlspecialchars($report['caller_phone'] ?? '') ?>" placeholder="09xxxxxxxx">
                    <label><?= t('sup_dm_citizen_message') ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                        <button type="button" id="smBtnEmoji" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);cursor:pointer;">😊 Emoji</button>
                        <label style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);cursor:pointer;">
                            📷 Image <input type="file" name="sm_file" id="smFileImage" accept="image/*" style="display:none;" onchange="smOnFile(this,'image')">
                        </label>
                        <button type="button" id="smBtnVoice" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);cursor:pointer;">🎤 Voice</button>
                        <button type="button" id="smBtnVideo" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);cursor:pointer;">🎬 Video</button>
                        <label style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);cursor:pointer;">
                            📎 File <input type="file" name="sm_file" id="smFileAny" accept="image/*,audio/*,video/*" style="display:none;" onchange="smOnFile(this,'auto')">
                        </label>
                    </div>
                    <div id="smEmojiPicker" style="display:none;flex-wrap:wrap;gap:4px;padding:8px;background:var(--panel-2);border:1px solid var(--border);border-radius:10px;margin-bottom:8px;max-width:320px;">
                        <?php
                        $emojis = ['😀','😂','😊','😍','🤔','👍','👎','👏','🙏','🔥','✅','❌','⚠️','🚨','📍','📞','💪','🙌','❤️','💙','🟢','🔴','⭐','🎉','📝','📷','🎤','🎬','🚗','🏥','👮','🚒'];
                        foreach ($emojis as $e) echo '<span style="font-size:22px;cursor:pointer;padding:4px;" onclick="smInsertEmoji(\''.$e.'\')">'.$e.'</span>';
                        ?>
                    </div>
                    <div id="smMediaPreview" style="display:none;font-size:12px;color:var(--muted);margin-bottom:8px;"></div>
                    <textarea name="citizen_dm" id="smMessage" rows="4" placeholder="<?= t_raw('sup_dm_citizen_placeholder') ?>"></textarea>
                    <label style="display:flex; align-items:flex-start; gap:10px; margin-top:12px; font-weight:normal; cursor:pointer;">
                        <input type="checkbox" name="citizen_dm_consent" value="1" required style="margin-top:4px; width:auto;">
                        <span><?= t('sup_dm_consent_label') ?></span>
                    </label>
                    <button type="submit" name="submit_citizen_dm" style="margin-top:12px;"><?= t('sup_dm_citizen_send') ?></button>
                </form>
                <script>
                function smInsertEmoji(e) {
                  var ta = document.getElementById('smMessage');
                  if (!ta) return;
                  ta.value += e;
                  document.getElementById('sm_message_type').value = 'emoji';
                  ta.focus();
                }
                document.getElementById('smBtnEmoji')?.addEventListener('click', function(){
                  var p = document.getElementById('smEmojiPicker');
                  p.style.display = p.style.display === 'flex' ? 'none' : 'flex';
                });
                function smOnFile(input, forced) {
                  var file = input.files && input.files[0];
                  if (!file) return;
                  var type = forced;
                  if (type === 'auto') {
                    if (file.type.startsWith('image/')) type = 'image';
                    else if (file.type.startsWith('audio/')) type = 'voice';
                    else if (file.type.startsWith('video/')) type = 'video';
                    else type = 'image';
                  }
                  document.getElementById('sm_message_type').value = type;
                  var other = input.id === 'smFileImage' ? document.getElementById('smFileAny') : document.getElementById('smFileImage');
                  if (other) other.value = '';
                  var prev = document.getElementById('smMediaPreview');
                  prev.style.display = 'block';
                  prev.innerHTML = '📎 ' + file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)';
                  if (type === 'image') prev.innerHTML += '<br><img src="'+URL.createObjectURL(file)+'" style="max-width:140px;border-radius:6px;margin-top:4px;">';
                }
                var smVoiceRec=null, smVoiceChunks=[], smVoiceStream=null;
                document.getElementById('smBtnVoice')?.addEventListener('click', async function(){
                  var btn=this;
                  if (smVoiceRec && smVoiceRec.state==='recording'){ smVoiceRec.stop(); btn.textContent='🎤 Voice'; return; }
                  try {
                    smVoiceStream = await navigator.mediaDevices.getUserMedia({audio:true});
                    smVoiceChunks=[];
                    smVoiceRec = new MediaRecorder(smVoiceStream);
                    smVoiceRec.ondataavailable = function(e){ if(e.data.size) smVoiceChunks.push(e.data); };
                    smVoiceRec.onstop = function(){
                      var blob=new Blob(smVoiceChunks,{type:'audio/webm'});
                      var r=new FileReader();
                      r.onloadend=function(){
                        document.getElementById('sm_voice_b64').value=(r.result||'').split(',')[1]||'';
                        document.getElementById('sm_message_type').value='voice';
                        document.getElementById('sm_video_b64').value='';
                        var prev=document.getElementById('smMediaPreview');
                        prev.style.display='block';
                        prev.innerHTML='🎤 Voice <audio controls src="'+URL.createObjectURL(blob)+'" style="display:block;margin-top:4px;max-width:220px;"></audio>';
                      };
                      r.readAsDataURL(blob);
                      smVoiceStream.getTracks().forEach(function(t){t.stop();});
                    };
                    smVoiceRec.start();
                    btn.textContent='⏹ Stop';
                  } catch(err){ alert('Mic: '+(err.message||err.name)); }
                });
                var smVideoRec=null, smVideoChunks=[], smVideoStream=null;
                document.getElementById('smBtnVideo')?.addEventListener('click', async function(){
                  var btn=this;
                  if (smVideoRec && smVideoRec.state==='recording'){ smVideoRec.stop(); btn.textContent='🎬 Video'; return; }
                  try {
                    smVideoStream = await navigator.mediaDevices.getUserMedia({audio:true,video:true});
                    smVideoChunks=[];
                    smVideoRec = new MediaRecorder(smVideoStream);
                    smVideoRec.ondataavailable = function(e){ if(e.data.size) smVideoChunks.push(e.data); };
                    smVideoRec.onstop = function(){
                      var blob=new Blob(smVideoChunks,{type:'video/webm'});
                      var r=new FileReader();
                      r.onloadend=function(){
                        document.getElementById('sm_video_b64').value=(r.result||'').split(',')[1]||'';
                        document.getElementById('sm_message_type').value='video';
                        document.getElementById('sm_voice_b64').value='';
                        var prev=document.getElementById('smMediaPreview');
                        prev.style.display='block';
                        prev.innerHTML='🎬 Video <video controls src="'+URL.createObjectURL(blob)+'" style="display:block;margin-top:4px;max-width:220px;max-height:130px;"></video>';
                      };
                      r.readAsDataURL(blob);
                      smVideoStream.getTracks().forEach(function(t){t.stop();});
                    };
                    smVideoRec.start();
                    btn.textContent='⏹ Stop';
                  } catch(err){ alert('Camera: '+(err.message||err.name)); }
                });
                </script>
            <?php endif; ?>

            <?php if (in_array($report['status'], ['solved','unsolved'], true)): ?>
                <?php if ($report['satisfaction_rating']): ?>
                    <div class="alert success"><?= t('rate_already') ?> <?= str_repeat('★', (int)$report['satisfaction_rating']) ?></div>
                <?php elseif ($rateSuccess): ?>
                    <div class="alert success"><?= t('rate_thanks') ?></div>
                <?php else: ?>
                    <hr style="border-color:var(--border); margin:20px 0;">
                    <h2 style="font-size:16px;"><?= t('rate_title') ?></h2>
                    <p class="muted" style="font-size:13px;"><?= t('rate_prompt') ?></p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                        <label><?= t('label_rating') ?></label>
                        <select name="rating" required>
                            <option value="">--</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></option>
                            <?php endfor; ?>
                        </select>
                        <textarea name="comment" placeholder="<?= t('label_feedback_message') ?>"></textarea>
                        <button type="submit" name="submit_rating"><?= t('rate_submit') ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn" href="index.php?lang=<?= $CURRENT_LANG ?>"><?= t('btn_back_home') ?></a>
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

<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>
