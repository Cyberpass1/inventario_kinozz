<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$flowLabels = $cashFlow['labels'] ?? [];
$flowSales = array_map('floatval', $cashFlow['sales'] ?? []);
$flowPurchases = array_map('floatval', $cashFlow['purchases'] ?? []);
$flowExpenses = array_map('floatval', $cashFlow['expenses'] ?? []);
$compositionLabels = $composition['labels'] ?? [];
$compositionValues = array_map('floatval', $composition['values'] ?? []);
$alerts = $alerts ?? [];
$topProducts = $topProducts ?? [];

$topProductLabels = [];
$topProductTotals = [];
foreach ($topProducts as $item) {
    $topProductLabels[] = (string) ($item['name'] ?? 'Producto');
    $topProductTotals[] = (float) ($item['total'] ?? 0);
}

$baseCurrency = strtoupper((string) base_currency());
$baseCurrencyEscaped = e($baseCurrency);
$secondaryCurrencyRaw = strtoupper((string) secondary_currency());
$exchangeRate = (float) ($rate['rate'] ?? env('DEFAULT_EXCHANGE_RATE', 1));
$salesBase = (float) ($stats['sales_base'] ?? 0);
$purchasesBase = (float) ($stats['purchases_base'] ?? 0);
$expensesBase = (float) ($stats['expenses_base'] ?? 0);
$inventoryBase = (float) ($stats['inventory_value_base'] ?? 0);
$salesSecondary = (float) ($stats['sales_secondary'] ?? $stats['sales'] ?? 0);
$purchasesSecondary = (float) ($stats['purchases_secondary'] ?? $stats['purchases'] ?? 0);
$expensesSecondary = (float) ($stats['expenses_secondary'] ?? $stats['expenses'] ?? 0);
$inventorySecondary = (float) ($stats['inventory_value_secondary'] ?? 0);
$secondaryCurrency = e($secondaryCurrencyRaw);
$dashboardPdfUrl = '/dashboard/pdf?from=' . rawurlencode((string) $from) . '&to=' . rawurlencode((string) $to);

$reportLinks = [
    ['href' => '/reports', 'title' => 'Centro de reportes', 'copy' => 'Ventas, compras, gastos e inventario en una sola vista.'],
    ['href' => '/reports/journal', 'title' => 'Libro diario', 'copy' => 'Consulta los asientos del periodo y su origen.'],
    ['href' => '/reports/ledger', 'title' => 'Libro mayor', 'copy' => 'Revisión de saldos acumulados por movimiento.'],
];
?>

