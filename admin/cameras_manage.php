<?php
/**
 * admin/cameras_manage.php — full camera CRUD for administrators.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator']);

// Ensure cameras table exists and has the columns this page needs
function cameras_manage_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cameras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        location VARCHAR(255) DEFAULT NULL,
        stream_url TEXT DEFAULT NULL,
        stream_type ENUM('hls','http','rtsp','mjpeg','webrtc') NOT NULL DEFAULT 'hls',
        status VARCHAR(32) NOT NULL DEFAULT 'online',
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add missing columns if table already existed with older schema
    $cols = [
        'latitude'  => "ALTER TABLE cameras ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL",
        'longitude' => "ALTER TABLE cameras ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL",
        'stream_url'=> "ALTER TABLE cameras ADD COLUMN stream_url TEXT DEFAULT NULL",
        'status'    => "ALTER TABLE cameras ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'online'",
    ];
    foreach ($cols as $col => $sql) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM cameras LIKE " . $pdo->quote($col))->fetch();
            if (!$chk) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // Optional clip tracking table (used only for counts on this page)
    $pdo->exec("CREATE TABLE IF NOT EXISTS camera_clips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        camera_id INT NOT NULL,
        file_path VARCHAR(512) NOT NULL,
        duration_sec INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (camera_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

cameras_manage_ensure($pdo);

$editCam = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int) ($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $latitude   = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
        $longitude  = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
        $status     = in_array($_POST['status'] ?? '', ['online', 'offline'], true) ? $_POST['status'] : 'online';

        if ($name !== '' && $location !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cameras SET name=?, location=?, stream_url=?, latitude=?, longitude=?, status=? WHERE id=?");
                $stmt->execute([$name, $location, $stream_url ?: null, $latitude, $longitude, $status, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cameras (name, location, stream_url, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $location, $stream_url ?: null, $latitude, $longitude, $status]);
            }
        }
        header('Location: cameras_manage.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM cameras WHERE id=?")->execute([$id]);
        }
        header('Location: cameras_manage.php');
        exit;
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id=?");
    $stmt->execute([(int) $_GET['edit']]);
    $editCam = $stmt->fetch();
}

// Safe query — never reference columns that may not exist (ai_detections.camera_id)
try {
    $cameras = $pdo->query("SELECT c.*,
        0 AS detection_count,
        (SELECT COUNT(*) FROM camera_clips cl WHERE cl.camera_id = c.id) AS clip_count
        FROM cameras c ORDER BY c.name")->fetchAll();
} catch (Throwable $e) {
    // Fallback if camera_clips still missing
    $cameras = $pdo->query("SELECT c.*, 0 AS detection_count, 0 AS clip_count FROM cameras c ORDER BY c.name")->fetchAll();
}
$activeNav = 'cctv';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('cctv_tab_cameras') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('cctv_tab_cameras') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <p class="muted" style="margin-top:0;">
        <a href="cctv_monitoring.php?tab=cameras"><?= t('nav_cctv') ?></a>
        &nbsp;·&nbsp; <?= t('cctv_add_camera') ?>
    </p>

    <div class="card">
        <h2 style="font-size:15px;"><?= $editCam ? t('btn_edit') : t('cctv_add_camera') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editCam['id'] ?? '' ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label><?= t('cctv_camera_name') ?></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editCam['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label><?= t('cctv_camera_location') ?></label>
                    <input type="text" name="location" value="<?= htmlspecialchars($editCam['location'] ?? '') ?>" required>
                </div>
                <div style="grid-column:1 / -1;">
                    <label><?= t('cctv_camera_stream_url') ?></label>
                    <input type="text" name="stream_url" value="<?= htmlspecialchars($editCam['stream_url'] ?? '') ?>" placeholder="https://…">
                </div>
                <div>
                    <label>Latitude</label>
                    <input type="text" name="latitude" value="<?= htmlspecialchars($editCam['latitude'] ?? '') ?>" placeholder="8.54">
                </div>
                <div>
                    <label>Longitude</label>
                    <input type="text" name="longitude" value="<?= htmlspecialchars($editCam['longitude'] ?? '') ?>" placeholder="39.27">
                </div>
                <div>
                    <label><?= t('cctv_live') ?> / <?= t('cctv_offline') ?></label>
                    <select name="status">
                        <option value="online"  <?= ($editCam['status'] ?? 'online') === 'online'  ? 'selected' : '' ?>><?= t('cctv_live') ?></option>
                        <option value="offline" <?= ($editCam['status'] ?? '') === 'offline' ? 'selected' : '' ?>><?= t('cctv_offline') ?></option>
                    </select>
                </div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit"><?= t('cctv_save') ?></button>
                <?php if ($editCam): ?>
                    <a class="btn" href="cameras_manage.php" style="background:var(--panel-2); color:var(--text);"><?= t('btn_back_home') ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <tr>
                <th><?= t('cctv_camera_name') ?></th>
                <th><?= t('cctv_camera_location') ?></th>
                <th><?= t('cctv_live') ?></th>
                <th><?= t('cctv_tab_detections') ?></th>
                <th><?= t('cctv_tab_clips') ?></th>
                <th><?= t('dash_col_manage') ?></th>
            </tr>
            <?php foreach ($cameras as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['location'] ?? '-') ?></td>
                <td>
                    <span class="badge <?= $c['status'] === 'online' ? 'solved' : 'medium' ?>">
                        <?= $c['status'] === 'online' ? t('cctv_live') : t('cctv_offline') ?>
                    </span>
                </td>
                <td><?= (int) $c['detection_count'] ?></td>
                <td><?= (int) $c['clip_count'] ?></td>
                <td>
                    <a href="?edit=<?= (int) $c['id'] ?>"><?= t('btn_edit') ?></a>
                    &nbsp;
                    <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" style="background:none; color:var(--red); padding:0; margin:0; box-shadow:none; text-decoration:underline;"><?= t('btn_delete') ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$cameras): ?>
            <tr><td colspan="6" class="muted"><?= t('cctv_no_cameras') ?></td></tr>
            <?php endif; ?>
        </table>
    </div>
</main>
</div>
</body>
</html>