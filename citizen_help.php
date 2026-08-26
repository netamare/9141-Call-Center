<?php
/**
 * Public help → saves + notifies administrator, operator, supervisor (urgent)
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

$ok = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $code = trim($_POST['tracking_code'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($phone === '' || $message === '') {
        $err = t_raw('error_required');
    } elseif (function_exists('is_valid_et_phone') && !is_valid_et_phone($phone)) {
        $err = t_raw('error_phone_format');
    } else {
        $stmt = $pdo->prepare("INSERT INTO citizen_help (tracking_code, name, phone, message) VALUES (?,?,?,?)");
        $stmt->execute([
            $code !== '' ? $code : null,
            $name !== '' ? $name : null,
            $phone,
            $message
        ]);

        // Urgent notify to admin + operator + supervisor
        $title = '🆘 Gargaarsa lammii';
        $body = trim(($name ? $name . ' · ' : '') . $phone . ($code ? ' · ' . $code : '') . ' — ' . mb_substr($message, 0, 180));
        try {
            notify_roles($pdo, ['administrator', 'operator', 'supervisor'], null, 'citizen_help', $title, $body, true);
        } catch (Throwable $e) {}

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
<title>Gargaarsa - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title">🆘 Gargaarsa / Help</div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">
    <div class="card">
        <?php if ($ok): ?>
            <div class="alert success">Gaaffiin kee operator fi admin-tti ergameera. Deebii eegi.</div>
            <a class="btn" href="index.php"><?= t('btn_back_home') ?></a>
        <?php else: ?>
            <?php if ($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <p class="muted">Gaaffiin kee admin fi operator-ootaaf (urgent) ni ergamti.</p>
            <form method="post">
                <?= csrf_field() ?>
                <label>Maqaa</label>
                <input type="text" name="name" maxlength="150">
                <label>Bilbila *</label>
                <input type="text" name="phone" required placeholder="09xxxxxxxx">
                <label>Koodii hordoffii (yoo qabaatte)</label>
                <input type="text" name="tracking_code" placeholder="9141-XXXXXX">
                <label>Gaaffii / gargaarsa *</label>
                <textarea name="message" required rows="4" placeholder="Maal si gargaarraa..."></textarea>
                <button type="submit">Gara operator ergi</button>
            </form>
            <p style="margin-top:16px;"><a class="btn" href="index.php"><?= t('btn_back_home') ?></a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