<style>
:root {
    --bg: #f5f7fb;
    --bg-soft: #fbfcfe;
    --surface: rgba(255, 255, 255, 0.82);
    --surface-strong: #ffffff;
    --surface-muted: #f8fafc;

    --text: #162033;
    --text-soft: #5f6c80;
    --text-muted: #8a94a6;

    --border: rgba(15, 23, 42, 0.08);
    --border-strong: rgba(15, 23, 42, 0.12);

    --primary: #2f6f68;
    --primary-strong: #285e58;
    --primary-soft: rgba(47, 111, 104, 0.10);
    --primary-soft-2: rgba(47, 111, 104, 0.16);

    --blue: #607d9b;
    --blue-soft: rgba(96, 125, 155, 0.12);

    --gold: #c89553;
    --gold-soft: rgba(200, 149, 83, 0.12);

    --danger: #c45f5f;
    --danger-soft: rgba(196, 95, 95, 0.12);

    --radius-2xl: 30px;
    --radius-xl: 24px;
    --radius-lg: 20px;
    --radius-md: 16px;
    --radius-sm: 12px;

    --shadow-xs: 0 4px 12px rgba(15, 23, 42, 0.03);
    --shadow-sm: 0 10px 30px rgba(15, 23, 42, 0.05);
    --shadow-md: 0 22px 48px rgba(15, 23, 42, 0.08);

    --ease: cubic-bezier(.2,.8,.2,1);
    --transition: 220ms var(--ease);
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

.premium-dashboard {
    padding: 14px 28px 28px;
    background:
        radial-gradient(circle at top left, rgba(47, 111, 104, 0.05), transparent 24%),
        radial-gradient(circle at top right, rgba(96, 125, 155, 0.05), transparent 20%),
        linear-gradient(180deg, #f7f9fc 0%, #f3f6fb 100%);
    color: var(--text);
}

.dashboard-shell {
    display: grid;
    gap: 18px;
    width: 100%;
}

.dashboard-card,
.card,
.metric-card,
.micro-card,
.dashboard-hero,
.dashboard-hero-side,
.dashboard-link-card,
.stack-row {
    min-width: 0;
}

.dashboard-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(340px, 0.95fr);
    gap: 20px;
    padding: 26px;
    border: 1px solid var(--border);
    border-radius: var(--radius-2xl);
    background: linear-gradient(180deg, rgba(255,255,255,0.90), rgba(255,255,255,0.80));
    box-shadow: var(--shadow-sm);
    backdrop-filter: blur(12px);
    animation: fadeUp .55s var(--ease) both;
}

.dashboard-hero-main {
    padding: 6px 4px;
    min-width: 0;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    padding: 8px 13px;
    border-radius: 999px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.dashboard-hero-main h2 {
    margin: 16px 0 10px;
    font-size: clamp(28px, 3vw, 40px);
    line-height: 1.05;
    font-weight: 750;
    letter-spacing: -0.03em;
    color: var(--text);
}

.dashboard-hero-main p {
    margin: 0;
    max-width: 62ch;
    font-size: 15px;
    line-height: 1.75;
    color: var(--text-soft);
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 24px;
}

.btn {
    appearance: none;
    border: 0;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 700;
    transition:
        transform var(--transition),
        box-shadow var(--transition),
        background var(--transition),
        border-color var(--transition),
        color var(--transition),
        opacity var(--transition);
    white-space: nowrap;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn:active {
    transform: translateY(0);
}

.btn-primary,
.btn:not(.btn-outline):not(.btn-link):not(.btn-secondary) {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 12px 28px rgba(47, 111, 104, 0.18);
}

.btn-primary:hover,
.btn:not(.btn-outline):not(.btn-link):not(.btn-secondary):hover {
    background: var(--primary-strong);
}

.btn-outline,
.btn-secondary {
    background: rgba(255,255,255,0.72);
    color: var(--text);
    border: 1px solid var(--border);
    box-shadow: none;
}

.btn-outline:hover,
.btn-secondary:hover {
    background: #fff;
    border-color: var(--border-strong);
}

.btn-link {
    background: transparent;
    color: var(--primary);
    padding-inline: 4px;
}

.dashboard-hero-side {
    display: grid;
    gap: 16px;
    align-content: start;
    min-width: 0;
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: calc(var(--radius-2xl) - 6px);
    background: linear-gradient(180deg, rgba(248,250,252,0.96), rgba(245,247,251,0.92));
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
}

.dashboard-hero-side-head h3 {
    margin: 10px 0 6px;
    font-size: 22px;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.dashboard-hero-side-head p {
    margin: 0;
    font-size: 14px;
    line-height: 1.65;
    color: var(--text-soft);
}

.dashboard-range {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
}

.dashboard-range label {
    display: grid;
    gap: 7px;
    min-width: 0;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-soft);
}

.dashboard-range .range-action {
    grid-column: 1 / -1;
}

.dashboard-range input {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.92);
    color: var(--text);
    outline: none;
    transition:
        border-color var(--transition),
        box-shadow var(--transition),
        background var(--transition),
        transform var(--transition);
}

.dashboard-range input:focus {
    border-color: rgba(47, 111, 104, 0.35);
    box-shadow: 0 0 0 4px rgba(47, 111, 104, 0.08);
    background: #fff;
}

.hero-note-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    min-width: 0;
}

.hero-note-item {
    min-width: 0;
    padding: 14px 14px 15px;
    border-radius: 16px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.86);
    box-shadow: var(--shadow-xs);
    transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
}

