<?php

// Staff Direct Messages helpers (for unread badges on every page)
if (!function_exists('dm_unread_count')) {
    @require_once __DIR__ . '/../../includes/direct_messages.php';
}

$role = current_role();
$overdue_badge = $overdue_badge ?? 0;

// Unread DM count, used by the sidebar's DM icon badge below AND by
// render_topbar_controls() further down the page.
$dmUnread = 0;
if (function_exists('dm_unread_count') && isset($pdo) && !empty($_SESSION['user_id'])) {
    $dmUnread = dm_unread_count($pdo, (int)$_SESSION['user_id']);
}

// Which roles can see which nav sections (per the project spec's role list + camera_operator)
$can_new_event   = in_array($role, ['administrator', 'operator'], true);
$can_escalate    = in_array($role, ['administrator', 'operator'], true);
$can_departments = in_array($role, ['administrator'], true);
$can_users       = in_array($role, ['administrator'], true);
$can_settings    = in_array($role, ['administrator'], true);
// Reports: administrator, supervisor, AND operator
$can_reports     = in_array($role, ['administrator', 'supervisor', 'operator'], true);
// Performance + Analytics: administrator & supervisor only
$can_analytics   = in_array($role, ['administrator', 'supervisor'], true);
// Room Camera / AI Detection: administrator + camera_operator only
$can_cameras     = in_array($role, ['administrator', 'camera_operator'], true);
// Recent Activity: administrator, supervisor, operator only
$can_activity    = in_array($role, ['administrator', 'supervisor', 'operator'], true);
// Public citizen feedback/help from outside
$can_citizen_msg = in_array($role, ['administrator', 'supervisor', 'operator'], true);

// Count of new (unseen) citizen help messages from outside
$citizenNewCount = 0;
if ($can_citizen_msg && isset($pdo)) {
    try {
        $citizenNewCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM citizen_help WHERE status = 'new'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $citizenNewCount = 0;
    }
}

/**
 * Small inline icon per role, reused in the sidebar footer and in the
 * header role chip (render_topbar_controls, below) so the current
 * role -- System Administrator, Operator, Supervisor, Department
 * Officer, Camera Operator -- is always visually identifiable.
 */
