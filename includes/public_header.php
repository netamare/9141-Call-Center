<?php
/**
 * Shared public header with brand, responsive nav (desktop buttons + mobile toggle),
 * language switcher, and optional citizen notification bell.
 * Include after <body> on public pages.
 *
 * Optional vars before include:
 *   $header_title   - overrides brand-title text
 *   $header_subtitle - overrides brand-subtitle
 *   $active_nav     - one of: home|login|about|feedback|help|supervisor
 *   $citizen_notif_count - int badge on bell (default 0)
 */
if (!function_exists('t')) {
    require_once __DIR__ . '/lang.php';
}
$header_title    = $header_title    ?? (function_exists('t') ? t('site_title') : 'Call Center 9141');
$header_subtitle = $header_subtitle ?? (function_exists('t') ? t('site_subtitle') : '');
$active_nav      = $active_nav ?? '';
$citizen_notif_count = isset($citizen_notif_count) ? (int)$citizen_notif_count : 0;
?>
<header class="public-header">
    <div class="brand">
        <a href="index.php" class="brand-link" aria-label="Home">
            <img src="assets/logo-adama.png" alt="Adama City Administration" class="logo">
        </a>
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= htmlspecialchars($header_title) ?></div>
            <?php if ($header_subtitle !== ''): ?>
            <div class="brand-subtitle"><?= htmlspecialchars($header_subtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <button type="button" class="nav-toggle" id="publicNavToggle" aria-controls="publicNav" aria-expanded="false" aria-label="Menu">
        <span class="nav-toggle-bar"></span>
        <span class="nav-toggle-bar"></span>
        <span class="nav-toggle-bar"></span>
    </button>

    <!-- Collapsed behind the hamburger toggle at every screen size: Home, About, Help, Notifications,
         Feedback, Supervisor, Night Mode, Log In (in that order, with a small gap above Log In).
         On mobile this renders as a stacked list; same dropdown card on desktop. -->
    <nav class="public-nav" id="publicNav" aria-label="Main">
        <div class="public-nav-inner">
            <a class="public-nav-btn public-nav-btn--home<?= $active_nav === 'home' ? ' is-active' : '' ?>" href="index.php">
                <span class="nav-icon" aria-hidden="true">🏠</span>
                <span><?= function_exists('t') ? t('Home') : 'Home' ?></span>
            </a>
            <a class="public-nav-btn<?= $active_nav === 'about' ? ' is-active' : '' ?>" href="about.php">
                <span class="nav-icon" aria-hidden="true">ℹ️</span>
                <span><?= function_exists('t') ? t('btn_about_short') : 'About' ?></span>
            </a>
            <a class="public-nav-btn<?= $active_nav === 'help' ? ' is-active' : '' ?>" href="citizen_help.php">
                <span class="nav-icon" aria-hidden="true">🆘</span>
                <span><?= function_exists('t') ? t('btn_help_short') : 'Help' ?></span>
            </a>
            <a class="public-nav-btn public-nav-btn--notif" href="track.php">
                <span class="nav-icon notif-bell" aria-hidden="true">🔔</span>
                <span><?= function_exists('t') ? t('nav_notifications') : 'Notifications' ?></span>
                <?php if ($citizen_notif_count > 0): ?>
                <span class="notif-badge"><?= $citizen_notif_count > 99 ? '99+' : $citizen_notif_count ?></span>
                <?php endif; ?>
            </a>
            <a class="public-nav-btn<?= $active_nav === 'feedback' ? ' is-active' : '' ?>" href="citizen_feedback.php">
                <span class="nav-icon" aria-hidden="true">💬</span>
                <span><?= function_exists('t') ? t('btn_feedback_short') : 'Feedback' ?></span>
            </a>
            <a class="public-nav-btn public-nav-btn--supervisor<?= $active_nav === 'supervisor' ? ' is-active' : '' ?>" href="track.php">
                <span class="nav-icon" aria-hidden="true">📞</span>
                <span><?= function_exists('t') ? t('Contact Supervisor') : ' Contact Supervisor' ?></span>
            </a>
            <button type="button" class="public-nav-btn public-nav-btn--theme" id="publicThemeBtn">
                <span class="nav-icon" aria-hidden="true">
                    <svg id="publicThemeIconMoon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg id="publicThemeIconSun" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </span>
                <span><?= function_exists('t') ? t('night_mode') : 'Night Mode' ?></span>
            </button>
            <a class="public-nav-btn<?= $active_nav === 'login' ? ' is-active' : '' ?>" href="admin/login.php">
                <span class="nav-icon" aria-hidden="true">🔑</span>
                <span><?= function_exists('t') ? t('Log In') : 'Log In' ?></span>
            </a>
        </div>
    </nav>

    <!-- Duplicate of Home/About/Help/Notifications/Log In — visible on the header on desktop/tablet,
         hidden on mobile (there they exist only inside the toggle above) -->
    <nav class="public-nav-quick" aria-label="Quick actions">
        <a class="public-nav-btn public-nav-btn--home<?= $active_nav === 'home' ? ' is-active' : '' ?>" href="index.php">
            <span class="nav-icon" aria-hidden="true">🏠</span>
            <span><?= function_exists('t') ? t('Home') : 'Home' ?></span>
        </a>
        <a class="public-nav-btn<?= $active_nav === 'about' ? ' is-active' : '' ?>" href="about.php">
            <span class="nav-icon" aria-hidden="true">ℹ️</span>
            <span><?= function_exists('t') ? t('btn_about_short') : 'About' ?></span>
        </a>
        <a class="public-nav-btn<?= $active_nav === 'help' ? ' is-active' : '' ?>" href="citizen_help.php">
            <span class="nav-icon" aria-hidden="true">🆘</span>
            <span><?= function_exists('t') ? t('btn_help_short') : 'Help' ?></span>
        </a>


        
        <a class="public-nav-btn<?= $active_nav === 'login' ? ' is-active' : '' ?>" href="admin/login.php">
            <span class="nav-icon" aria-hidden="true">🔑</span>
            <span><?= function_exists('t') ? t('Log In') : 'Log In' ?></span>
        </a>
    </nav>

    <!-- Microsoft-style search bar (track by code) -->
    <form class="header-search" action="track.php" method="get" role="search">
        <span class="header-search-icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        </span>
        <input type="search" name="code" class="header-search-input"
               placeholder="<?= function_exists('t') ? t('search_tracking_placeholder') : 'Search tracking code…' ?>"
               autocomplete="off" aria-label="Search tracking code">
        <button type="submit" class="header-search-submit" aria-label="Search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        </button>
    </form>

    <div class="header-actions">
        <?php if (function_exists('render_lang_switcher')) render_lang_switcher(); ?>
    </div>
</header>
<script>
(function () {
  // ---------- Theme (dark/light), shared with the admin side via the same key ----------
  var root = document.documentElement;
  var savedTheme = localStorage.getItem('cc9141_theme') || 'light';
  root.setAttribute('data-theme', savedTheme);

  function syncThemeIcons() {
    var isLight = root.getAttribute('data-theme') === 'light';
    var moon = document.getElementById('publicThemeIconMoon');
    var sun = document.getElementById('publicThemeIconSun');
    if (moon) moon.style.display = isLight ? 'none' : 'block';
    if (sun) sun.style.display = isLight ? 'block' : 'none';
  }
  syncThemeIcons();

  var themeBtn = document.getElementById('publicThemeBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      localStorage.setItem('cc9141_theme', next);
      syncThemeIcons();
    });
  }

  // ---------- Hamburger toggle ----------
  var btn = document.getElementById('publicNavToggle');
  var nav = document.getElementById('publicNav');
  if (!btn || !nav) return;

  function setOpen(open) {
    nav.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('nav-open', open);
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    setOpen(!nav.classList.contains('is-open'));
  });

  document.addEventListener('click', function (e) {
    if (!nav.classList.contains('is-open')) return;
    if (nav.contains(e.target) || btn.contains(e.target)) return;
    setOpen(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
      setOpen(false);
      btn.focus();
    }
  });

  // Close the dropdown after choosing a link (but not after toggling the theme button)
  nav.addEventListener('click', function (e) {
    if (e.target.closest('a.public-nav-btn')) {
      setOpen(false);
    }
  });
})();
</script>