.hero-note-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
    border-color: rgba(47, 111, 104, 0.12);
}

.hero-note-item span {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    line-height: 1.4;
    color: var(--text-muted);
}

.hero-note-item strong {
    display: block;
    font-size: 14px;
    line-height: 1.45;
    letter-spacing: -0.01em;
    color: var(--text);
    word-break: break-word;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    animation: fadeUp .65s var(--ease) both;
}

.metric-card {
    min-width: 0;
    padding: 20px;
    border-radius: var(--radius-xl);
    border: 1px solid var(--border);
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.88));
    box-shadow: var(--shadow-xs);
    transition:
        transform var(--transition),
        box-shadow var(--transition),
        border-color var(--transition);
}

.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-sm);
    border-color: rgba(47, 111, 104, 0.12);
}

.metric-card-soft {
    background: linear-gradient(180deg, rgba(47,111,104,0.09), rgba(255,255,255,0.94));
}

.metric-card-blue {
    background: linear-gradient(180deg, rgba(96,125,155,0.08), rgba(255,255,255,0.94));
}

.metric-card-gold {
    background: linear-gradient(180deg, rgba(200,149,83,0.08), rgba(255,255,255,0.94));
}

.metric-card-neutral {
    background: linear-gradient(180deg, rgba(22,32,51,0.03), rgba(255,255,255,0.94));
}

.metric-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.metric-card-head span {
    font-size: 14px;
    font-weight: 750;
    color: var(--text);
}

.metric-card-head small {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.metric-card strong {
    display: block;
    min-width: 0;
    font-size: clamp(24px, 2vw, 30px);
    line-height: 1.08;
    letter-spacing: -0.03em;
    word-break: break-word;
}

.currency-subtitle {
    display: block;
    margin-top: 9px;
    font-size: 13px;
    line-height: 1.55;
    color: var(--text-soft);
}

.micro-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    animation: fadeUp .72s var(--ease) both;
}

.micro-card {
    min-width: 0;
    padding: 18px 20px;
    border-radius: var(--radius-xl);
    border: 1px solid var(--border);
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.90));
    box-shadow: var(--shadow-xs);
    transition: transform var(--transition), box-shadow var(--transition);
}

.micro-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

.micro-card span {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--text-soft);
}

.micro-card strong {
    display: block;
    font-size: 28px;
    line-height: 1.1;
    letter-spacing: -0.03em;
}

.micro-card small {
    display: block;
    margin-top: 6px;
    color: var(--text-muted);
}

.dashboard-main-grid,
.dashboard-secondary-grid {
    display: grid;
    gap: 18px;
    animation: fadeUp .78s var(--ease) both;
}

.dashboard-main-grid {
    grid-template-columns: minmax(0, 1.7fr) minmax(320px, 0.95fr);
}

.dashboard-secondary-grid {
    grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.85fr);
}

.card {
    min-width: 0;
    padding: 22px;
    border-radius: var(--radius-2xl);
    border: 1px solid var(--border);
    background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,255,255,0.88));
    box-shadow: var(--shadow-sm);
    transition:
        transform var(--transition),
        box-shadow var(--transition),
        border-color var(--transition);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: rgba(47, 111, 104, 0.10);
}

.section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}

.section-head h3 {
    margin: 0;
    font-size: 18px;
    line-height: 1.25;
    letter-spacing: -0.02em;
}

.section-head p {
    margin: 6px 0 0;
    font-size: 14px;
    line-height: 1.65;
    color: var(--text-soft);
}

.chart-shell {
    position: relative;
    min-height: 320px;
    width: 100%;
}

.chart-shell--compact {
    min-height: 280px;
}