function role_icon_svg($role) {
    $icons = [
        'administrator' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></svg>',
        'operator' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><path d="M21 15v2a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2z"/><path d="M3 15v2a2 2 0 0 0 2 2h1v-6H5a2 2 0 0 0-2 2z"/><path d="M15 19a2 2 0 0 1-2 2h-2"/></svg>',
        'supervisor' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg>',
        'department_officer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="1.5"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg>',
        'camera_operator' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    ];
    return $icons[$role] ?? $icons['operator'];
}
?>
<aside class="sidebar">
    <a href="dashboard.php" class="brand">
        <div class="brand-mark"><img src="../assets/logo-adama.png" alt="Adama City Administration emblem"></div>
        <div>
            <div class="brand-name">Adama City<br>9141 CallCenter</div>
            <div class="brand-sub">System Administration</div>
        </div>
    </a>

    <nav class="nav">
        <div class="nav-label">Operate</div>
        <a class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span><?= t('nav_dashboard') ?></span>
        </a>

        <?php if ($can_new_event): ?>
        <a class="nav-item <?= $activeNav === 'new_event' ? 'active' : '' ?>" href="new_event.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <span><?= t('nav_new_event') ?></span>
        </a>
        <?php endif; ?>

        <a class="nav-item <?= $activeNav === 'event_list' ? 'active' : '' ?>" href="dashboard.php#events">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a1 1 0 0 0-1-1 2 2 0 0 1 0-4 1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v2a2 2 0 0 1 0 4 1 1 0 0 0-1 1 1 1 0 0 0 1 1 2 2 0 0 1 0 4 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a2 2 0 0 1 0-4 1 1 0 0 0 1-1z"/></svg>
            <span><?= t('nav_event_list') ?></span>
            <?php if ($overdue_badge > 0): ?><span class="nav-badge"><?= $overdue_badge ?></span><?php endif; ?>
        </a>

        <a class="nav-item <?= $activeNav === 'monitoring' ? 'active' : '' ?>" href="monitoring.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg>
            <span><?= t('nav_monitoring') ?></span>
        </a>

        <?php if ($can_cameras): ?>
        <a class="nav-item <?= $activeNav === 'cameras' ? 'active' : '' ?>" href="cameras.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            <span><?= t('nav_cameras') ?></span>
        </a>
        <?php endif; ?>

        <a class="nav-item <?= $activeNav === 'live_map' ? 'active' : '' ?>" href="live_map.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4z"/><path d="M8 2v16M16 6v16"/></svg>
            <span><?= t('nav_live_map') ?></span>
        </a>

        <?php if ($can_departments): ?>
        <a class="nav-item <?= $activeNav === 'departments' ? 'active' : '' ?>" href="departments.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/></svg>
            <span><?= t('nav_departments') ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_reports): ?>
        <a class="nav-item <?= $activeNav === 'reports' ? 'active' : '' ?>" href="reports.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h6M9 19h3"/></svg>
            <span><?= t('nav_reports') ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_analytics): ?>
        <a class="nav-item <?= $activeNav === 'performance' ? 'active' : '' ?>" href="performance.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 5-8"/></svg>
            <span><?= t('nav_performance') ?></span>
        </a>
        <a class="nav-item <?= $activeNav === 'analytics' ? 'active' : '' ?>" href="analytics.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21H4a1 1 0 0 1-1-1V3"/><path d="M20 8l-5 5-3-3-4 4" /></svg>
            <span><?= t('nav_analytics') ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_users): ?>
        <div class="nav-label">Administer</div>
        <a class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>" href="users.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span><?= t('nav_users') ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_settings): ?>
        <a class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>" href="settings.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span><?= t('nav_settings') ?></span>
        </a>
        <?php endif; ?>

        <?php if ($can_activity): ?>
        <a class="nav-item <?= $activeNav === 'activity' ? 'active' : '' ?>" href="activity.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            <span><?= t('nav_activity') ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-label">Public</div>
        <?php if ($can_citizen_msg): ?>
        <a class="nav-item <?= $activeNav === 'citizen_messages' ? 'active' : '' ?>" href="citizen_messages.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8M8 14h5"/></svg>
            <span><?= t('nav_citizen_messages') ?></span>
            <?php if (!empty($citizenNewCount) && $citizenNewCount > 0): ?>
                <span class="nav-badge" title="Ergaa haaraa"><?= $citizenNewCount > 99 ? '99+' : (int)$citizenNewCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a class="nav-item <?= $activeNav === 'notifications' ? 'active' : '' ?>" href="notifications.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span><?= t('nav_notifications') ?></span>
        </a>
        <a class="nav-item <?= $activeNav === 'feedback' ? 'active' : '' ?>" href="feedback.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span><?= t('nav_feedback') ?></span>
        </a>
        <a class="nav-item" href="../index.php" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/></svg>
            <span><?= t('nav_public_form') ?></span>
        </a>

    </nav>


    <div class="sidebar-foot">
        <div class="user-line">
            <div class="avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['user_name'] ?? '?', 0, 2))) ?></div>
            <div>
                <div><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                <div class="role-line"><?= role_icon_svg($role) ?><span><?= t('role_' . $role) ?></span></div>
            </div>
        </div>
        <a class="logout-link" href="logout.php">&larr; <?= t('logout') ?></a>
    </div>
</aside>

<?php
/**
 * Notifications bell, dark/light theme toggle, and the "System Live" status
 * pill — rendered in the page header, next to the language switcher, on
 * every admin page (call this from each page's top-actions row, after this
 * sidebar include). Kept as a function here so the markup and its wiring
 * script below stay in one place.
 */
