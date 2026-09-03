<?php
/**
 * Internal Direct Messages — any staff can message any other staff.
 * Example: Fire Dept Officer ↔ Operator, Camera Room ↔ Admin, etc.
 */
require __DIR__ . '/../includes/auth.php';
require_role(['administrator', 'operator', 'supervisor', 'department_officer', 'camera_operator']);

require_once __DIR__ . '/../includes/direct_messages.php';
require_once __DIR__ . '/../includes/security.php';

$dir = t_raw('dir');
$me = current_user();
$myId = (int) ($me['id'] ?? $_SESSION['user_id'] ?? 0);
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $to = (int) ($_POST['receiver_id'] ?? 0);
    $body = trim($_POST['message'] ?? '');
    if ($to <= 0 || $body === '') {
        $error = 'Receiver and message are required.';
    } elseif (dm_send($pdo, $myId, $to, $body)) {
        header('Location: direct_messages.php?with=' . $to);
        exit;
    } else {
        $error = 'Could not send message.';
    }
}

$withId = isset($_GET['with']) ? (int) $_GET['with'] : 0;
$recipients = dm_list_recipients($pdo, $myId);
$inbox = dm_inbox($pdo, $myId);
$conversation = [];
$withUser = null;

if ($withId > 0) {
    foreach ($recipients as $r) {
        if ((int)$r['id'] === $withId) { $withUser = $r; break; }
    }
    if (!$withUser) {
        $stmt = $pdo->prepare("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? AND u.status='active'");
        $stmt->execute([$withId]);
        $withUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($withUser) {
        $conversation = dm_conversation($pdo, $myId, $withId);
        dm_mark_read($pdo, $myId, $withId);
    }
}

$unreadTotal = dm_unread_count($pdo, $myId);
$activeNav = 'direct_messages';
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('dm_title') ?> — <?= t('site_title') ?></title>
<link rel="icon" href="../assets/logo-adama.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="shell">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="top-actions" style="margin-bottom:16px;">
    <div>
      <div class="eyebrow" style="font-family:var(--mono); font-size:10.5px; letter-spacing:2px; text-transform:uppercase; color:var(--cyan); margin-bottom:6px;">
        Staff
      </div>
      <h2 class="dm-page-title" style="margin:0;">
        💬 <?= t('dm_title') ?>
        <?php if ($unreadTotal > 0): ?>
          <span class="dm-badge dm-badge--header"><?= $unreadTotal ?></span>
        <?php endif; ?>
      </h2>
      <p class="muted" style="margin:4px 0 0; font-size:13px;"><?= t('dm_subtitle') ?></p>
    </div>
    <div class="topbar-controls"><?php render_topbar_controls(); render_lang_switcher(); ?></div>
  </div>

  <!-- About Direct Messages -->
  <div class="dm-about">
    <strong>ℹ️ <?= t('dm_title') ?></strong><br>
    <?= t('dm_about') ?>
    <ul>
      <li><strong>Admin</strong> ↔ Operator / Supervisor / Department / Camera Room</li>
      <li><strong>Operator</strong> ↔ Fire Dept Officer, Camera Operator, etc.</li>
      <li><strong>Department Officer</strong> (e.g. Fire) ↔ Operator / Supervisor</li>
      <li><strong>Camera Room</strong> ↔ Admin / Operator</li>
    </ul>
  </div>

  <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="dm-layout">

    <!-- LEFT: inbox -->
    <aside class="dm-sidebar">
      <h3><?= t('dm_inbox') ?></h3>

      <form method="get">
        <label class="muted" style="font-size:12px; display:block; margin-bottom:4px;"><?= t('dm_new') ?></label>
        <select name="with" class="dm-new-select" onchange="this.form.submit()">
          <option value=""><?= t('dm_select_user') ?></option>
          <?php foreach ($recipients as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $withId === (int)$r['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['full_name']) ?>
              (<?= dm_role_label($r['role']) ?><?= !empty($r['dept_name']) ? ' · '.$r['dept_name'] : '' ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if (empty($inbox)): ?>
        <p class="muted" style="font-size:13px; margin-top:8px;"><?= t('dm_empty') ?></p>
      <?php else: ?>
        <?php foreach ($inbox as $row): ?>
          <a href="?with=<?= (int)$row['user_id'] ?>"
             class="dm-conv-item <?= $withId === (int)$row['user_id'] ? 'is-active' : '' ?>">
            <div class="dm-conv-top">
              <span class="dm-conv-name"><?= htmlspecialchars($row['full_name']) ?></span>
              <?php if ((int)$row['unread'] > 0): ?>
                <span class="dm-badge"><?= (int)$row['unread'] ?></span>
              <?php endif; ?>
            </div>
            <div class="dm-conv-meta">
              <?= dm_role_label($row['role']) ?><?= !empty($row['dept_name']) ? ' · '.htmlspecialchars($row['dept_name']) : '' ?>
            </div>
            <div class="dm-conv-preview">
              <?= htmlspecialchars(mb_strimwidth($row['last_message'] ?? '', 0, 42, '…')) ?>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </aside>

    <!-- RIGHT: thread -->
    <section class="dm-thread-panel">
      <?php if (!$withUser): ?>
        <div class="dm-thread-empty">
          <?= t('dm_select_user') ?><br>
          <small>Fire Dept Officer ↔ Operator · Camera Room ↔ Admin · etc.</small>
        </div>
      <?php else: ?>
        <div class="dm-thread-header">
          <strong><?= htmlspecialchars($withUser['full_name']) ?></strong>
          <span class="dm-role">
            <?= dm_role_label($withUser['role'] ?? '') ?>
            <?= !empty($withUser['dept_name']) ? ' · '.htmlspecialchars($withUser['dept_name']) : '' ?>
          </span>
        </div>

        <div id="dm-thread" class="dm-thread">
          <?php if (empty($conversation)): ?>
            <p class="muted" style="text-align:center; margin:auto;"><?= t('dm_no_msgs') ?></p>
          <?php else: ?>
            <?php foreach ($conversation as $msg): ?>
              <?php $mine = (int)$msg['sender_id'] === $myId; ?>
              <div class="dm-bubble-wrap <?= $mine ? 'mine' : 'theirs' ?>">
                <div class="dm-bubble <?= $mine ? 'mine' : 'theirs' ?>">
                  <?= nl2br(htmlspecialchars($msg['message'])) ?>
                </div>
                <div class="dm-meta">
                  <?= htmlspecialchars($msg['created_at']) ?>
                  <?= $mine ? '' : ' · '.htmlspecialchars($msg['sender_name'] ?? '') ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form method="post" class="dm-composer">
          <?= csrf_field() ?>
          <input type="hidden" name="receiver_id" value="<?= (int)$withUser['id'] ?>">
          <textarea name="message" required rows="2" placeholder="<?= htmlspecialchars(t('dm_placeholder')) ?>"></textarea>
          <button type="submit" class="btn"><?= t('dm_send') ?></button>
        </form>
      <?php endif; ?>
    </section>
  </div>

</main>
</div>

<script>
(function () {
  var el = document.getElementById('dm-thread');
  if (el) el.scrollTop = el.scrollHeight;
})();
</script>
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
