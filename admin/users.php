<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_role(['administrator']);

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$editUser = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['administrator','operator','supervisor','department_officer','camera_operator'], true) ? $_POST['role'] : 'operator';
        $dept_id = ($role === 'department_officer') ? ((int) ($_POST['department_id'] ?? 0) ?: null) : null;
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
        $password = $_POST['password'] ?? '';

        if ($full_name === '' || $username === '') {
            $error = 'Full name and username are required.';
        } else {
            if ($id > 0) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, username=?, role=?, department_id=?, status=?, password_hash=? WHERE id=?");
                    $stmt->execute([$full_name, $email ?: null, $phone ?: null, $username, $role, $dept_id, $status, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, username=?, role=?, department_id=?, status=? WHERE id=?");
                    $stmt->execute([$full_name, $email ?: null, $phone ?: null, $username, $role, $dept_id, $status, $id]);
                }
            } else {
                if ($password === '') {
                    $error = t_raw('password_leave_blank');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, username, password_hash, role, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    try {
                        $stmt->execute([$full_name, $email ?: null, $phone ?: null, $username, $hash, $role, $dept_id, $status]);
                    } catch (PDOException $e) {
                        $error = 'That username is already taken.';
                    }
                }
            }
            if (!$error) { header('Location: users.php'); exit; }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id !== (int) $_SESSION['user_id']) { // can't deactivate yourself
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?");
            $stmt->execute([$id]);
        }
        header('Location: users.php');
        exit;
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.full_name")->fetchAll();

$activeNav = 'users';
$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('users_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <h2 style="margin:0;"><?= t('users_title') ?></h2>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;"><?= $editUser ? t('user_edit') : t('user_add') ?></h2>
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editUser['id'] ?? '' ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
                <div><label><?= t('label_full_name') ?></label><input type="text" name="full_name" value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>" required></div>
                <div><label><?= t('label_username') ?></label><input type="text" name="username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required></div>
                <div><label><?= t('label_email') ?></label><input type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>"></div>
                <div><label><?= t('label_phone_field') ?></label><input type="text" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>"></div>
                <div>
                    <label><?= t('label_role') ?></label>
                    <select name="role" id="roleSelect" onchange="document.getElementById('deptWrap').style.display = this.value==='department_officer' ? 'block' : 'none';">
                        <?php foreach (['administrator','operator','supervisor','department_officer','camera_operator'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($editUser['role'] ?? '')===$r?'selected':'' ?>><?= t('role_' . $r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="deptWrap" style="display:<?= ($editUser['role'] ?? '')==='department_officer' ? 'block' : 'none' ?>;">
                    <label><?= t('label_department') ?></label>
                    <select name="department_id">
                        <option value="">-- <?= t('dash_filter_all') ?> --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($editUser['department_id'] ?? '')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?= t('label_status') ?></label>
                    <select name="status">
                        <option value="active" <?= ($editUser['status'] ?? 'active')==='active'?'selected':'' ?>><?= t('status_active') ?></option>
                        <option value="inactive" <?= ($editUser['status'] ?? '')==='inactive'?'selected':'' ?>><?= t('status_inactive') ?></option>
                    </select>
                </div>
                <div>
                    <label><?= t('label_new_password') ?></label>
                    <input type="password" name="password" placeholder="<?= $editUser ? t('password_leave_blank') : '' ?>" <?= $editUser ? '' : 'required' ?>>
                </div>
            </div>
            <button type="submit"><?= t('btn_save') ?></button>
            <?php if ($editUser): ?><a class="btn" href="users.php" style="background:var(--panel-2); color:var(--text);"><?= t('btn_back_home') ?></a><?php endif; ?>
        </form>
    </div>

    <div class="card">
        <table>
            <tr><th><?= t('label_full_name') ?></th><th><?= t('label_username') ?></th><th><?= t('label_role') ?></th><th><?= t('label_department') ?></th><th><?= t('label_status') ?></th><th><?= t('dash_col_manage') ?></th></tr>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><span class="role-badge <?= $u['role'] ?>"><?= t('role_' . $u['role']) ?></span></td>
                <td><?= htmlspecialchars($u['dept_name'] ?? '-') ?></td>
                <td><span class="status-dot <?= $u['status'] ?>"></span><?= t('status_' . $u['status']) ?></td>
                <td>
                    <a href="?edit=<?= $u['id'] ?>"><?= t('btn_edit') ?></a>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        &nbsp;
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" style="background:none; color:var(--muted); padding:0; margin:0; box-shadow:none; text-decoration:underline;">
                                <?= $u['status']==='active' ? t('btn_deactivate') : t('btn_activate') ?>
                            </button>
                        </form>
                    <?php endif; ?>
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
