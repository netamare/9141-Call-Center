(function () {
  function ensureHeader() {
    var topActions = document.querySelector('main.main .top-actions, .main .top-actions, .top-actions');
    if (!topActions) {
      var main = document.querySelector('main.main, .main');
      if (!main) return null;
      topActions = document.createElement('div');
      topActions.className = 'top-actions top-actions--auto';
      main.insertBefore(topActions, main.firstChild);
    }
    return topActions;
  }

  function ensureToggleAndBrand(topActions) {
    // 1) Toggle FIRST (Telegram: ☰ on the far left)
    var btn = document.getElementById('sidebarToggle');
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sidebar-toggle';
      btn.id = 'sidebarToggle';
      btn.setAttribute('aria-controls', 'adminSidebar');
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-label', 'Menu');
      btn.title = 'Menu';
      btn.innerHTML =
        '<span class="sidebar-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>' +
        '<span class="sidebar-toggle-dot" aria-hidden="true"></span>';
    }

    // 2) Brand SECOND
    var brand = topActions.querySelector('.header-brand');
    if (!brand) {
      brand = document.createElement('a');
      brand.href = 'dashboard.php';
      brand.className = 'header-brand';
      brand.innerHTML =
        '<div class="header-brand-mark"><img src="../assets/logo-adama.png" alt="Adama City"></div>' +
        '<div class="header-brand-text">' +
          '<div class="header-brand-name">Adama City<br>9141 CallCenter</div>' +
          '<div class="header-brand-sub">SYSTEM ADMINISTRATION</div>' +
        '</div>';
    }

    // Force order: [☰] [Logo] ...rest
    topActions.insertBefore(btn, topActions.firstChild);
    if (btn.nextSibling !== brand) {
      topActions.insertBefore(brand, btn.nextSibling);
    }

    return btn;
  }

  function ensureOverlay() {
    var ov = document.getElementById('sidebarOverlay');
    if (ov) return ov;
    ov = document.createElement('div');
    ov.className = 'sidebar-overlay';
    ov.id = 'sidebarOverlay';
    document.body.appendChild(ov);
    return ov;
  }

  function init() {
    var topActions = ensureHeader();
    if (!topActions) return;

    var btn = ensureToggleAndBrand(topActions);
    var overlay = ensureOverlay();
    if (!btn) return;

    function setOpen(open) {
      document.body.classList.toggle('sidebar-open', !!open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    setOpen(false);

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      setOpen(!document.body.classList.contains('sidebar-open'));
    });

    if (overlay) {
      overlay.addEventListener('click', function () { setOpen(false); });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });

    var side = document.querySelector('aside.sidebar, .sidebar');
    if (side) {
      if (!side.id) side.id = 'adminSidebar';
      side.addEventListener('click', function (e) {
        if (e.target.closest('a.nav-item, a.logout-link')) setOpen(false);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
