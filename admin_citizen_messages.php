<?php
/**
 * Put this file in admin/ as citizen_messages.php
 * Lists public feedback + help for admin/operator/supervisor
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator', 'operator', 'supervisor']);
require __DIR__ . '/lang.php';

try {
    $feedback = $pdo->query("SELECT * FROM citizen_feedback ORDER BY created_at DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) { $feedback = []; }
try {
    $help = $pdo->query("SELECT * FROM citizen_help ORDER BY created_at DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) { $help = []; }

// mark help as seen
if (isset($_GET['seen']) && ctype_digit($_GET['seen'])) {
    try {
        $pdo->prepare("UPDATE citizen_help SET status='seen' WHERE id=?")->execute([(int)$_GET['seen']]);
    } catch (Throwable $e) {}
    header('Location: citizen_messages.php');
    exit;
}

$activeNav = 'citizen_messages';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lammii messages - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <h2>💬 Feedback lammii</h2>
    <div class="card">
        <table>
            <tr><th>Yeroo</th><th>Maqaa</th><th>Bilbila</th><th>Code</th><th>★</th><th>Yaada</th></tr>
            <?php foreach ($feedback as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['created_at']) ?></td>
                <td><?= htmlspecialchars($f['name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['tracking_code'] ?? '-') ?></td>
                <td><?= $f['rating'] ? (int)$f['rating'] : '-' ?></td>
                <td><?= htmlspecialchars($f['message']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$feedback): ?><tr><td colspan="6" class="muted">Hin jiru</td></tr><?php endif; ?>
        </table>
    </div>

    <h2 style="margin-top:24px;">🆘 Gargaarsa lammii</h2>
    <div class="card">
        <table>
            <tr><th>Yeroo</th><th>Status</th><th>Maqaa</th><th>Bilbila</th><th>Code</th><th>Gaaffii</th><th></th></tr>
            <?php foreach ($help as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['created_at']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($h['status']) ?></span></td>
                <td><?= htmlspecialchars($h['name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($h['phone']) ?></td>
                <td><?= htmlspecialchars($h['tracking_code'] ?? '-') ?></td>
                <td><?= htmlspecialchars($h['message']) ?></td>
                <td><?php if ($h['status']==='new'): ?><a href="?seen=<?= (int)$h['id'] ?>">Seen</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$help): ?><tr><td colspan="7" class="muted">Hin jiru</td></tr><?php endif; ?>
        </table>
    </div>
</main>
</div>
</body>
</html>
