<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

$faqs = [
    ['q' => 'How do I register a new event from a phone call?', 'a' => 'Go to Event Management → New Event, fill in the caller\'s details and description, and submit. A tracking code is generated automatically.'],
    ['q' => 'How do I escalate an event to a department?', 'a' => 'Open the event from the Event List, and use the "Escalate to department" panel to assign it. The department officer is notified automatically.'],
    ['q' => 'What does the overdue (SLA) badge mean?', 'a' => 'Each priority level has a target response time (configurable in Settings). If an event stays open past that target, it is flagged overdue on the dashboard.'],
    ['q' => 'How do I change my password?', 'a' => 'Ask an Administrator to reset it for you from the Users page — self-service password change is not yet available.'],
    ['q' => 'Why don\'t I see the Users or Settings menu?', 'a' => 'Those pages are restricted to the Administrator role, matching the system\'s access control policy.'],
    ['q' => 'How does the operator alarm work?', 'a' => 'New reports create a silent in-app notification. If an event stays unhandled (status still "new") for the configured time (default 5 minutes), an escalating alert is sent. Only Operators hear a short ~30-second audible siren for that escalating alert. Administrators see the notification but do not get the sound.'],
];

$activeNav = 'help';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('help_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('help_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('help_intro') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <?php foreach ($faqs as $f): ?>
    <div class="card">
        <h2 style="font-size:14.5px; margin-bottom:8px;">❓ <?= htmlspecialchars($f['q']) ?></h2>
        <p class="muted" style="font-size:13px; margin:0;"><?= htmlspecialchars($f['a']) ?></p>
    </div>
    <?php endforeach; ?>

    <div class="card" style="text-align:center;">
        <p><?= t('help_contact') ?></p>
        <a class="btn" href="feedback.php"><?= t('nav_feedback') ?></a>
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
