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

    // Support both password_hash (9141 schema) and password (Laravel-style).
    // Using ?? instead of empty() so this can never throw an
    // "Undefined array key" warning, no matter which column exists.
    $hash = null;
    if ($user) {
        $hash = $user['password_hash'] ?? $user['password'] ?? null;
    }

    if ($user && ($user['status'] ?? '') === 'inactive') {
        $error = t_raw('login_error');
    } elseif ($user && $hash && password_verify($password, $hash)) {
        try {
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);
        } catch (Throwable $e) { /* columns may not exist */ }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_department_id'] = $user['department_id'] ?? null;
        try {
            require_once __DIR__ . '/../includes/activity.php';
            log_activity(
                $pdo,
                'login',
                ($user['full_name'] ?? $user['username']) . ' logged in',
                'user',
                (int)$user['id'],
                null,
                ['id' => (int)$user['id'], 'name' => $user['full_name'] ?? '', 'role' => $user['role']]
            );
        } catch (Throwable $e) { /* ignore */ }
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
<footer style="
    text-align: center;
    padding: 22px 16px;
    margin-top: 50px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-top: 1px solid #e2e8f0;
    font-family: system-ui, -apple-system, sans-serif;
">
    <div style="
        font-size: 13.5px;
        font-weight: 600;
        color: #334155;
        letter-spacing: 0.3px;
    ">
        © 2026 MNAN. All Rights Reserved.
    </div>
    <div style="
        margin-top: 6px;
        font-size: 12px;
        color: #64748b;
    ">
        Designed &amp; Developed by <span style="color:#0ea5e9; font-weight:600;">MNAN</span>
    </div>
    <div style="
        margin-top: 8px;
        font-size: 11px;
        color: #94a3b8;
    ">
        Adama City Administration · Call Center 9141
    </div>
</footer>
</body>
</html>
