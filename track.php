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
$dmSuccess = false;
$dmError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_citizen_dm'])) {
    verify_csrf();
    $code = trim($_POST['code'] ?? $code);
    $dmText = trim($_POST['citizen_dm'] ?? '');
    $dmName = trim($_POST['citizen_dm_name'] ?? '');
    $dmPhone = trim($_POST['citizen_dm_phone'] ?? '');
    $dmConsent = !empty($_POST['citizen_dm_consent']);
    if (!$dmConsent) {
        $dmError = t_raw('sup_dm_consent_required');
    } elseif ($code !== '' && $dmText !== '') {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE tracking_code = ?");
        $stmt->execute([$code]);
        $ev = $stmt->fetch();
        // Any time while case is still open (not solved/unsolved)
        $stillOpen = $ev && !in_array($ev['status'] ?? '', ['solved', 'unsolved'], true);
        if ($ev && $stillOpen) {
            if (citizen_message_to_supervisor($pdo, (int)$ev['id'], $dmText, $dmName, $dmPhone)) {
                $dmSuccess = true;
                try {
                    require_once __DIR__ . '/includes/notifications.php';
                    $title = t_raw('sup_dm_from_public_notify');
                    $body = trim(($dmName ? $dmName . ' · ' : '') . ($dmPhone ? $dmPhone . ' · ' : '') . $code . ' — ' . mb_substr($dmText, 0, 160));
                    // Direct message: supervisor only (not admin/operator)
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
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="Adama City Administration emblem" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= t('track_title') ?></div>
            <div class="brand-subtitle"><?= t('site_subtitle') ?></div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
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
                    <div style="margin-top:6px; line-height:1.5;"><?= nl2br(htmlspecialchars($sm['message'])) ?></div>
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
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <label><?= t('citizen_fb_name') ?></label>
                    <input type="text" name="citizen_dm_name" maxlength="150" value="<?= htmlspecialchars($report['caller_name'] ?? '') ?>">
                    <label><?= t('citizen_fb_phone') ?></label>
                    <input type="text" name="citizen_dm_phone" value="<?= htmlspecialchars($report['caller_phone'] ?? '') ?>" placeholder="09xxxxxxxx">
                    <label><?= t('sup_dm_citizen_message') ?> *</label>
                    <textarea name="citizen_dm" required rows="4" placeholder="<?= t_raw('sup_dm_citizen_placeholder') ?>"></textarea>
                    <label style="display:flex; align-items:flex-start; gap:10px; margin-top:12px; font-weight:normal; cursor:pointer;">
                        <input type="checkbox" name="citizen_dm_consent" value="1" required style="margin-top:4px; width:auto;">
                        <span><?= t('sup_dm_consent_label') ?></span>
                    </label>
                    <button type="submit" name="submit_citizen_dm" style="margin-top:12px;"><?= t('sup_dm_citizen_send') ?></button>
                </form>
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
