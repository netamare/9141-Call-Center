<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator']);

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['critical','high','medium','low'] as $level) {
        $hours = max(1, (int) ($_POST['sla_' . $level] ?? 0));
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$hours, 'sla_hours_' . $level]);
    }
    $wordLimit = max(20, (int) ($_POST['description_word_limit'] ?? 150));
    $idleMinutes = max(2, (int) ($_POST['session_idle_minutes'] ?? 20));
    $alertMinutes = max(1, (int) ($_POST['operator_alert_minutes'] ?? 5));
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$wordLimit, 'description_word_limit']);
    $stmt->execute([$idleMinutes, 'session_idle_minutes']);
    $stmt->execute([$alertMinutes, 'operator_alert_minutes']);

    $smsFields = [
        'sms_enabled'        => !empty($_POST['sms_enabled']) ? '1' : '0',
        'sms_gateway_url'    => trim($_POST['sms_gateway_url'] ?? ''),
        'sms_gateway_method' => (strtoupper($_POST['sms_gateway_method'] ?? 'GET') === 'POST') ? 'POST' : 'GET',
        'sms_api_key'        => trim($_POST['sms_api_key'] ?? ''),
        'sms_sender_id'      => trim($_POST['sms_sender_id'] ?? ''),
        'sms_provider'       => trim($_POST['sms_provider'] ?? 'afromessage'),
        'sms_identifier'     => trim($_POST['sms_identifier'] ?? ''),
        'sms_callback_url'   => trim($_POST['sms_callback_url'] ?? ''),
    ];
    $upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($smsFields as $k => $v) {
        $upsert->execute([$k, $v]);
    }

    $message = t_raw('settings_saved');
}

$rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$sla = [];
$other = [];
foreach ($rows as $r) {
    if (strpos($r['setting_key'], 'sla_hours_') === 0) {
        $sla[str_replace('sla_hours_', '', $r['setting_key'])] = $r['setting_value'];
    } else {
        $other[$r['setting_key']] = $r['setting_value'];
    }
}

$sms = [
    'sms_enabled'        => $other['sms_enabled'] ?? '0',
    'sms_gateway_url'    => $other['sms_gateway_url'] ?? '',
    'sms_gateway_method' => $other['sms_gateway_method'] ?? 'GET',
    'sms_api_key'        => $other['sms_api_key'] ?? '',
    'sms_sender_id'      => $other['sms_sender_id'] ?? '9141',
    'sms_provider'       => $other['sms_provider'] ?? 'afromessage',
    'sms_identifier'     => $other['sms_identifier'] ?? '',
    'sms_callback_url'   => $other['sms_callback_url'] ?? '',
];
// Reused as hidden fields in every OTHER settings form on this page, so
// submitting one card never resets the SMS gateway fields (each form
// posts the full settings set — see the single POST handler above).
ob_start();
foreach ($sms as $k => $v) {
    echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">' . "\n";
}
$smsHiddenFields = ob_get_clean();

