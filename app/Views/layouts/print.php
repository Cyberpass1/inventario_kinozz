<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Impresion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="print-body">
    <div class="print-actions">
        <button onclick="window.print()" class="btn">Imprimir</button>
        <a href="<?= e(app_url($backUrl ?? '/dashboard')) ?>" class="btn btn-outline">Volver</a>
    </div>

    <div class="print-container"><?= $content ?></div>
</body>
</html>