.chart-shell--donut,
.chart-shell--composition {
    min-height: 300px;
}

.dashboard-link-list,
.stack-list {
    display: grid;
    gap: 12px;
}

.dashboard-link-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-width: 0;
    padding: 16px 18px;
    border-radius: 18px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.90);
    text-decoration: none;
    transition:
        transform var(--transition),
        box-shadow var(--transition),
        border-color var(--transition),
        background var(--transition);
}

.dashboard-link-card:hover {
    transform: translateY(-2px);
    border-color: rgba(47, 111, 104, 0.14);
    box-shadow: var(--shadow-sm);
    background: #fff;
}

.dashboard-link-card > div {
    min-width: 0;
}

.dashboard-link-card strong {
    display: block;
    font-size: 15px;
    line-height: 1.35;
    color: var(--text);
}

.dashboard-link-card small {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.55;
    color: var(--text-soft);
}

.dashboard-link-chip,
.dashboard-link-card > span {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 12px;
    font-weight: 800;
}

.dashboard-link-card--report > span {
    background: var(--blue-soft);
    color: var(--blue);
}

.stack-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-width: 0;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.88);
    transition:
        transform var(--transition),
        box-shadow var(--transition),
        border-color var(--transition);
}

.stack-row:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-xs);
    border-color: rgba(47, 111, 104, 0.12);
}

.stack-row > div:first-child {
    min-width: 0;
}

.stack-row strong {
    display: block;
    font-size: 14px;
    line-height: 1.4;
}

.stack-row small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.5;
    color: var(--text-soft);
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.badge-danger {
    color: var(--danger);
    background: var(--danger-soft);
}

.badge-neutral {
    color: var(--gold);
    background: var(--gold-soft);
}

.empty-state {
    padding: 18px;
    border-radius: 18px;
    border: 1px dashed var(--border-strong);
    background: var(--surface-muted);
    font-size: 14px;
    line-height: 1.65;
    color: var(--text-soft);
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }
}