function render_topbar_controls() {
    global $STRINGS, $pdo, $dmUnread;
    $role = current_role();

    // Ensure DM helpers are available for the unread badge
    if (!function_exists('dm_unread_count')) {
        @require_once __DIR__ . '/../../includes/direct_messages.php';
    }
    if (!isset($dmUnread)) {
        $dmUnread = 0;
        if (function_exists('dm_unread_count') && isset($pdo) && !empty($_SESSION['user_id'])) {
            $dmUnread = dm_unread_count($pdo, (int)$_SESSION['user_id']);
        }
    }
    ?>
    <span class="role-chip role-<?= htmlspecialchars($role) ?>"><?= role_icon_svg($role) ?><span><?= t('role_' . $role) ?></span></span>
    <div class="icon-row">
        <div class="icon-row-wrap">
            <button class="icon-btn" id="bellBtn" title="<?= t('nav_notifications') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="ping" id="bellPing" style="display:none;">0</span>
            </button>
            <div class="dropdown-panel" id="notifPanel">
                <div class="dp-head"><strong><?= t('nav_notifications') ?></strong><button type="button" id="markReadBtn"><?= t('notif_mark_read') ?></button></div>
                <div id="notifList"><div class="notif-empty"><?= t('notif_empty') ?></div></div>
            </div>
        </div>
        <button class="icon-btn" id="themeBtn" title="<?= t('theme_toggle') ?>">
            <svg id="themeIconMoon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg id="themeIconSun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        </button>
    </div>
    <span class="status-pill"><span class="dot-live"></span> <?= t('system_live') ?></span>
    <?php
}
?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // ---------- Theme toggle (persisted in localStorage) ----------
    const root = document.documentElement;
    const saved = localStorage.getItem('cc9141_theme') || 'dark';
    root.setAttribute('data-theme', saved);
    function syncThemeIcons(){
        const isLight = root.getAttribute('data-theme') === 'light';
        document.getElementById('themeIconMoon').style.display = isLight ? 'none' : 'block';
        document.getElementById('themeIconSun').style.display = isLight ? 'block' : 'none';
    }
    syncThemeIcons();
    const themeBtn = document.getElementById('themeBtn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function(){
            const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', next);
            localStorage.setItem('cc9141_theme', next);
            syncThemeIcons();
        });
    }

    // ---------- Notifications: poll every 15s, bell dropdown, operator alarm ----------
    const bellBtn = document.getElementById('bellBtn');
    const notifPanel = document.getElementById('notifPanel');
    const bellPing = document.getElementById('bellPing');
    const notifList = document.getElementById('notifList');
    let alarmPlayedFor = 0;
    let _sirenCtx = null;
    let _sirenStopTimer = null;

    function stopAmbulanceSiren() {
        try {
            if (_sirenStopTimer) { clearTimeout(_sirenStopTimer); _sirenStopTimer = null; }
            if (_sirenCtx) { _sirenCtx.close(); _sirenCtx = null; }
            const btn = document.getElementById('stopSirenBtn');
            if (btn) btn.style.display = 'none';
        } catch (e) {}
    }

    // Ambulance-style siren ~30s when escalation (5+ min unhandled) fires
    function playAlarmBeep(){
        stopAmbulanceSiren();
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            _sirenCtx = ctx;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            gain.gain.value = 0.18;
            osc.connect(gain);
            gain.connect(ctx.destination);
            let t = ctx.currentTime;
            const cycle = 0.85;
            const duration = 30; // only 30 seconds as requested
            for (let i = 0; i < duration / cycle; i++) {
                const t0 = t + i * cycle;
                osc.frequency.setValueAtTime(600, t0);
                osc.frequency.linearRampToValueAtTime(900, t0 + cycle / 2);
                osc.frequency.linearRampToValueAtTime(600, t0 + cycle);
            }
            osc.start(t);
            osc.stop(t + duration);
            gain.gain.setValueAtTime(0.18, t + duration - 2);
            gain.gain.linearRampToValueAtTime(0.001, t + duration);
            _sirenStopTimer = setTimeout(stopAmbulanceSiren, (duration + 0.5) * 1000);
            let btn = document.getElementById('stopSirenBtn');
            if (!btn && document.body) {
                btn = document.createElement('button');
                btn.id = 'stopSirenBtn';
                btn.type = 'button';
                btn.textContent = '🔇 Stop siren';
                btn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;padding:12px 18px;background:#dc2626;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.3);';
                btn.onclick = stopAmbulanceSiren;
                document.body.appendChild(btn);
            }
            if (btn) btn.style.display = 'block';
        } catch (e) {}
    }

    if (bellBtn && notifPanel) {
        bellBtn.addEventListener('click', function(e){
            e.stopPropagation();
            notifPanel.classList.toggle('open');
        });
        document.addEventListener('click', function(){ notifPanel.classList.remove('open'); });
        notifPanel.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    const markReadBtn = document.getElementById('markReadBtn');
    if (markReadBtn) {
        markReadBtn.addEventListener('click', function(){
            fetch('<?= (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '' : 'admin/') ?>api_notifications.php', {
                method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=mark_read'
            }).then(pollNotifications);
        });
    }

    function pollNotifications(){
        if (!bellPing || !notifList) return;
        fetch('<?= (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '' : 'admin/') ?>api_notifications.php')
            .then(r => r.json())
            .then(function(data){
                bellPing.style.display = data.count > 0 ? 'flex' : 'none';
                bellPing.textContent = data.count > 99 ? '99+' : data.count;
                if (bellBtn) bellBtn.classList.toggle('alarm-on', data.urgent);

                if (data.urgent && data.count !== alarmPlayedFor) {
                    playAlarmBeep();
                    alarmPlayedFor = data.count;
                }

                if (!data.items.length) {
                    notifList.innerHTML = '<div class="notif-empty"><?= addslashes(t('notif_empty')) ?></div>';
                    return;
                }
                notifList.innerHTML = data.items.map(function(n){
                    const href = n.event_id ? 'report.php?id=' + n.event_id : '#';
                    return '<a class="notif-item ' + (n.read ? '' : 'unread') + '" href="' + href + '">'
                        + '<div class="nt-title">' + (n.urgent ? '🚨 ' : '') + n.title + '</div>'
                        + (n.message ? '<div class="nt-msg">' + n.message + '</div>' : '')
                        + '<div class="nt-time">' + n.when + '</div></a>';
                }).join('');
            })
            .catch(function(){});
    }
    pollNotifications();
    setInterval(pollNotifications, 15000);
});
</script>