$activeNav = 'settings';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('settings_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('settings_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('settings_sla_heading') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('settings_sla_note') ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="description_word_limit" value="<?= htmlspecialchars($other['description_word_limit'] ?? 150) ?>">
            <input type="hidden" name="session_idle_minutes" value="<?= htmlspecialchars($other['session_idle_minutes'] ?? 20) ?>">
            <?= $smsHiddenFields ?>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px;">
                <div><label><span class="badge critical"><?= t('severity_critical') ?></span></label><input type="number" min="1" name="sla_critical" value="<?= htmlspecialchars($sla['critical'] ?? 1) ?>"></div>
                <div><label><span class="badge high"><?= t('severity_high') ?></span></label><input type="number" min="1" name="sla_high" value="<?= htmlspecialchars($sla['high'] ?? 4) ?>"></div>
                <div><label><span class="badge medium"><?= t('severity_medium') ?></span></label><input type="number" min="1" name="sla_medium" value="<?= htmlspecialchars($sla['medium'] ?? 24) ?>"></div>
                <div><label><span class="badge low"><?= t('severity_low') ?></span></label><input type="number" min="1" name="sla_low" value="<?= htmlspecialchars($sla['low'] ?? 72) ?>"></div>
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:15px;">System behavior</h2>
        <form method="post">
            <?= csrf_field() ?>
            <?php foreach (['critical','high','medium','low'] as $level): ?>
                <input type="hidden" name="sla_<?= $level ?>" value="<?= htmlspecialchars($sla[$level] ?? 24) ?>">
            <?php endforeach; ?>
            <?= $smsHiddenFields ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div><label>Description word limit (public form)</label><input type="number" min="20" name="description_word_limit" value="<?= htmlspecialchars($other['description_word_limit'] ?? 150) ?>"></div>
                <div><label>Session idle timeout (minutes)</label><input type="number" min="2" name="session_idle_minutes" value="<?= htmlspecialchars($other['session_idle_minutes'] ?? 20) ?>"></div>
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:15px;">⏳ <?= t('escalate_settings_heading') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('escalate_settings_note') ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <?php foreach (['critical','high','medium','low'] as $level): ?>
                <input type="hidden" name="sla_<?= $level ?>" value="<?= htmlspecialchars($sla[$level] ?? 24) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="description_word_limit" value="<?= htmlspecialchars($other['description_word_limit'] ?? 150) ?>">
            <input type="hidden" name="session_idle_minutes" value="<?= htmlspecialchars($other['session_idle_minutes'] ?? 20) ?>">
            <?= $smsHiddenFields ?>
            <div style="max-width:260px;">
                <label><?= t('escalate_settings_field') ?></label>
                <input type="number" min="1" name="operator_alert_minutes" value="<?= htmlspecialchars($other['operator_alert_minutes'] ?? 5) ?>">
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:15px;">📱 <?= t('settings_sms_heading') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('settings_sms_note') ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <?php foreach (['critical','high','medium','low'] as $level): ?>
                <input type="hidden" name="sla_<?= $level ?>" value="<?= htmlspecialchars($sla[$level] ?? 24) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="description_word_limit" value="<?= htmlspecialchars($other['description_word_limit'] ?? 150) ?>">
            <input type="hidden" name="session_idle_minutes" value="<?= htmlspecialchars($other['session_idle_minutes'] ?? 20) ?>">
            <input type="hidden" name="operator_alert_minutes" value="<?= htmlspecialchars($other['operator_alert_minutes'] ?? 5) ?>">

            <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
                <input type="checkbox" name="sms_enabled" value="1" style="width:auto;" <?= $sms['sms_enabled'] === '1' ? 'checked' : '' ?>>
                <?= t('settings_sms_enabled') ?>
            </label>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:14px; margin-top:10px;">
                <div>
                    <label><?= t('settings_sms_gateway_url') ?></label>
                    <input type="text" name="sms_gateway_url" placeholder="https://api.yourprovider.com/send?to={phone}&amp;msg={message}&amp;key={apikey}&amp;from={sender}" value="<?= htmlspecialchars($sms['sms_gateway_url']) ?>">
                    <p class="hint"><?= t('settings_sms_gateway_url_hint') ?></p>
                </div>
                <div>
                    <label><?= t('settings_sms_method') ?></label>
                    <select name="sms_gateway_method">
                        <option value="GET" <?= $sms['sms_gateway_method'] === 'GET' ? 'selected' : '' ?>>GET</option>
                        <option value="POST" <?= $sms['sms_gateway_method'] === 'POST' ? 'selected' : '' ?>>POST</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:10px;">
                <div><label><?= t('settings_sms_api_key') ?></label><input type="text" name="sms_api_key" value="<?= htmlspecialchars($sms['sms_api_key']) ?>"></div>
                <div><label><?= t('settings_sms_sender_id') ?></label><input type="text" name="sms_sender_id" value="<?= htmlspecialchars($sms['sms_sender_id']) ?>"></div>
                <div>
                    <label>Provider</label>
                    <select name="sms_provider">
                        <option value="afromessage" <?= ($sms['sms_provider'] ?? '') === 'afromessage' ? 'selected' : '' ?>>AfroMessage</option>
                        <option value="generic" <?= ($sms['sms_provider'] ?? '') === 'generic' ? 'selected' : '' ?>>Generic HTTP gateway</option>
                    </select>
                </div>
                <div>
                    <label>AfroMessage Identifier (from)</label>
                    <input type="text" name="sms_identifier" value="<?= htmlspecialchars($sms['sms_identifier'] ?? '') ?>" placeholder="YOUR_IDENTIFIER_ID">
                </div>
                <div style="grid-column:1/-1;">
                    <label>Delivery callback URL</label>
                    <input type="text" name="sms_callback_url" value="<?= htmlspecialchars($sms['sms_callback_url'] ?? '') ?>" placeholder="https://yourdomain.com/sms_callback.php">
                    <p class="hint">AfroMessage GET callback — status (DELIVRD / UNDELIV) updates sms_logs</p>
                </div>
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
        </form>
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
