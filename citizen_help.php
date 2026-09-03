<?php
/**
 * Public citizen help request → saves + urgent notify to administrator, operator, supervisor
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
        status ENUM('new','seen','answered') NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

foreach ([
    'reply_message' => "ALTER TABLE citizen_help ADD COLUMN reply_message TEXT DEFAULT NULL",
    'replied_by'    => "ALTER TABLE citizen_help ADD COLUMN replied_by INT DEFAULT NULL",
    'replied_name'  => "ALTER TABLE citizen_help ADD COLUMN replied_name VARCHAR(150) DEFAULT NULL",
    'replied_at'    => "ALTER TABLE citizen_help ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL",
] as $col => $sql) {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM citizen_help LIKE " . $pdo->quote($col))->fetch();
        if (!$chk) $pdo->exec($sql);
    } catch (Throwable $e) {}
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
    if ($phone === '' || $message === '') {
        $err = t_raw('error_required');
    } else {
        $stmt = $pdo->prepare("INSERT INTO citizen_help (tracking_code, name, phone, message) VALUES (?,?,?,?)");
        $stmt->execute([
            $code !== '' ? $code : null,
            $name !== '' ? $name : null,
            $phone,
            $message
        ]);

        $title = t_raw('citizen_help_notify_title');
        $body = trim(($name ? $name . ' · ' : '') . $phone . ($code ? ' · ' . $code : '') . ' — ' . mb_substr($message, 0, 180));
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
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= t('citizen_help_heading_public') ?></div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">
    <div class="card">
        <?php if ($ok): ?>
            <div class="alert success"><?= t('citizen_help_success') ?></div>
            <a class="btn" href="index.php"><?= t('btn_back_home') ?></a>
        <?php else: ?>
            <?php if ($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <p class="muted"><?= t('citizen_help_intro') ?></p>
            <form method="post">
                <?= csrf_field() ?>
                <label><?= t('citizen_help_name') ?></label>
                <input type="text" name="name" maxlength="150">
                <label><?= t('citizen_help_phone') ?> *</label>
                <input type="text" name="phone" required placeholder="09xxxxxxxx">
                <label><?= t('citizen_help_tracking') ?></label>
                <input type="text" name="tracking_code" placeholder="9141-XXXXXX">
                <label><?= t('citizen_help_message') ?> *</label>
                <textarea name="message" required rows="4" placeholder="<?= t_raw('citizen_help_message_ph') ?>"></textarea>
                <button type="submit"><?= t('citizen_help_submit') ?></button>
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
                        <div style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($h['created_at']) ?> · <?= htmlspecialchars($h['status'] ?? '') ?></div>
                        <div style="margin-top:6px;"><strong><?= t('citizen_help_message') ?>:</strong><br><?= nl2br(htmlspecialchars($h['message'])) ?></div>
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

<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>
