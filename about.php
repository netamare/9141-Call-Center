<?php
/**
 * Public About page — 9141 Call Center Adama
 * Edit the text blocks below with your official content.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/lang.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/maps.php';

$dir = t_raw('dir');
?>
<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('about_title') ?> - <?= t('site_title') ?></title>
<link rel="icon" href="assets/logo-adama.png">
<link rel="stylesheet" href="assets/style.css">
<?php leaflet_assets(); ?>
</head>
<body>
<header>
    <div class="brand">
        <img src="assets/logo-adama.png" alt="Adama" class="logo">
        <div>
            <div class="brand-eyebrow">Adama City Administration</div>
            <div class="brand-title"><?= t('about_title') ?></div>
            <div class="brand-subtitle">Call Center 9141</div>
        </div>
    </div>
    <?php render_lang_switcher(); ?>
</header>
<div class="container">
    <div class="card">
        <h2>📞 Call Center 9141 — Adaamaa</h2>
        <p>
            <!-- EDIT THIS TEXT with official About content -->
            <?= t('about_body') ?>
        </p>
        <hr style="border-color:var(--border);margin:20px 0;">
        <h2 style="font-size:16px;">🌍 Waa’ee / About</h2>
        <ul style="line-height:1.7;font-size:14px;">
            <li><strong>Maqaa:</strong> Call Center 9141 — Adama City Administration</li>
            <li><strong>Bakka:</strong> Adaamaa, Oromiyaa, Itoophiyaa</li>
            <li><strong>Lakk. bilbilaa:</strong> 9141 (bilisaa)</li>
            <li><strong>Tajaajila:</strong> Al-seerummaa, Nageenya, Tajaajila, Balaa / Emergency</li>
            <li><strong>Afaanota:</strong> Afaan Oromoo, Amharic, English + 4 more</li>
            <!-- Add year established when you have it, e.g.: -->
            <!-- <li><strong>Bara hundeeffamaa:</strong> 20XX</li> -->
        </ul>
        <p class="muted" style="font-size:13px;">
            Gabaasa marsariitii ykn bilbila 9141 tiin galchi. Koodii hordoffii argatta;
            operator yoo fudhate SMS / status track.php irratti ni argita.
        </p>
        <div id="aboutMapFull" class="map-canvas" style="height:220px;margin-top:16px;border-radius:12px;"></div>
        <script>
        (function(){
            var m = L.map('aboutMapFull', { scrollWheelZoom:false }).setView([8.541, 39.270], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(m);
            L.marker([8.541, 39.270]).addTo(m).bindPopup('Adama · 9141');
        })();
        </script>
        <div class="public-action-btns" style="margin-top:20px;">
            <a class="btn public-action-btn" href="index.php"><?= t('btn_back_home') ?></a>
            <a class="btn public-action-btn" href="citizen_feedback.php">💬 Feedback</a>
            <a class="btn public-action-btn" href="citizen_help.php">🆘 Gargaarsa</a>
            <a class="btn public-action-btn public-action-btn--supervisor" href="track.php"><?= t('btn_contact_supervisor') ?></a>
        </div>
    </div>
</div>
<footer style="text-align:center; padding:22px 16px; margin-top:50px; background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%); border-top:1px solid #e2e8f0; font-family:system-ui,-apple-system,sans-serif;">
    <div style="font-size:13.5px; font-weight:600; color:#334155; letter-spacing:0.3px;">
        © 2026 MNAN. All Rights Reserved.
    </div>
    <div style="margin-top:6px; font-size:12px; color:#64748b;">
        Designed &amp; Developed by <span style="color:#0ea5e9; font-weight:600;">MNAN</span>
    </div>
    <div style="margin-top:8px; font-size:11px; color:#94a3b8;">
        Adama City Administration · Call Center 9141
    </div>
</footer>

<?php require __DIR__ . "/includes/chat_fab.php"; ?>
</body>
</html>