@media (max-width: 1200px) {
    .dashboard-hero {
        grid-template-columns: 1fr;
    }

    .dashboard-hero-side {
        width: 100%;
    }

    .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-main-grid,
    .dashboard-secondary-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 820px) {
    .premium-dashboard {
        padding: 18px;
    }

    .dashboard-hero {
        padding: 18px;
        gap: 18px;
    }

    .dashboard-hero-side {
        padding: 16px;
    }

    .dashboard-range {
        grid-template-columns: 1fr;
    }

    .hero-note-grid {
        grid-template-columns: 1fr;
    }

    .hero-actions {
        flex-direction: column;
    }

    .hero-actions .btn,
    .dashboard-range .btn {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .metric-grid,
    .micro-grid {
        grid-template-columns: 1fr;
    }

    .card,
    .metric-card,
    .micro-card {
        padding: 18px;
    }

    .dashboard-link-card,
    .stack-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .dashboard-link-card > span,
    .badge {
        width: auto;
    }
}
</style>

<section class="dashboard premium-dashboard">
    <div class="dashboard-shell">

        <header class="dashboard-hero">
            <article class="dashboard-hero-main">
                <span class="eyebrow">Panel principal</span>
                <h2>Resumen claro del negocio</h2>
                <p>
                    Consulta ventas, compras, gastos e inventario desde una interfaz más limpia, con jerarquía visual clara y espacios más cómodos para operar sin fricción.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="/invoices">Nueva factura</a>
                    <a class="btn btn-outline" href="/purchases">Registrar compra</a>
                    <a class="btn btn-outline" href="/expenses">Registrar gasto</a>
                    <a class="btn btn-outline" href="<?= e($dashboardPdfUrl) ?>" target="_blank" rel="noopener noreferrer">Exportar PDF</a>
                    <a class="btn btn-link" href="/reports">Ver reportes</a>
                </div>
            </article>

            <aside class="dashboard-hero-side">
                <div class="dashboard-hero-side-head">
                    <span class="eyebrow">Periodo</span>
                    <h3>Lectura del rango actual</h3>
                    <p>Ajusta fechas y revisa cómo se comportó la operación.</p>
                </div>

                <form class="dashboard-range" method="get" action="/dashboard">
                    <label>
                        Desde
                        <input type="date" name="from" value="<?= e($from) ?>">
                    </label>

                    <label>
                        Hasta
                        <input type="date" name="to" value="<?= e($to) ?>">
                    </label>

                    <div class="range-action">
                        <button class="btn btn-primary" type="submit">Actualizar</button>
                    </div>
                </form>

                <div class="hero-note-grid">
                    <div class="hero-note-item">
                        <span>Tasa vigente</span>
                        <strong>1 <?= $baseCurrencyEscaped ?> = <?= money($exchangeRate) ?> <?= $secondaryCurrency ?></strong>
                    </div>

                    <div class="hero-note-item">
                        <span>Stock bajo</span>
                        <strong><?= (int) ($stats['low_stock'] ?? 0) ?> productos</strong>
                    </div>

                    <div class="hero-note-item">
                        <span>Inventario</span>
                        <strong><?= money($inventoryBase) ?> <?= $baseCurrencyEscaped ?></strong>
                    </div>
                </div>
            </aside>
        </header>

        <section class="metric-grid">
            <article class="metric-card metric-card-soft">
                <div class="metric-card-head">
                    <span>Ventas</span>
                    <small>Periodo actual</small>
                </div>
                <strong><?= money($salesBase) ?> <?= $baseCurrencyEscaped ?></strong>
                <small class="currency-subtitle">~ <?= money($salesSecondary) ?> <?= $secondaryCurrency ?></small>
            </article>

            <article class="metric-card metric-card-blue">
                <div class="metric-card-head">
                    <span>Compras</span>
                    <small>Abastecimiento</small>
                </div>
                <strong><?= money($purchasesBase) ?> <?= $baseCurrencyEscaped ?></strong>
                <small class="currency-subtitle">~ <?= money($purchasesSecondary) ?> <?= $secondaryCurrency ?></small>
            </article>

            <article class="metric-card metric-card-gold">
                <div class="metric-card-head">
                    <span>Gastos</span>
                    <small>Operación</small>
                </div>
                <strong><?= money($expensesBase) ?> <?= $baseCurrencyEscaped ?></strong>
                <small class="currency-subtitle">~ <?= money($expensesSecondary) ?> <?= $secondaryCurrency ?></small>
            </article>

            <article class="metric-card metric-card-neutral">
                <div class="metric-card-head">
                    <span>Inventario</span>
                    <small>Valor actual</small>
                </div>
                <strong><?= money($inventoryBase) ?> <?= $baseCurrencyEscaped ?></strong>
                <small class="currency-subtitle">~ <?= money($inventorySecondary) ?> <?= $secondaryCurrency ?></small>
            </article>
        </section>

        <section class="micro-grid">
            <article class="micro-card">
                <span>Clientes</span>
                <strong><?= (int) ($stats['clients'] ?? 0) ?></strong>
                <small>Clientes registrados</small>
            </article>

            <article class="micro-card">
                <span>Proveedores</span>
                <strong><?= (int) ($stats['suppliers'] ?? 0) ?></strong>
                <small>Proveedores activos</small>
            </article>
        </section>

        <section class="dashboard-main-grid">
            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Flujo de caja</h3>
                        <p>Comparativo diario de ventas, compras y gastos.</p>
                    </div>
                </header>
                <div class="chart-shell">
                    <canvas id="flowChart" aria-label="Gráfica de flujo de caja" role="img"></canvas>
                </div>
            </article>

            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Distribución del periodo</h3>
                        <p>Vista resumida de cómo se reparte la actividad económica.</p>
                    </div>
                </header>
                <div class="chart-shell chart-shell--donut">
                    <canvas id="compositionChart" aria-label="Gráfica de composición" role="img"></canvas>
                </div>
            </article>
        </section>

        <section class="dashboard-secondary-grid">
            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Productos más vendidos</h3>
                        <p>Top productos por actividad comercial acumulada entre facturas y notas de entrega.</p>
                    </div>
                </header>

                <?php if ($topProductTotals): ?>
                    <div class="chart-shell chart-shell--compact">
                        <canvas id="topProductsChart" aria-label="Gráfica de productos top" role="img"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No hay ventas ni notas de entrega en este rango.</div>
                <?php endif; ?>
            </article>

            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Accesos rápidos</h3>
                        <p>Entra a las acciones más usadas sin recorrer todo el panel.</p>
                    </div>
                </header>

                <div class="dashboard-link-list">
                    <a class="dashboard-link-card" href="/invoices">
                        <div>
                            <strong>Facturación</strong>
                            <small>Emite facturas con cliente, producto y PDF.</small>
                        </div>
                        <span>Ventas</span>
                    </a>

                    <a class="dashboard-link-card" href="/purchases">
                        <div>
                            <strong>Compras</strong>
                            <small>Registra entradas de mercancía y costos.</small>
                        </div>
                        <span>Control</span>
                    </a>

                    <a class="dashboard-link-card" href="/expenses">
                        <div>
                            <strong>Gastos</strong>
                            <small>Controla egresos operativos y administrativos.</small>
                        </div>
                        <span>Caja</span>
                    </a>

                    <a class="dashboard-link-card" href="/delivery-notes">
                        <div>
                            <strong>Notas de entrega</strong>
                            <small>Gestiona salidas y despachos de forma directa.</small>
                        </div>
                        <span>Despacho</span>
                    </a>
                </div>
            </article>
        </section>

        <section class="dashboard-secondary-grid">
            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Stock bajo</h3>
                        <p>Productos que conviene revisar antes de afectar la venta.</p>
                    </div>
                </header>

                <?php if ($alerts): ?>
                    <div class="stack-list">
                        <?php foreach ($alerts as $alert): ?>
                            <div class="stack-row">
                                <div>
                                    <strong><?= e($alert['name']) ?></strong>
                                    <small>SKU: <?= e($alert['sku']) ?></small>
                                </div>
                                <div>
                                    <span class="badge <?= ((float) $alert['stock'] <= 0) ? 'badge-danger' : 'badge-neutral' ?>">
                                        <?= (float) $alert['stock'] ?> / mín <?= (float) $alert['stock_min'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No hay alertas de stock en este momento.</div>
                <?php endif; ?>
            </article>

            <article class="card">
                <header class="section-head">
                    <div>
                        <h3>Reportes</h3>
                        <p>Consultas gerenciales y contables listas para abrir.</p>
                    </div>
                </header>

                <div class="dashboard-link-list">
                    <?php foreach ($reportLinks as $link): ?>
                        <a class="dashboard-link-card dashboard-link-card--report" href="<?= e($link['href']) ?>">
                            <div>
                                <strong><?= e($link['title']) ?></strong>
                                <small><?= e($link['copy']) ?></small>
                            </div>
                            <span>Reporte</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

    </div>
</section>

<script>
const flowLabels = <?= json_encode($flowLabels, JSON_UNESCAPED_UNICODE) ?>;
const flowSales = <?= json_encode($flowSales, JSON_NUMERIC_CHECK) ?>;
const flowPurchases = <?= json_encode($flowPurchases, JSON_NUMERIC_CHECK) ?>;
const flowExpenses = <?= json_encode($flowExpenses, JSON_NUMERIC_CHECK) ?>;
const compositionLabels = <?= json_encode($compositionLabels, JSON_UNESCAPED_UNICODE) ?>;
const compositionValues = <?= json_encode($compositionValues, JSON_NUMERIC_CHECK) ?>;
const topProductLabels = <?= json_encode($topProductLabels, JSON_UNESCAPED_UNICODE) ?>;
const topProductTotals = <?= json_encode($topProductTotals, JSON_NUMERIC_CHECK) ?>;

const currencyFormatter = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});

