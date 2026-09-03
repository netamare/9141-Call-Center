<?php
/**
 * admin/activity.php — Recent Activity log (system-wide).
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/activity.php';
require_role(['administrator', 'operator', 'supervisor']);

activity_ensure_table($pdo);

$filter = trim($_GET['action'] ?? '');
$limit  = (int)($_GET['limit'] ?? 50);
if ($limit < 10) $limit = 10;
if ($limit > 200) $limit = 200;

// Show only the current user's own activity (me only)
$meId = (int)(current_user()['id'] ?? 0);
$activities = activity_recent($pdo, $limit, $filter !== '' ? $filter : null, $meId > 0 ? $meId : null);

// Distinct actions for filter dropdown (this user only)
if ($meId > 0) {
    $stmt = $pdo->prepare(
        "SELECT action, COUNT(*) AS c FROM activity_logs WHERE user_id = ? GROUP BY action ORDER BY c DESC"
    );
    $stmt->execute([$meId]);
    $actionList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $actionList = [];
}

$activeNav = 'activity';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('nav_activity') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<style>
.act-table { width:100%; border-collapse:collapse; font-size:13px; }
.act-table th, .act-table td { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
.act-table th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
.act-table tr:hover td { background:var(--panel-2); }
.act-action {
    display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600;
    background:rgba(59,130,246,.12); color:var(--cyan);
}
.act-action.login, .act-action.logout { background:rgba(16,185,129,.12); color:var(--green); }
.act-action.event_created, .act-action.camera_updated { background:rgba(245,158,11,.12); color:var(--amber); }
.act-action.status_change, .act-action.deleted { background:rgba(239,68,68,.12); color:var(--red); }
.act-time { font-family:var(--mono); font-size:12px; color:var(--muted); white-space:nowrap; }
.act-user { font-weight:600; }
.act-role { font-size:11px; color:var(--muted); }
.act-empty { text-align:center; padding:40px; color:var(--muted); }
</style>
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('nav_activity') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <p class="muted" style="margin-top:0;"><?= t('activity_intro') ?></p>

    <div class="card" style="margin-bottom:16px;">
        <form method="get" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div>
                <label style="display:block; font-size:12px; color:var(--muted); margin-bottom:4px;"><?= t('activity_filter_action') ?></label>
                <select name="action" style="padding:8px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text);">
                    <option value=""><?= t('dash_filter_all') ?></option>
                    <?php foreach ($actionList as $a): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filter === $a['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['action']) ?> (<?= (int)$a['c'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; color:var(--muted); margin-bottom:4px;"><?= t('activity_limit') ?></label>
                <select name="limit" style="padding:8px 10px; border-radius:8px; border:1px solid var(--border); background:var(--panel-2); color:var(--text);">
                    <?php foreach ([25, 50, 100, 200] as $n): ?>
                        <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn" style="padding:8px 16px;"><?= t('btn_filter') ?: 'Filter' ?></button>
        </form>
    </div>

    <div class="card">
        <?php if (!$activities): ?>
            <div class="act-empty"><?= t('activity_empty') ?></div>
        <?php else: ?>
            <table class="act-table">
                <thead>
                    <tr>
                        <th><?= t('activity_col_time') ?></th>
                        <th><?= t('activity_col_user') ?></th>
                        <th><?= t('activity_col_action') ?></th>
                        <th><?= t('activity_col_summary') ?></th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activities as $row): ?>
                    <tr>
                        <td class="act-time"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td>
                            <div class="act-user"><?= htmlspecialchars($row['user_name'] ?? '—') ?></div>
                            <?php if (!empty($row['role'])): ?>
                                <div class="act-role"><?= htmlspecialchars($row['role']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="act-action <?= htmlspecialchars($row['action']) ?>">
                                <?= htmlspecialchars($row['action']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['summary']) ?>
                            <?php if (!empty($row['entity_type']) && !empty($row['entity_id'])): ?>
                                <span class="act-role"> · <?= htmlspecialchars($row['entity_type']) ?> #<?= (int)$row['entity_id'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['details'])): ?>
                                <div class="act-role" style="margin-top:4px;"><?= htmlspecialchars($row['details']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="act-time"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