<?php
/* ============================================================
   Floating chat FABs (admin)
   1) Staff Direct Messages — blue
   2) Citizen messages from outside (help/feedback) — same style
   Badge shows count of new external citizen_help messages.
   ============================================================ */
?>
<link rel="stylesheet" href="../assets/chat-fab.css">
<div class="chat-fab-wrap">
  <?php if (!empty($can_citizen_msg)): ?>
  <a href="citizen_messages.php" class="chat-fab footer-fab footer-fab--message" id="citizenChatFab" aria-label="<?= htmlspecialchars(t('nav_citizen_messages')) ?>" title="<?= htmlspecialchars(t('nav_citizen_messages')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <?php if (!empty($citizenNewCount) && $citizenNewCount > 0): ?>
      <span class="badge" data-count="<?= (int)$citizenNewCount ?>" id="citizenChatFabBadge"><?= $citizenNewCount > 99 ? '99+' : (int)$citizenNewCount ?></span>
    <?php else: ?>
      <span class="badge" data-count="0" id="citizenChatFabBadge" style="display:none;">0</span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
  <a href="direct_messages.php" class="chat-fab footer-fab footer-fab--message" id="adminChatFab" aria-label="<?= htmlspecialchars(t('nav_direct_messages')) ?>" title="<?= htmlspecialchars(t('nav_direct_messages')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <?php if (!empty($dmUnread) && $dmUnread > 0): ?>
      <span class="badge" data-count="<?= (int)$dmUnread ?>" id="adminChatFabBadge"><?= $dmUnread > 99 ? '99+' : (int)$dmUnread ?></span>
    <?php else: ?>
      <span class="badge" data-count="0" id="adminChatFabBadge" style="display:none;">0</span>
    <?php endif; ?>
  </a>
</div>
