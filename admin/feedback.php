<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);
require __DIR__ . '/../includes/security.php';

$role = current_role();
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $message = trim($_POST['message'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($message !== '') {
        $stmt = $pdo->prepare("INSERT INTO feedback (user_id, message, rating) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $message, $rating ?: null]);
        $success = true;
    }
}

// Administrators can see everyone's feedback
$history = [];
if ($role === 'administrator') {
    $history = $pdo->query("SELECT f.*, u.full_name FROM feedback f LEFT JOIN users u ON u.id = f.user_id ORDER BY f.created_at DESC LIMIT 50")->fetchAll();
}

$activeNav = 'feedback';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('feedback_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('feedback_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('feedback_intro') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <?php if ($success): ?><div class="alert success"><?= t('feedback_thanks') ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label><?= t('label_rating') ?></label>
            <select name="rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>"><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></option>
                <?php endfor; ?>
            </select>
            <label><?= t('label_feedback_message') ?></label>
            <textarea name="message" required></textarea>
            <button type="submit"><?= t('btn_submit_feedback') ?></button>
        </form>
    </div>

    <?php if ($role === 'administrator'): ?>
    <div class="card">
        <table>
            <tr><th><?= t('label_full_name') ?></th><th><?= t('label_rating') ?></th><th><?= t('label_feedback_message') ?></th><th><?= t('col_date') ?></th></tr>
            <?php foreach ($history as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['full_name'] ?? '-') ?></td>
                <td><?= $h['rating'] ? str_repeat('★', (int)$h['rating']) : '-' ?></td>
                <td><?= htmlspecialchars($h['message']) ?></td>
                <td><?= htmlspecialchars($h['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="4"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
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
