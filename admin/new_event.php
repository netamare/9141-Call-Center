<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/notifications.php';
require __DIR__ . '/../includes/maps.php';
require_role(['administrator', 'operator']);

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency'];
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$wordLimitStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'description_word_limit'");
$wordLimitStmt->execute();
$WORD_LIMIT = (int) ($wordLimitStmt->fetchColumn() ?: 150);

$success = null;
$error = null;
$new_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $category_id = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $caller_name = trim($_POST['caller_name'] ?? '');
    $caller_phone = trim($_POST['caller_phone'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'unspecified';
    $address = trim($_POST['address'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $latitude = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
    $longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
    $priority = in_array($_POST['priority'] ?? '', ['low','medium','high','critical']) ? $_POST['priority'] : 'medium';
    $dept_id = (int) ($_POST['department_id'] ?? 0) ?: null;

    if ($category_id <= 0 || $description === '') {
        $error = t_raw('error_required');
    } elseif (!is_valid_et_phone($caller_phone)) {
        $error = t_raw('error_phone_format');
    } elseif (str_word_count($description) > $WORD_LIMIT) {
        $error = sprintf(t_raw('error_word_limit'), $WORD_LIMIT);
    } else {
        $tracking_code = generate_tracking_code();
        $status = $dept_id ? 'assigned' : 'new';

        $stmt = $pdo->prepare("INSERT INTO events (tracking_code, category_id, caller_name, caller_phone, gender, address, location, latitude, longitude, description, priority, status, assigned_department_id, operator_id)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tracking_code, $category_id, $caller_name ?: null, $caller_phone ?: null, $gender, $address ?: null, $location ?: null, $latitude, $longitude, $description, $priority, $status, $dept_id, $_SESSION['user_id']]);
        $new_id = $pdo->lastInsertId();

        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'created', 'Registered by call center operator', ?)");
        $log->execute([$new_id, $_SESSION['user_id']]);

        $catName = '';
        foreach ($categories as $c) { if ($c['id'] == $category_id) { $catName = $c['name']; break; } }
        notify_new_event($pdo, $new_id, $priority, $catName, $tracking_code);
        if ($dept_id) {
            notify_escalation($pdo, $new_id, $dept_id, $tracking_code, $priority);
            // Same SMS-to-department alert used by the manual "Escalate" action on
            // report.php — fires here too so a department assigned right at intake
            // (not just one escalated later) gets the text immediately.
            notify_department_escalation_sms($pdo, $dept_id, $new_id, $tracking_code, $catName, $priority);
        }

        $success = $tracking_code;
    }
}

$activeNav = 'new_event';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('new_event_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<?php leaflet_assets(); ?>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <h2 style="margin:0;"><?= t('new_event_title') ?></h2>
            <div class="muted" style="font-size:13px; margin-top:4px;"><?= t('new_event_subtitle') ?></div>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <?php if ($success): ?>
        <div class="card">
            <div class="alert success"><?= t('new_event_success') ?> <strong class="mono"><?= htmlspecialchars($success) ?></strong></div>
            <a class="btn" href="new_event.php"><?= t('nav_new_event') ?></a>
            <a class="btn" href="report.php?id=<?= $new_id ?>"><?= t('dash_col_manage') ?></a>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label><?= t('label_category') ?></label>
                    <select name="category_id" required>
                        <option value=""><?= t('select_category') ?></option>
                        <?php foreach ($categories as $i => $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['icon'] ?? '') ?> <?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?= t('label_severity') ?></label>
                    <select name="priority">
                        <option value="low"><?= t('severity_low') ?></option>
                        <option value="medium" selected><?= t('severity_medium') ?></option>
                        <option value="high"><?= t('severity_high') ?></option>
                        <option value="critical"><?= t('severity_critical') ?></option>
                    </select>
                </div>
            </div>

            <label><?= t('label_description') ?></label>
            <textarea name="description" id="descField" required placeholder="<?= t('placeholder_description') ?>" oninput="updateWordCount()"></textarea>
            <div class="hint" id="wordCountHint">0 / <?= $WORD_LIMIT ?> words</div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div><label><?= t('label_name') ?></label><input type="text" name="caller_name"></div>
                <div><label><?= t('label_phone') ?></label><input type="tel" name="caller_phone" placeholder="<?= t('placeholder_phone') ?>" pattern="^(?:\+251|0)9\d{8}$" title="<?= htmlspecialchars(t_raw('phone_format_hint')) ?>"></div>
                <div><label><?= t('label_gender') ?></label>
                    <select name="gender">
                        <option value="unspecified" selected><?= t('gender_unspecified') ?></option>
                        <option value="male"><?= t('gender_male') ?></option>
                        <option value="female"><?= t('gender_female') ?></option>
                    </select>
                </div>
                <div><label><?= t('label_location') ?></label><input type="text" name="location" placeholder="<?= t('placeholder_location') ?>"></div>
                <div><label><?= t('label_address') ?></label><input type="text" name="address"></div>
            </div>

            <?php render_location_picker('newEventMap'); ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
                <div>
                    <label><?= t('escalate_to_department') ?></label>
                    <select name="department_id">
                        <option value="">-- <?= t('dash_filter_all') ?> --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit"><?= t('btn_register_event') ?></button>
        </form>
    </div>
</main>
</div>
<script>
const WORD_LIMIT = <?= $WORD_LIMIT ?>;
function updateWordCount() {
    const text = document.getElementById('descField').value.trim();
    const words = text === '' ? 0 : text.split(/\s+/).length;
    const hint = document.getElementById('wordCountHint');
    hint.textContent = words + ' / ' + WORD_LIMIT + ' words';
    hint.style.color = words > WORD_LIMIT ? 'var(--red)' : '';
}
</script>
</body>
</html>
