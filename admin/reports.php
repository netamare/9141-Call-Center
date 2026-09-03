<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'supervisor', 'operator']);

$history = [];
try {
    $history = $pdo->query("SELECT rg.*, u.full_name FROM report_generations rg LEFT JOIN users u ON u.id = rg.generated_by ORDER BY rg.generated_at DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) {
    // table missing or old columns
}
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$cat_keys = ['cat_illegal', 'cat_security', 'cat_service', 'cat_emergency'];
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$activeNav = 'reports';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('reports_page_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('reports_page_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('reports_quick_heading') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('reports_quick_note') ?></p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="export_csv.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>">⬇ <?= t('reports_daily') ?></a>
            <a class="btn" href="export_csv.php?date_from=<?= date('Y-m-d', strtotime('-6 days')) ?>&date_to=<?= date('Y-m-d') ?>">⬇ <?= t('reports_weekly') ?></a>
            <a class="btn" href="export_csv.php?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-t') ?>">⬇ <?= t('reports_monthly') ?></a>
            <a class="btn btn-ghost" href="export_csv.php">⬇ <?= t('reports_export_csv') ?></a>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('reports_custom_heading') ?></h2>
        <form method="get" action="export_csv.php">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px;">
                <div><label><?= t('reports_date_from') ?></label><input type="date" name="date_from"></div>
                <div><label><?= t('reports_date_to') ?></label><input type="date" name="date_to"></div>
                <div>
                    <label><?= t('dash_filter_category') ?></label>
                    <select name="category">
                        <option value=""><?= t('dash_filter_all') ?></option>
                        <?php foreach ($categories as $i => $c): ?>
                            <option value="<?= $c['id'] ?>"><?= t(isset($cat_keys[$i]) ? $cat_keys[$i] : '') ?: htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?= t('dash_col_department') ?></label>
                    <select name="department">
                        <option value=""><?= t('dash_filter_all') ?></option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?= t('dash_filter_status') ?></label>
                    <select name="status">
                        <option value=""><?= t('dash_filter_all') ?></option>
                        <?php foreach (['new','assigned','ongoing','solved','unsolved'] as $s): ?>
                            <option value="<?= $s ?>"><?= t('status_' . $s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>🔎 <?= t('search_label') ?></label><input type="text" name="q" placeholder="<?= t('search_placeholder') ?>"></div>
            </div>
            <button type="submit">⬇ <?= t('reports_export_csv') ?></button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= t('reports_history') ?></h2>
        <table>
            <tr><th><?= t('reports_col_type') ?></th><th><?= t('reports_col_by') ?></th><th><?= t('reports_col_when') ?></th></tr>
            <?php foreach ($history as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['report_type']) ?></td>
                <td><?= htmlspecialchars($h['full_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($h['generated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="3"><?= t('reports_none') ?></td></tr><?php endif; ?>
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
