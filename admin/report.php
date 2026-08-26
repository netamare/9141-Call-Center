<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/notifications.php';
require __DIR__ . '/../includes/maps.php';

$role = current_role();
$myDeptId = current_user_department_id();
$id = (int) ($_GET['id'] ?? 0);
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

function fetch_event($pdo, $id) {
    $stmt = $pdo->prepare("SELECT r.*, c.name AS category_name, c.icon, u.full_name AS operator_name
                            FROM events r
                            LEFT JOIN categories c ON c.id = r.category_id
                            LEFT JOIN users u ON u.id = r.operator_id
                            WHERE r.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$report = fetch_event($pdo, $id);
if (!$report) {
    header('Location: dashboard.php');
    exit;
}

// Department officers can only see/manage events assigned to their own department
if ($role === 'department_officer' && (int)$report['assigned_department_id'] !== (int)$myDeptId) {
    require_role([]); // always denies -> shows access_denied
}

$can_escalate = in_array($role, ['administrator', 'operator'], true);
$can_update_status = in_array($role, ['administrator', 'operator'], true)
    || ($role === 'department_officer' && (int)$report['assigned_department_id'] === (int)$myDeptId);
$can_edit_details = in_array($role, ['administrator', 'operator'], true);
$can_followup = in_array($role, ['administrator', 'operator'], true);

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'escalate' && $can_escalate) {
        $dept_id = (int) $_POST['department_id'];
        $stmt = $pdo->prepare("UPDATE events SET assigned_department_id = ?, status = 'assigned' WHERE id = ?");
        $stmt->execute([$dept_id, $id]);

        $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $deptStmt->execute([$dept_id]);
        $dept_name = $deptStmt->fetchColumn();
        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'escalated', ?, ?)");
        $log->execute([$id, "Escalated to $dept_name", $_SESSION['user_id']]);
        notify_escalation($pdo, $id, $dept_id, $report['tracking_code'], $report['priority']);
        notify_department_escalation_sms($pdo, $dept_id, $id, $report['tracking_code'], $report['category_name'] ?? '', $report['priority']);
        $message = t_raw('dash_col_department') . ' ' . t_raw('btn_update');

    } elseif ($action === 'update_status' && $can_update_status) {
        $status = $_POST['status'];
        $allowed = ['new', 'assigned', 'ongoing', 'solved', 'unsolved'];
        if (in_array($status, $allowed, true)) {
            $note = trim($_POST['note'] ?? '');
            $isFinal = in_array($status, ['solved', 'unsolved'], true);
            $resolved_at = $isFinal ? date('Y-m-d H:i:s') : null;

            // Compute response time (minutes from creation to resolution) once, on the final status
            $responseMinutes = null;
            if ($isFinal) {
                $createdStmt = $pdo->prepare("SELECT created_at, response_time_minutes FROM events WHERE id = ?");
                $createdStmt->execute([$id]);
                $row = $createdStmt->fetch();
                if ($row && $row['response_time_minutes'] === null) {
                    $responseMinutes = (int) round((strtotime($resolved_at) - strtotime($row['created_at'])) / 60);
                }
            }

            $stmt = $pdo->prepare("UPDATE events SET status = ?, resolved_at = COALESCE(?, resolved_at),
                                    response_time_minutes = COALESCE(?, response_time_minutes) WHERE id = ?");
            $stmt->execute([$status, $resolved_at, $responseMinutes, $id]);

            $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'status_change', ?, ?)");
            $log->execute([$id, "Status set to $status" . ($note ? " - $note" : ''), $_SESSION['user_id']]);

            if ($isFinal && !empty($report['caller_phone'])) {
                $statusText = t_raw('status_' . $status);
                send_sms($pdo, $report['caller_phone'], sprintf(t_raw('sms_status_update'), $report['tracking_code'], $statusText), $id);
            }

            // Close the escalation loop: let the operator who logged this (and
            // admins/supervisors) know the department has responded/updated it.
            notify_status_update_to_operator($pdo, $id, $report['operator_id'], $report['tracking_code'], $status);

            $message = t_raw('update_status') . ' ' . t_raw('btn_update');
        }

    } elseif ($action === 'edit_details' && $can_edit_details) {
        $caller_name = trim($_POST['caller_name'] ?? '');
        $caller_phone = trim($_POST['caller_phone'] ?? '');
        if (!is_valid_et_phone($caller_phone)) {
            $message = t_raw('error_phone_format');
        } else {
        $gender = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'unspecified';
        $address = trim($_POST['address'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $latitude = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
        $longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
        $priority = in_array($_POST['priority'] ?? '', ['low','medium','high','critical']) ? $_POST['priority'] : $report['priority'];
        $description = trim($_POST['description'] ?? '');

        $stmt = $pdo->prepare("UPDATE events SET caller_name=?, caller_phone=?, gender=?, address=?, location=?, latitude=?, longitude=?, priority=?, description=? WHERE id=?");
        $stmt->execute([$caller_name ?: null, $caller_phone ?: null, $gender, $address ?: null, $location ?: null, $latitude, $longitude, $priority, $description, $id]);

        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'edited', 'Event details updated', ?)");
        $log->execute([$id, $_SESSION['user_id']]);
        $message = t_raw('btn_save_changes');
        }

    } elseif ($action === 'delete_report' && $role === 'administrator') {
        $del = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $del->execute([$id]);
        header('Location: dashboard.php?deleted=1');
        exit;

    } elseif ($action === 'add_followup' && $can_followup) {
        $followup_date = trim($_POST['followup_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $fstatus = $_POST['followup_status'] === 'done' ? 'done' : 'pending';
        if ($followup_date !== '') {
            $stmt = $pdo->prepare("INSERT INTO followups (event_id, followup_date, remarks, status, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $followup_date, $remarks ?: null, $fstatus, $_SESSION['user_id']]);

            $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note, changed_by) VALUES (?, 'followup', ?, ?)");
            $log->execute([$id, "Follow-up logged for $followup_date", $_SESSION['user_id']]);
            $message = t_raw('btn_add_followup');
        }
    }

    $report = fetch_event($pdo, $id);
}

$logs = $pdo->prepare("SELECT l.*, u.full_name FROM event_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.event_id = ? ORDER BY l.changed_at DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();

$att_stmt = $pdo->prepare("SELECT * FROM event_attachments WHERE event_id = ?");
$att_stmt->execute([$id]);
$attachments = $att_stmt->fetchAll();

$fu_stmt = $pdo->prepare("SELECT f.*, u.full_name FROM followups f LEFT JOIN users u ON u.id = f.created_by WHERE f.event_id = ? ORDER BY f.followup_date DESC");
$fu_stmt->execute([$id]);
$followups = $fu_stmt->fetchAll();

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($report['tracking_code']) ?> - <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
<?php leaflet_assets(); ?>
</head>
<body>
<div class="shell">
<?php $activeNav = 'event_list'; include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <div class="top-actions" style="margin-bottom:20px;">
        <div>
            <div class="eyebrow" style="font-family:var(--mono); font-size:10.5px; letter-spacing:2px; text-transform:uppercase; color:var(--cyan); margin-bottom:6px;">
                <?= htmlspecialchars($report['icon'] ?? '') ?> <?= htmlspecialchars($report['tracking_code']) ?>
            </div>
            <h2 style="margin:0;"><?= htmlspecialchars($report['category_name']) ?></h2>
        </div>
        <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <p><strong><?= t('track_status') ?>:</strong> <span class="badge <?= $report['status'] ?>"><?= t('status_' . $report['status']) ?></span>
           &nbsp; <strong><?= t('label_severity') ?>:</strong> <span class="badge <?= $report['priority'] ?>"><?= t('severity_' . $report['priority']) ?></span></p>
        <p><strong><?= t('label_description') ?>:</strong> <?= nl2br(htmlspecialchars($report['description'])) ?></p>
        <p><strong><?= t('label_location') ?>:</strong> <?= htmlspecialchars($report['location'] ?? '-') ?>
           &nbsp; <strong><?= t('label_address') ?>:</strong> <?= htmlspecialchars($report['address'] ?? '-') ?></p>
        <p><strong><?= t('label_name') ?>:</strong> <?= htmlspecialchars($report['caller_name'] ?: '-') ?>
           <?= $report['caller_phone'] ? ' - ' . htmlspecialchars($report['caller_phone']) : '' ?>
           &nbsp; <strong><?= t('label_gender') ?>:</strong> <?= t('gender_' . $report['gender']) ?></p>
        <p><strong><?= t('label_operator') ?>:</strong> <?= htmlspecialchars($report['operator_name'] ?? '-') ?>
           &nbsp; <strong><?= t('track_submitted') ?>:</strong> <?= htmlspecialchars($report['created_at']) ?>
           &nbsp; <strong><?= t('label_response_time') ?>:</strong> <?= $report['response_time_minutes'] !== null ? (int)$report['response_time_minutes'] . ' ' . t_raw('minutes_short') : t('not_yet_resolved') ?></p>

        <?php if ($report['latitude'] !== null && $report['longitude'] !== null): ?>
        <p><strong>📍 <?= t('label_gps') ?>:</strong> <span class="mono"><?= number_format($report['latitude'], 5) ?>, <?= number_format($report['longitude'], 5) ?></span></p>
        <?php render_location_view($report['latitude'], $report['longitude'], 'reportViewMap'); ?>
        <?php endif; ?>

        <?php if ($attachments): ?>
        <p><strong><?= t('track_attachments') ?>:</strong></p>
        <div class="attachment-grid">
            <?php foreach ($attachments as $att): ?>
                <?php if ($att['file_type'] === 'image'): ?>
                    <a href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank" rel="noopener">
                        <img src="../<?= htmlspecialchars($att['file_path']) ?>" alt="<?= htmlspecialchars($att['original_name']) ?>" class="attachment-thumb">
                    </a>
                <?php elseif ($att['file_type'] === 'video'): ?>
                    <video src="../<?= htmlspecialchars($att['file_path']) ?>" controls style="width:220px;border-radius:8px"></video>
                <?php elseif ($att['file_type'] === 'audio'): ?>
                    <audio src="../<?= htmlspecialchars($att['file_path']) ?>" controls></audio>
                <?php else: ?>
                    <a class="attachment-file" href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank" rel="noopener">📄 <?= htmlspecialchars($att['original_name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($can_edit_details): ?>
    <div class="card">
        <h2><?= t('btn_edit') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_details">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div><label><?= t('label_name') ?></label><input type="text" name="caller_name" value="<?= htmlspecialchars($report['caller_name'] ?? '') ?>"></div>
                <div><label><?= t('label_phone') ?></label><input type="tel" name="caller_phone" value="<?= htmlspecialchars($report['caller_phone'] ?? '') ?>" placeholder="<?= t('placeholder_phone') ?>" pattern="^(?:\+251|0)9\d{8}$" title="<?= htmlspecialchars(t_raw('phone_format_hint')) ?>"></div>
                <div><label><?= t('label_gender') ?></label>
                    <select name="gender">
                        <option value="unspecified" <?= $report['gender']==='unspecified'?'selected':'' ?>><?= t('gender_unspecified') ?></option>
                        <option value="male" <?= $report['gender']==='male'?'selected':'' ?>><?= t('gender_male') ?></option>
                        <option value="female" <?= $report['gender']==='female'?'selected':'' ?>><?= t('gender_female') ?></option>
                    </select>
                </div>
                <div><label><?= t('label_severity') ?></label>
                    <select name="priority">
                        <?php foreach (['low','medium','high','critical'] as $p): ?>
                            <option value="<?= $p ?>" <?= $report['priority']===$p?'selected':'' ?>><?= t('severity_' . $p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label><?= t('label_location') ?></label><input type="text" name="location" value="<?= htmlspecialchars($report['location'] ?? '') ?>"></div>
                <div><label><?= t('label_address') ?></label><input type="text" name="address" value="<?= htmlspecialchars($report['address'] ?? '') ?>"></div>
            </div>

            <?php render_location_picker('editEventMap', 'latitude', 'longitude', $report['latitude'], $report['longitude']); ?>

            <label><?= t('label_description') ?></label>
            <textarea name="description"><?= htmlspecialchars($report['description']) ?></textarea>
            <button type="submit"><?= t('btn_save_changes') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($can_escalate): ?>
    <div class="card">
        <h2><?= t('escalate_to_department') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="escalate">
            <select name="department_id" required onchange="document.querySelectorAll('.dept-contact').forEach(e=>e.style.display='none'); const el=document.getElementById('dept-contact-'+this.value); if(el) el.style.display='block';">
                <option value="">-- <?= t('dash_filter_all') ?> --</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $report['assigned_department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php foreach ($departments as $d): ?>
                <div class="dept-contact" id="dept-contact-<?= $d['id'] ?>" style="display:<?= $report['assigned_department_id'] == $d['id'] ? 'block' : 'none' ?>; font-size:13px; color:var(--muted); margin:6px 0;">
                    <?= $d['contact_phone'] ? '📞 ' . htmlspecialchars($d['contact_phone']) : '' ?>
                    <?= $d['contact_email'] ? ' &nbsp; ✉️ ' . htmlspecialchars($d['contact_email']) : '' ?>
                </div>
            <?php endforeach; ?>
            <button type="submit"><?= t('btn_escalate') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($can_update_status): ?>
    <div class="card">
        <h2><?= t('update_status') ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <select name="status" required>
                <?php foreach (['new','assigned','ongoing','solved','unsolved'] as $s): ?>
                    <option value="<?= $s ?>" <?= $report['status'] === $s ? 'selected' : '' ?>><?= t('status_' . $s) ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="note" placeholder="Note..."></textarea>
            <button type="submit"><?= t('btn_update') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2><?= t('followup_title') ?></h2>
        <table>
            <tr><th><?= t('label_followup_date') ?></th><th><?= t('label_followup_remarks') ?></th><th><?= t('label_status') ?></th><th><?= t('col_by') ?></th></tr>
            <?php foreach ($followups as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['followup_date']) ?></td>
                <td><?= htmlspecialchars($f['remarks'] ?? '') ?></td>
                <td><span class="badge <?= $f['status']==='done' ? 'solved' : 'assigned' ?>"><?= t('followup_status_' . $f['status']) ?></span></td>
                <td><?= htmlspecialchars($f['full_name'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$followups): ?><tr><td colspan="4"><?= t('no_followups') ?></td></tr><?php endif; ?>
        </table>

        <?php if ($can_followup): ?>
        <h3 style="margin-top:20px; font-size:14px;"><?= t('followup_add') ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_followup">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div><label><?= t('label_followup_date') ?></label><input type="datetime-local" name="followup_date" required></div>
                <div><label><?= t('label_status') ?></label>
                    <select name="followup_status">
                        <option value="pending"><?= t('followup_status_pending') ?></option>
                        <option value="done"><?= t('followup_status_done') ?></option>
                    </select>
                </div>
            </div>
            <label><?= t('label_followup_remarks') ?></label>
            <textarea name="remarks"></textarea>
            <button type="submit"><?= t('btn_add_followup') ?></button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2><?= t('audit_trail') ?></h2>
        <table>
            <tr><th><?= t('col_date') ?></th><th><?= t('col_action') ?></th><th><?= t('col_note') ?></th><th><?= t('col_by') ?></th></tr>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['changed_at']) ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= htmlspecialchars($l['note']) ?></td>
                <td><?= htmlspecialchars($l['full_name'] ?? 'System') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($role === 'administrator'): ?>
    <div class="card" style="border-color:rgba(226,87,76,0.35);">
        <h2 style="color:var(--red);"><?= t('danger_zone') ?></h2>
        <p class="muted" style="font-size:13px;"><?= t('delete_report_hint') ?></p>
        <form method="post" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('delete_report_confirm')), ENT_QUOTES) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_report">
            <button type="submit" class="btn btn-danger">🗑 <?= t('delete_report') ?></button>
        </form>
    </div>
    <?php endif; ?>

    <a class="btn" href="dashboard.php"><?= t('btn_back_home') ?></a>
</main>
</div>
</body>
</html>
