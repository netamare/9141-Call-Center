<?php
/**
 * Public citizen feedback → saves + notifies administrator, operator, supervisor
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/lang.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/notifications.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS citizen_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tracking_code VARCHAR(32) DEFAULT NULL,
        name VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(32) DEFAULT NULL,
        rating TINYINT DEFAULT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$ok = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $code = trim($_POST['tracking_code'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $err = t_raw('error_required');
    } else {
        $stmt = $pdo->prepare("INSERT INTO citizen_feedback (tracking_code, name, phone, rating, message) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $code !== '' ? $code : null,
            $name !== '' ? $name : null,
            $phone !== '' ? $phone : null,
            ($rating >= 1 && $rating <= 5) ? $rating : null,
            $message
        ]);

        $stars = ($rating >= 1 && $rating <= 5) ? str_repeat('★', $rating) : '';
        $title = t_raw('citizen_fb_notify_title') . ($stars ? " ($stars)" : '');
        $body = trim(($name ? $name . ' · ' : '') . ($phone ? $phone . ' · ' : '') . ($code ? $code . ' · ' : '') . mb_substr($message, 0, 180));
        try {
            notify_roles($pdo, ['administrator', 'operator', 'supervisor'], null, 'citizen_feedback', $title, $body, false);
        } catch (Throwable $e) { /* ignore notify errors */ }

        $ok = true;
    }
}

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('citizen_fb_page_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= t('citizen_fb_heading') ?></div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">
    <div class="card">
        <?php if ($ok): ?>
            <div class="alert success"><?= t('citizen_fb_success') ?></div>
            <a class="btn" href="index.php"><?= t('btn_back_home') ?></a>
        <?php else: ?>
            <?php if ($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <p class="muted"><?= t('citizen_fb_intro') ?></p>
            <form method="post">
                <?= csrf_field() ?>
                <label><?= t('citizen_fb_name') ?></label>
                <input type="text" name="name" maxlength="150">
                <label><?= t('citizen_fb_phone') ?></label>
                <input type="text" name="phone" placeholder="09xxxxxxxx">
                <label><?= t('citizen_fb_tracking') ?></label>
                <input type="text" name="tracking_code" placeholder="9141-XXXXXX">
                <label><?= t('citizen_fb_rating') ?></label>
                <select name="rating">
                    <option value="">--</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option>
                    <?php endfor; ?>
                </select>
                <label><?= t('citizen_fb_message') ?> *</label>
                <textarea name="message" required rows="4" placeholder="<?= t_raw('citizen_fb_message_ph') ?>"></textarea>
                <button type="submit"><?= t('citizen_fb_submit') ?></button>
            </form>
            <p style="margin-top:16px;"><a class="btn" href="index.php"><?= t('btn_back_home') ?></a></p>
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

<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>
