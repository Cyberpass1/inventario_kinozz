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
            ['label' => 'Reportes', 'hint' => 'Metricas clave y libros contables', 'href' => '/reports', 'mode' => 'prefix'],
            ['label' => 'Graficas', 'hint' => 'Tendencias, comparativos y predicciones', 'href' => '/charts', 'mode' => 'prefix'],
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
try {
    $alertsSummary = (new \App\Models\Alerts())->summary();
} catch (\Throwable $alertsException) {
    $alertsSummary = ['total' => 0, 'groups' => []];
}
$alertGroupLabels = [
    'stock' => ['Stock critico', 'bi-box-seam'],
    'invoices_overdue' => ['Facturas vencidas', 'bi-receipt'],
    'invoices_upcoming' => ['Facturas proximas a vencer', 'bi-clock-history'],
    'deliveries_overdue' => ['Notas de entrega vencidas', 'bi-truck'],
    'purchases_overdue' => ['Compras por pagar vencidas', 'bi-cart-x'],
    'purchases_upcoming' => ['Compras proximas a vencer', 'bi-cart-check'],
];
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
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= e($appName) ?>">
    <link rel="icon" type="image/png" href="<?= e($assetsBaseUrl) ?>/img/Logo_System.png">
    <link rel="shortcut icon" type="image/png" href="<?= e($assetsBaseUrl) ?>/img/Logo_System.png">
    <link rel="apple-touch-icon" href="<?= e($assetsBaseUrl) ?>/img/Logo_System.png">
    <link rel="manifest" href="<?= e(app_url('/manifest.webmanifest')) ?>">
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

                    <div class="alert-center alert-center--brand" data-alert-center>
                        <button
                            type="button"
                            class="alert-center-trigger"
                            data-alert-center-toggle
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-label="Centro de alertas"
                            title="Centro de alertas"
                        >
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <?php if (($alertsSummary['total'] ?? 0) > 0): ?>
                                <span class="alert-center-badge" data-alert-center-badge><?= (int) $alertsSummary['total'] ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="alert-center-panel" data-alert-center-panel hidden role="dialog" aria-label="Alertas activas">
                            <div class="alert-center-head">
                                <strong>Centro de alertas</strong>
                                <span><?= (int) ($alertsSummary['total'] ?? 0) ?> activas</span>
                            </div>

                            <?php if (($alertsSummary['total'] ?? 0) === 0): ?>
                                <div class="alert-center-empty">
                                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                                    <p>Sin alertas. Todo en orden por ahora.</p>
                                </div>
                            <?php else: ?>
                                <div class="alert-center-body">
                                    <?php foreach (($alertsSummary['groups'] ?? []) as $groupKey => $items): ?>
                                        <?php if (empty($items)) { continue; } ?>
                                        <?php [$groupLabel, $groupIcon] = $alertGroupLabels[$groupKey] ?? ['Alertas', 'bi-bell']; ?>
                                        <section class="alert-center-group">
                                            <header>
                                                <i class="bi <?= e($groupIcon) ?>" aria-hidden="true"></i>
                                                <strong><?= e($groupLabel) ?></strong>
                                                <span class="alert-center-count"><?= count($items) ?></span>
                                            </header>
                                            <ul>
                                                <?php foreach ($items as $alert): ?>
                                                    <li class="alert-center-item alert-center-item--<?= e($alert['severity'] ?? 'info') ?>">
                                                        <a href="<?= e(app_url($alert['href'] ?? '/dashboard')) ?>">
                                                            <span class="alert-center-dot" aria-hidden="true"></span>
                                                            <div>
                                                                <strong><?= e($alert['title'] ?? '') ?></strong>
                                                                <small><?= e($alert['meta'] ?? '') ?></small>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <footer class="alert-center-foot">
                                <a href="<?= e(app_url('/inventory')) ?>"><i class="bi bi-arrow-right-short" aria-hidden="true"></i> Inventario</a>
                                <a href="<?= e(app_url('/invoices')) ?>">Cobros pendientes</a>
                            </footer>
                        </div>
                    </div>
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

                    <div class="sidebar-footer-actions">
                        <form method="post" action="/logout" class="sidebar-profile-logout" data-logout-confirm data-no-submit-loading="1">
                            <?= csrf_field() ?>
                            <button
                                class="btn btn-outline sidebar-icon-btn sidebar-logout-btn"
                                type="submit"
                                aria-label="Cerrar sesion"
                                title="Cerrar sesion"
                            >
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                <span class="sidebar-icon-btn-label">Salir</span>
                            </button>
                        </form>

                        <button
                            type="button"
                            class="btn btn-outline sidebar-icon-btn sidebar-desktop-toggle btnCompactar"
                            data-sidebar-toggle
                            aria-pressed="false"
                            aria-label="Compactar menu lateral"
                            title="Compactar menu lateral"
                        >
                            <i class="bi bi-chevron-double-left sidebar-desktop-toggle-icon" data-sidebar-toggle-icon aria-hidden="true"></i>
                            <span class="sidebar-icon-btn-label" data-sidebar-toggle-label>Compactar</span>
                        </button>
                    </div>

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
