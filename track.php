<?php
require 'config.php';
require 'includes/lang.php';
require 'includes/security.php';
require 'includes/maps.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$report = null;
$attachments = [];
$rateSuccess = false;

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
</body>
</html>
