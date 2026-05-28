<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e(env('APP_NAME','Sistema')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e(env('APP_NAME','Sistema')) ?>">
    <link rel="icon" type="image/png" href="<?= e(asset_url('img/Logo_System.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('img/Logo_System.png')) ?>">
    <link rel="manifest" href="<?= e(app_url('/manifest.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="guest-body"><?= $content ?></body>
</html>
