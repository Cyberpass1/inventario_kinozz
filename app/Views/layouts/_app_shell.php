<?php
$currentPath = app_request_path();
$user = auth_user() ?? [];
$role = (string) ($user['role'] ?? '');

$roleLabels = [
    'administrator' => 'Administrador',
    'vendor' => 'Ventas',
    'general_consultant' => 'Consulta',
];

$roleLabel = $roleLabels[$role] ?? ucfirst(str_replace('_', ' ', $role));
$appName = (string) env('APP_NAME', 'Sistema');
$displayName = trim((string) ($user['name'] ?? '')) !== ''
    ? (string) $user['name']
    : (string) ($user['username'] ?? '');
$appInitials = (static function (string $value): string {
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'SI';
})($appName);
$userInitials = (static function (string $value): string {
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'US';
})($displayName);

$matchPath = static function (string $href, string $mode = 'exact') use ($currentPath): bool {
    if ($mode === 'prefix') {
        return $currentPath === $href || str_starts_with($currentPath, rtrim($href, '/') . '/');
    }

    return $currentPath === $href;
};

$navShortLabel = static function (string $label): string {
    $parts = preg_split('/\s+/', trim($label)) ?: [];
    $short = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $short .= strtoupper(substr($part, 0, 1));
        if (strlen($short) >= 2) {
            break;
        }
    }

    return $short !== '' ? $short : strtoupper(substr($label, 0, 1));
};

$navGroups = [
    [
        'eyebrow' => 'General',
        'title' => 'Workspace',
        'items' => [
            ['label' => 'Dashboard', 'hint' => 'Resumen general del negocio', 'href' => '/dashboard', 'mode' => 'exact'],
        ],
    ],
    [
        'eyebrow' => 'Ventas',
        'title' => 'Clientes y facturacion',
        'items' => [
            ['label' => 'Facturas', 'hint' => 'Emision y seguimiento comercial', 'href' => '/invoices', 'mode' => 'prefix'],
            ['label' => 'Entregas', 'hint' => 'Despachos y salidas operativas', 'href' => '/delivery-notes', 'mode' => 'prefix'],
            ['label' => 'Clientes', 'hint' => 'Relacion comercial y contactos', 'href' => '/clients', 'mode' => 'prefix'],
        ],
    ],
    [
        'eyebrow' => 'Finanzas',
        'title' => 'Compras y gastos',
        'items' => [
            ['label' => 'Compras', 'hint' => 'Registro de ingresos y costos', 'href' => '/purchases', 'mode' => 'prefix'],
            ['label' => 'Proveedores', 'hint' => 'Directorio, estado y mantenimiento', 'href' => '/suppliers', 'mode' => 'prefix'],
            ['label' => 'Gastos', 'hint' => 'Control de egresos operativos', 'href' => '/expenses', 'mode' => 'prefix'],
        ],
    ],
    (static function (): array {
        $items = [
            ['label' => 'Catalogo', 'hint' => 'Productos, categorias y existencias', 'href' => '/inventory', 'mode' => 'exact'],
            ['label' => 'Servicios', 'hint' => 'Catalogo comercial sin inventario', 'href' => '/services', 'mode' => 'prefix'],
        ];

        if (production_enabled()) {
            $items[] = ['label' => 'Produccion', 'hint' => 'Recetas y fabricacion interna', 'href' => '/production', 'mode' => 'prefix'];
        }

        $items[] = ['label' => 'Movimientos', 'hint' => 'Entradas, salidas y trazabilidad', 'href' => '/inventory/movements', 'mode' => 'prefix'];

        return [
            'eyebrow' => 'Inventario',
            'title' => 'Catalogo y stock',
            'items' => $items,
        ];
    })(),
    [
        'eyebrow' => 'Analitica',
        'title' => 'Reportes',
        'items' => [
            ['label' => 'Dashboard de reportes', 'hint' => 'Metricas clave del negocio', 'href' => '/reports', 'mode' => 'exact'],
            ['label' => 'Libro diario', 'hint' => 'Asientos por periodo', 'href' => '/reports/journal', 'mode' => 'prefix'],
            ['label' => 'Libro mayor', 'hint' => 'Saldos y acumulados', 'href' => '/reports/ledger', 'mode' => 'prefix'],
            ['label' => 'Balance general', 'hint' => 'Vista financiera consolidada', 'href' => '/reports/balance-sheet', 'mode' => 'prefix'],
        ],
    ],
];

if ($role === 'administrator') {
    $navGroups[] = [
        'eyebrow' => 'Sistema',
        'title' => 'Configuracion',
        'items' => [
            ['label' => 'Ajustes generales', 'hint' => 'Empresa, monedas y parametros', 'href' => '/settings', 'mode' => 'prefix'],
            ['label' => 'Usuarios', 'hint' => 'Accesos, roles y estado de cuentas', 'href' => '/settings/users', 'mode' => 'prefix'],
        ],
    ];
}

