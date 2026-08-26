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

        // Notify admin + operator + supervisor (bell in admin panel)
        $stars = ($rating >= 1 && $rating <= 5) ? str_repeat('★', $rating) : '';
        $title = '💬 Feedback lammii' . ($stars ? " ($stars)" : '');
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
<title>Feedback - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title">💬 Feedback / Yaada</div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">
    <div class="card">
        <?php if ($ok): ?>
            <div class="alert success">Galatoomaa — yaadni kee operator fi admin-tti ergameera.</div>
            <a class="btn" href="index.php"><?= t('btn_back_home') ?></a>
        <?php else: ?>
            <?php if ($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <p class="muted">Yaada kee admin fi operator-ootaaf ni ergamti.</p>
            <form method="post">
                <?= csrf_field() ?>
                <label>Maqaa (optional)</label>
                <input type="text" name="name" maxlength="150">
                <label>Bilbila (optional)</label>
                <input type="text" name="phone" placeholder="09xxxxxxxx">
                <label>Koodii hordoffii (optional)</label>
                <input type="text" name="tracking_code" placeholder="9141-XXXXXX">
                <label>Rating</label>
                <select name="rating">
                    <option value="">--</option>
                    <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?= $i ?>"><?= str_repeat('★',$i) ?></option>
                    <?php endfor; ?>
                </select>
                <label>Yaada *</label>
                <textarea name="message" required rows="4" placeholder="Yaada kee barreessi..."></textarea>
                <button type="submit">Ergi</button>
            </form>
            <p style="margin-top:16px;"><a class="btn" href="index.php"><?= t('btn_back_home') ?></a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
