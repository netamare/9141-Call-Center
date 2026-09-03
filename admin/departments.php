<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator']);

$editDept = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE departments SET name=?, contact_phone=?, contact_email=? WHERE id=?");
                $stmt->execute([$name, $phone ?: null, $email ?: null, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO departments (name, contact_phone, contact_email) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone ?: null, $email ?: null]);
            }
        }
        header('Location: departments.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id=?");
        $stmt->execute([$id]);
        header('Location: departments.php');
        exit;
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
    $stmt->execute([(int) $_GET['edit']]);
    $editDept = $stmt->fetch();
}

$departments = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM events WHERE assigned_department_id = d.id) AS event_count FROM departments d ORDER BY d.name")->fetchAll();

$activeNav = 'departments';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('departments_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('departments_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= $editDept ? t('dept_edit') : t('dept_add') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editDept['id'] ?? '' ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
                <div><label><?= t('label_dept_name') ?></label><input type="text" name="name" value="<?= htmlspecialchars($editDept['name'] ?? '') ?>" required></div>
                <div><label><?= t('label_dept_phone') ?></label><input type="text" name="phone" value="<?= htmlspecialchars($editDept['contact_phone'] ?? '') ?>"></div>
                <div><label><?= t('label_dept_email') ?></label><input type="email" name="email" value="<?= htmlspecialchars($editDept['contact_email'] ?? '') ?>"></div>
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
            <?php if ($editDept): ?><a class="btn" href="departments.php" style="background:var(--panel-2); color:var(--text);"><?= t('btn_back_home') ?></a><?php endif; ?>
        </form>
    </div>

    <div class="card">
        <table>
            <tr><th><?= t('label_dept_name') ?></th><th><?= t('label_dept_phone') ?></th><th><?= t('label_dept_email') ?></th><th><?= t('nav_event_list') ?></th><th><?= t('dash_col_manage') ?></th></tr>
            <?php foreach ($departments as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><?= htmlspecialchars($d['contact_phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($d['contact_email'] ?? '-') ?></td>
                <td><?= (int) $d['event_count'] ?></td>
                <td>
                    <a href="?edit=<?= $d['id'] ?>"><?= t('btn_edit') ?></a> &nbsp;
                    <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <button type="submit" style="background:none; color:var(--red); padding:0; margin:0; box-shadow:none; text-decoration:underline;"><?= t('btn_delete') ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
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
