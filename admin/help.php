<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

$faqs = [
    ['q' => 'How do I register a new event from a phone call?', 'a' => 'Go to Event Management → New Event, fill in the caller\'s details and description, and submit. A tracking code is generated automatically.'],
    ['q' => 'How do I escalate an event to a department?', 'a' => 'Open the event from the Event List, and use the "Escalate to department" panel to assign it. The department officer is notified automatically.'],
    ['q' => 'What does the overdue (SLA) badge mean?', 'a' => 'Each priority level has a target response time (configurable in Settings). If an event stays open past that target, it is flagged overdue on the dashboard.'],
    ['q' => 'How do I change my password?', 'a' => 'Ask an Administrator to reset it for you from the Users page — self-service password change is not yet available.'],
    ['q' => 'Why don\'t I see the Users or Settings menu?', 'a' => 'Those pages are restricted to the Administrator role, matching the system\'s access control policy.'],
    ['q' => 'How does the operator alarm work?', 'a' => 'When a new high or critical priority event comes in, or one is escalated, Administrators and Operators get an urgent notification with an audible alarm until it is marked read.'],
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
</body>
</html>