const formatMoney = (value) => currencyFormatter.format(value);

const sharedFontColor = "#5f6c80";
const sharedGridColor = "rgba(15, 23, 42, 0.08)";

const baseChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: "index",
        intersect: false
    },
    animation: {
        duration: 700,
        easing: "easeOutCubic"
    },
    plugins: {
        legend: {
            position: "bottom",
            labels: {
                usePointStyle: true,
                boxWidth: 10,
                boxHeight: 10,
                padding: 16,
                color: sharedFontColor,
                font: {
                    size: 12,
                    weight: "600"
                }
            }
        },
        tooltip: {
            backgroundColor: "rgba(22, 32, 51, 0.94)",
            titleColor: "#ffffff",
            bodyColor: "#ffffff",
            padding: 12,
            cornerRadius: 12,
            displayColors: true
        }
    }
};

const flowCanvas = document.getElementById("flowChart");
if (flowCanvas) {
    new Chart(flowCanvas, {
        type: "line",
        data: {
            labels: flowLabels,
            datasets: [
                {
                    label: "Ventas",
                    data: flowSales,
                    tension: 0.36,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderColor: "#2f6f68",
                    backgroundColor: "rgba(47, 111, 104, 0.12)"
                },
                {
                    label: "Compras",
                    data: flowPurchases,
                    tension: 0.36,
                    fill: false,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderColor: "#607d9b",
                    borderDash: [6, 5]
                },
                {
                    label: "Gastos",
                    data: flowExpenses,
                    tension: 0.36,
                    fill: false,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderColor: "#c89553"
                }
            ]
        },
        options: {
            ...baseChartOptions,
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: sharedFontColor
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: sharedGridColor,
                        drawBorder: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: sharedFontColor,
                        callback: (value) => formatMoney(value)
                    }
                }
            }
        }
    });
}

