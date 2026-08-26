<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/security.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if (isset($_GET['timeout'])) {
    $error = 'Your session expired due to inactivity. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // No lockout — unlimited login attempts
    if ($user && $user['status'] === 'inactive') {
        $error = t_raw('login_error');
    } elseif ($user && password_verify($password, $user['password_hash'])) {
        // Clear any old lock data if columns exist
        try {
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);
        } catch (Throwable $e) { /* columns may not exist */ }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_department_id'] = $user['department_id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = t_raw('login_error');
    }
}

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('login_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-logo-row">
            <img src="../assets/logo-adama.png" alt="Adama City Administration emblem">
            <div class="auth-eyebrow">Adama City Administration</div>
            <div class="auth-title"><?= t('login_title') ?></div>
            <div class="auth-subtitle"><?= t('site_subtitle') ?></div>
            <div class="auth-roles">
                <span><?= t('role_administrator') ?></span>
                <span><?= t('role_operator') ?></span>
                <span><?= t('role_supervisor') ?></span>
                <span><?= t('role_department_officer') ?></span>
                <span><?= t('role_camera_operator') ?></span>
            </div>
        </div>
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label><?= t('label_username') ?></label>
            <input type="text" name="username" required autofocus>
            <label><?= t('label_password') ?></label>
            <input type="password" name="password" required>
            <button type="submit"><?= t('btn_login') ?></button>
        </form>
        <p style="font-size:12.5px;color:var(--faint);text-align:center;margin-top:16px;"><?= t('login_default_hint') ?></p>
        <div style="text-align:center; margin-top:14px;"><?php render_lang_switcher(); ?></div>
    </div>
</div>
</body>
</html>
