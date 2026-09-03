<?php
/**
 * admin/citizen_messages.php
 * Public citizen feedback + help. Operator/admin/supervisor can reply to help requests.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator', 'operator', 'supervisor']);

$role = current_role();
$can_reply_help = in_array($role, ['administrator', 'operator', 'supervisor'], true);

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

foreach ([
    'reply_message' => "ALTER TABLE citizen_help ADD COLUMN reply_message TEXT DEFAULT NULL",
    'replied_by'    => "ALTER TABLE citizen_help ADD COLUMN replied_by INT DEFAULT NULL",
    'replied_name'  => "ALTER TABLE citizen_help ADD COLUMN replied_name VARCHAR(150) DEFAULT NULL",
    'replied_at'    => "ALTER TABLE citizen_help ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL",
] as $col => $sql) {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM citizen_help LIKE " . $pdo->quote($col))->fetch();
        if (!$chk) $pdo->exec($sql);
    } catch (Throwable $e) {}
}

$flash = null;
$flashErr = null;

if (isset($_GET['seen']) && ctype_digit((string)$_GET['seen'])) {
    try {
        $pdo->prepare("UPDATE citizen_help SET status='seen' WHERE id=? AND status='new'")->execute([(int)$_GET['seen']]);
    } catch (Throwable $e) {}
    header('Location: citizen_messages.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply_help' && $can_reply_help) {
    verify_csrf();
    $hid = (int)($_POST['help_id'] ?? 0);
    $reply = trim($_POST['reply_message'] ?? '');
    if ($hid > 0 && $reply !== '') {
        $u = current_user();
        $uid = (int)($u['id'] ?? $_SESSION['user_id'] ?? 0);
        $uname = $u['name'] ?? ($u['full_name'] ?? ($_SESSION['user_name'] ?? 'Staff'));
        try {
            $stmt = $pdo->prepare(
                "UPDATE citizen_help
                 SET reply_message = ?, replied_by = ?, replied_name = ?, replied_at = NOW(), status = 'answered'
                 WHERE id = ?"
            );
            $stmt->execute([$reply, $uid ?: null, $uname, $hid]);
            $flash = t_raw('citizen_help_reply_saved');

            try {
                $row = $pdo->prepare("SELECT phone, tracking_code FROM citizen_help WHERE id = ?");
                $row->execute([$hid]);
                $hrow = $row->fetch(PDO::FETCH_ASSOC);
                if ($hrow && !empty($hrow['phone'])) {
                    require_once __DIR__ . '/../includes/sms.php';
                    if (function_exists('send_sms')) {
                        send_sms($pdo, $hrow['phone'], '9141: ' . mb_substr($reply, 0, 140), null);
                    }
                }
            } catch (Throwable $e) {}

            try {
                require_once __DIR__ . '/../includes/activity.php';
                log_activity($pdo, 'help_reply', 'Replied to citizen help #' . $hid, 'citizen_help', $hid, mb_substr($reply, 0, 200));
            } catch (Throwable $e) {}
        } catch (Throwable $e) {
            $flashErr = t_raw('error_required');
        }
    } else {
        $flashErr = t_raw('error_required');
    }
}

try {
    $feedback = $pdo->query("SELECT * FROM citizen_feedback ORDER BY created_at DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $feedback = [];
}
try {
    $help = $pdo->query("SELECT * FROM citizen_help ORDER BY created_at DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $help = [];
}

$activeNav = 'citizen_messages';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('nav_citizen_messages') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<style>
.badge-new { background:rgba(239,68,68,.15); color:var(--red); padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; }
.badge-seen { background:rgba(59,130,246,.12); color:var(--cyan); padding:2px 8px; border-radius:6px; font-size:11px; }
.badge-answered { background:rgba(16,185,129,.12); color:var(--green); padding:2px 8px; border-radius:6px; font-size:11px; }
.msg-table { width:100%; border-collapse:collapse; font-size:13px; }
.msg-table th, .msg-table td { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
.msg-table th { color:var(--muted); font-size:12px; text-transform:uppercase; }
.msg-table tr:hover td { background:var(--panel-2); }
.help-reply-box { margin-top:8px; padding:10px; border:1px solid var(--border); border-radius:8px; background:var(--panel-2); }
.help-reply-box textarea { width:100%; min-height:70px; margin-top:6px; }
.staff-reply { margin-top:8px; padding:8px 10px; border-left:3px solid var(--green); background:rgba(16,185,129,.08); border-radius:6px; font-size:13px; }
</style>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('nav_citizen_messages') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>
    <p class="muted" style="margin-top:0;"><?= t('citizen_messages_intro') ?></p>

    <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <h3 style="margin:20px 0 10px;">💬 <?= t('citizen_feedback_heading') ?> (<?= count($feedback) ?>)</h3>
    <div class="card">
        <table class="msg-table">
            <thead>
                <tr>
                    <th><?= t('activity_col_time') ?></th>
                    <th><?= t('label_full_name') ?></th>
                    <th><?= t('label_phone') ?></th>
                    <th>Code</th>
                    <th>★</th>
                    <th><?= t('label_feedback_message') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($feedback as $f): ?>
                <tr>
                    <td style="white-space:nowrap; font-family:var(--mono); font-size:12px;"><?= htmlspecialchars($f['created_at']) ?></td>
                    <td><?= htmlspecialchars($f['name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['tracking_code'] ?? '—') ?></td>
                    <td><?= !empty($f['rating']) ? str_repeat('★', (int)$f['rating']) : '—' ?></td>
                    <td><?= nl2br(htmlspecialchars($f['message'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$feedback): ?>
                <tr><td colspan="6" class="muted" style="text-align:center; padding:24px;"><?= t('citizen_feedback_empty') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 style="margin:28px 0 10px;">🆘 <?= t('citizen_help_heading') ?> (<?= count($help) ?>)</h3>
    <p class="muted" style="font-size:13px;"><?= t('citizen_help_reply_intro') ?></p>
    <div class="card">
        <table class="msg-table">
            <thead>
                <tr>
                    <th><?= t('activity_col_time') ?></th>
                    <th>Status</th>
                    <th><?= t('label_full_name') ?></th>
                    <th><?= t('label_phone') ?></th>
                    <th>Code</th>
                    <th><?= t('label_feedback_message') ?></th>
                    <th><?= t('citizen_help_reply_col') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($help as $h): ?>
                <?php
                $st = $h['status'] ?? 'new';
                $badge = $st === 'new' ? 'badge-new' : ($st === 'answered' ? 'badge-answered' : 'badge-seen');
                $hid = (int)$h['id'];
                ?>
                <tr>
                    <td style="white-space:nowrap; font-family:var(--mono); font-size:12px;"><?= htmlspecialchars($h['created_at']) ?></td>
                    <td><span class="<?= $badge ?>"><?= htmlspecialchars($st) ?></span></td>
                    <td><?= htmlspecialchars($h['name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($h['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($h['tracking_code'] ?? '—') ?></td>
                    <td>
                        <?= nl2br(htmlspecialchars($h['message'])) ?>
                        <?php if ($st === 'new'): ?>
                            <div style="margin-top:6px;"><a href="?seen=<?= $hid ?>"><?= t('citizen_mark_seen') ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td style="min-width:220px;">
                        <?php if (!empty($h['reply_message'])): ?>
                            <div class="staff-reply">
                                <div style="font-size:11px; color:var(--muted);">
                                    <?= htmlspecialchars($h['replied_name'] ?? 'Staff') ?>
                                    · <?= htmlspecialchars($h['replied_at'] ?? '') ?>
                                </div>
                                <div><?= nl2br(htmlspecialchars($h['reply_message'])) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_reply_help): ?>
                            <div class="help-reply-box">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reply_help">
                                    <input type="hidden" name="help_id" value="<?= $hid ?>">
                                    <label style="font-size:12px;"><?= t('citizen_help_reply_label') ?></label>
                                    <textarea name="reply_message" required placeholder="<?= t_raw('citizen_help_reply_ph') ?>"><?= htmlspecialchars($h['reply_message'] ?? '') ?></textarea>
                                    <button type="submit" style="margin-top:6px;"><?= t('citizen_help_reply_send') ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$help): ?>
                <tr><td colspan="7" class="muted" style="text-align:center; padding:24px;"><?= t('citizen_help_empty') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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