const compositionCanvas = document.getElementById("compositionChart");
if (compositionCanvas) {
    new Chart(compositionCanvas, {
        type: "doughnut",
        data: {
            labels: compositionLabels,
            datasets: [{
                data: compositionValues,
                borderWidth: 0,
                hoverOffset: 6,
                backgroundColor: ["#2f6f68", "#607d9b", "#c89553"]
            }]
        },
        options: {
            ...baseChartOptions,
            cutout: "70%",
            plugins: {
                ...baseChartOptions.plugins,
                legend: {
                    position: window.innerWidth > 900 ? "right" : "bottom",
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 14,
                        color: sharedFontColor,
                        font: {
                            size: 12,
                            weight: "600"
                        }
                    }
                },
                tooltip: {
                    ...baseChartOptions.plugins.tooltip,
                    callbacks: {
                        label: (context) => `${context.label}: ${formatMoney(context.raw)}`
                    }
                }
            }
        }
    });
}

const topProductsCanvas = document.getElementById("topProductsChart");
if (topProductsCanvas && topProductLabels.length) {
    new Chart(topProductsCanvas, {
        type: "bar",
        data: {
            labels: topProductLabels,
            datasets: [{
                label: "Monto",
                data: topProductTotals,
                borderRadius: 10,
                borderSkipped: false,
                backgroundColor: "#2f6f68",
                maxBarThickness: 24
            }]
        },
        options: {
            ...baseChartOptions,
            indexAxis: "y",
            plugins: {
                ...baseChartOptions.plugins,
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: sharedFontColor
                    }
                },
                x: {
                    beginAtZero: true,
                    grid: {
                        color: sharedGridColor,
                        drawBorder: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: sharedFontColor,
                        callback: (value) => formatMoney(value)
                    }
                }
            }
        }
    });
}
</script>
