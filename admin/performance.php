<?php
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'supervisor']);

$rows = $pdo->query("SELECT u.id, u.full_name,
    COUNT(e.id) AS handled,
    SUM(e.status='solved') AS solved,
    SUM(e.status='unsolved') AS unsolved,
    AVG(e.response_time_minutes) AS avg_response,
    AVG(e.satisfaction_rating) AS avg_rating
    FROM users u
    LEFT JOIN events e ON e.operator_id = u.id
    WHERE u.role = 'operator'
    GROUP BY u.id ORDER BY handled DESC")->fetchAll();

$activeNav = 'performance';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('performance_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('performance_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <table>
            <tr>
                <th><?= t('perf_col_operator') ?></th><th><?= t('perf_col_handled') ?></th><th><?= t('perf_col_solved') ?></th>
                <th><?= t('perf_col_unsolved') ?></th><th><?= t('perf_col_avg_response') ?></th><th><?= t('perf_col_resolution_rate') ?></th><th><?= t('label_rating') ?></th>
            </tr>
            <?php foreach ($rows as $r): ?>
            <?php $rate = $r['handled'] > 0 ? round(($r['solved'] / $r['handled']) * 100) : 0; ?>
            <tr>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td class="mono"><?= (int)$r['handled'] ?></td>
                <td class="mono" style="color:var(--green)"><?= (int)$r['solved'] ?></td>
                <td class="mono" style="color:var(--red)"><?= (int)$r['unsolved'] ?></td>
                <td class="mono"><?= $r['avg_response'] ? round($r['avg_response']) : '—' ?></td>
                <td>
                    <div style="background:var(--panel-2); border-radius:20px; height:8px; width:100px; overflow:hidden; display:inline-block; vertical-align:middle;">
                        <div style="background:linear-gradient(90deg,var(--cyan),var(--violet)); height:100%; width:<?= $rate ?>%;"></div>
                    </div>
                    <span class="mono" style="font-size:11px;"><?= $rate ?>%</span>
                </td>
                <td><?= $r['avg_rating'] ? round($r['avg_rating'], 1) . ' ★' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7"><?= t('dash_no_reports') ?></td></tr><?php endif; ?>
        </table>
    </div>
</main>
</div>
</body>
</html>
