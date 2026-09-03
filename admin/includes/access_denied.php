<!DOCTYPE html>
<html lang="<?= $CURRENT_LANG ?? 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('access_denied') ?></title>
<link rel="stylesheet" href="<?= (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '' : 'admin/') ?>../assets/style.css">
</head>
<body>
<div class="container" style="max-width:480px; margin-top:80px;">
    <div class="card" style="text-align:center;">
        <div style="font-size:40px; margin-bottom:10px;">🚫</div>
        <h2><?= t('access_denied') ?></h2>
        <a class="btn" href="dashboard.php"><?= t('btn_back_home') ?></a>
    </div>
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
