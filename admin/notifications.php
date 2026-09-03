<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/notifications.php';

if (isset($_GET['mark_read'])) {
    mark_all_read($pdo, $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

$items = get_recent_notifications($pdo, $_SESSION['user_id'], 100);

$activeNav = 'notifications';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('nav_notifications') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('nav_notifications') ?></h2>
        <div class="topbar-controls">
            <?php render_topbar_controls(); ?>
            <a class="btn" href="?mark_read=1"><?= t('notif_mark_read') ?></a>
        </div>
    </div>

    <div class="card">
        <?php if (!$items): ?>
            <div class="notif-empty"><?= t('notif_empty') ?></div>
        <?php endif; ?>
        <?php foreach ($items as $n): ?>
            <a class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" style="display:block; border-radius:8px; margin-bottom:6px;"
               href="<?= $n['event_id'] ? 'report.php?id=' . $n['event_id'] : '#' ?>">
                <div class="nt-title"><?= $n['is_urgent'] ? '🚨 ' : '' ?><?= htmlspecialchars($n['title']) ?></div>
                <?php if ($n['message']): ?><div class="nt-msg"><?= htmlspecialchars($n['message']) ?></div><?php endif; ?>
                <div class="nt-time"><?= htmlspecialchars($n['created_at']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</main>
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
