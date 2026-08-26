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
</body>
</html>