$currentSection = 'Dashboard';
foreach ($navGroups as $group) {
    foreach ($group['items'] as $item) {
        if ($matchPath($item['href'], $item['mode'])) {
            $currentSection = $item['label'];
            break 2;
        }
    }
}
$documentPrompt = flash('document_prompt');
$projectRoot = dirname(__DIR__, 3);
$servedAssetsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets';
$cssPath = $servedAssetsRoot . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'app.css';
$jsPath = $servedAssetsRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
$assetsBaseUrl = asset_url();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('sidebar-preload');

            try {
                if (window.localStorage.getItem('inventario.sidebar.collapsed') === '1') {
                    root.classList.add('sidebar-collapsed');
                }
            } catch (error) {
            }
        })();
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e($assetsBaseUrl) ?>/css/app.css?v=<?= e($cssVersion) ?>">
</head>
<body class="app-shell">
    <script>
        (function () {
            try {
                if (window.localStorage.getItem('inventario.sidebar.collapsed') === '1') {
                    document.body.classList.add('sidebar-collapsed');
                }
            } catch (error) {
            }
        })();
    </script>
    <div class="shell">
        <aside class="shell-sidebar">
            <div class="sidebar-mobile-bar">
                <button type="button" class="sidebar-close" data-menu-close aria-label="Cerrar menu">&times;</button>
            </div>

            <div class="sidebar-shell">
                <div class="sidebar-content">
                    <div class="brand-block" data-app-initials="<?= e($appInitials) ?>">
                    <span class="eyebrow">Workspace</span>
                    <h1><?= e($appName) ?></h1>
                    <p><?= e(company()['name']) ?> </p>
                </div>

                <div class="nav-stack" id="app-navigation">
                    <?php foreach ($navGroups as $group): ?>
                        <?php
                        $groupActive = false;
                        foreach ($group['items'] as $item) {
                            if ($matchPath($item['href'], $item['mode'])) {
                                $groupActive = true;
                                break;
                            }
                        }
                        ?>
                        <section class="nav-group">
                            <div class="nav-group-title <?= $groupActive ? 'is-active' : '' ?>">
                                <span><?= e($group['eyebrow']) ?></span>
                                <strong><?= e($group['title']) ?></strong>
                            </div>

                            <nav class="nav-modern" aria-label="<?= e($group['title']) ?>">
                                <?php foreach ($group['items'] as $item): ?>
                                    <?php $itemActive = $matchPath($item['href'], $item['mode']); ?>
                                    <a
                                        class="<?= $itemActive ? 'is-active' : '' ?>"
                                        href="<?= e(app_url($item['href'])) ?>"
                                        data-nav-link
                                        title="<?= e($item['label']) ?>"
                                        <?= $itemActive ? 'aria-current="page"' : '' ?>
                                    >
                                        <span class="nav-compact-label"><?= e($navShortLabel($item['label'])) ?></span>
                                        <span class="nav-label"><?= e($item['label']) ?></span>
                                        <small class="nav-hint"><?= e($item['hint']) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </section>
                    <?php endforeach; ?>
                </div>
                </div>

                <div class="sidebar-footnote">
                    <a class="sidebar-profile" href="<?= e(app_url('/profile')) ?>" data-sidebar-profile <?= $currentPath === '/profile' ? 'aria-current="page"' : '' ?>>
                        <span class="sidebar-profile-avatar"><?= e($userInitials) ?></span>
                        <span class="sidebar-profile-info">
                            <strong><?= e($displayName) ?></strong>
                            <small><?= e($roleLabel) ?></small>
                        </span>
                        <span class="sidebar-profile-edit" aria-hidden="true">&rsaquo;</span>
                    </a>

                    <form method="post" action="/logout" class="sidebar-profile-logout">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline" type="submit">Cerrar sesion</button>
                    </form>

                    <button
                        type="button"
                        class="btn btn-outline sidebar-desktop-toggle btnCompactar"
                        data-sidebar-toggle
                        aria-pressed="false"
                        title="Compactar menu lateral"
                    >
                        <span class="sidebar-desktop-toggle-icon" data-sidebar-toggle-icon>&laquo;</span>
                        <span data-sidebar-toggle-label></span>
                    </button>

                </div>
            </div>
        </aside>

        <div class="nav-overlay" data-menu-close></div>

        <main class="shell-main">
            <header class="topbar-modern">
                <div class="topbar-leading topbar-leading-minimal">
                    <button
                        type="button"
                        class="menu-toggle"
                        data-menu-open
                        aria-label="Abrir menu"
                        aria-controls="app-navigation"
                        aria-expanded="false"
                    >
                        <span></span><span></span><span></span>
                    </button>
                </div>

            </header>

            <section class="content-modern">
                <?php if ($msg = flash('success')): ?>
                    <div class="alert success"><?= e($msg) ?></div>
                <?php endif; ?>

                <?php if ($msg = flash('error')): ?>
                    <div class="alert danger"><?= e($msg) ?></div>
                <?php endif; ?>

                <?= $content ?>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($documentPrompt): ?>
        <script>
            window.__documentPrompt = <?= $documentPrompt ?>;
        </script>
    <?php endif; ?>
    <script src="<?= e($assetsBaseUrl) ?>/js/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>
