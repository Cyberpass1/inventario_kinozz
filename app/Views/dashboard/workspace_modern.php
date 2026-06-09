<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
/* =========================================================
   Centro de control operativo
   Dashboard orientado a la operacion de inventario: prioriza
   reposicion, valor de stock y movimiento del periodo en vez
   de un resumen financiero generico.
   ========================================================= */

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
$secondaryCurrency = e($secondaryCurrencyRaw);
$exchangeRate = (float) ($rate['rate'] ?? env('DEFAULT_EXCHANGE_RATE', 1));

$salesBase = (float) ($stats['sales_base'] ?? 0);
$purchasesBase = (float) ($stats['purchases_base'] ?? 0);
$expensesBase = (float) ($stats['expenses_base'] ?? 0);
$inventoryBase = (float) ($stats['inventory_value_base'] ?? 0);
$salesSecondary = (float) ($stats['sales_secondary'] ?? $stats['sales'] ?? 0);
$purchasesSecondary = (float) ($stats['purchases_secondary'] ?? $stats['purchases'] ?? 0);
$expensesSecondary = (float) ($stats['expenses_secondary'] ?? $stats['expenses'] ?? 0);
$inventorySecondary = (float) ($stats['inventory_value_secondary'] ?? 0);
$receivablesBase = (float) ($stats['receivables_base'] ?? 0);
$receivablesSecondary = (float) ($stats['receivables_secondary'] ?? $stats['receivables'] ?? 0);
$payablesBase = (float) ($stats['payables_base'] ?? 0);
$payablesSecondary = (float) ($stats['payables_secondary'] ?? $stats['payables'] ?? 0);

$productCount = (int) ($stats['products'] ?? 0);
$clientCount = (int) ($stats['clients'] ?? 0);
$supplierCount = (int) ($stats['suppliers'] ?? 0);
$lowStockCount = (int) ($stats['low_stock'] ?? 0);
$outOfStockCount = 0;
$reorderUnits = 0.0;
foreach ($alerts as $alert) {
    $alertStock = (float) ($alert['stock'] ?? 0);
    if ($alertStock <= 0) {
        $outOfStockCount++;
    }
    $reorderUnits += max((float) ($alert['stock_min'] ?? 0) - $alertStock, 0);
}

$user = auth_user() ?? [];
$displayName = trim((string) ($user['name'] ?? ($user['username'] ?? '')));
$firstName = $displayName !== '' ? (preg_split('/\s+/', $displayName)[0] ?? $displayName) : 'equipo';

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

$months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$weekdays = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miercoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sabado'];
$todayLabel = ($weekdays[date('l')] ?? '') . ', ' . date('j') . ' de ' . ($months[(int) date('n')] ?? '') . ' de ' . date('Y');

$formatRangeDate = static function (string $value): string {
    $ts = strtotime($value);
    return $ts === false ? $value : date('d/m/Y', $ts);
};
$rangeLabel = $formatRangeDate($from) . ' - ' . $formatRangeDate($to);

$dashboardPdfUrl = '/dashboard/pdf?from=' . rawurlencode((string) $from) . '&to=' . rawurlencode((string) $to);

$reportLinks = [
    ['href' => '/reports', 'title' => 'Centro de reportes', 'copy' => 'Ventas, compras, gastos e inventario en una sola vista.'],
    ['href' => '/charts', 'title' => 'Graficas y analitica', 'copy' => 'Tendencias, ABC de productos y prediccion de ventas.'],
    ['href' => '/inventory/movements', 'title' => 'Movimientos', 'copy' => 'Entradas, salidas y trazabilidad del stock.'],
];
?>

<section class="ops-dashboard">
    <div class="ops-shell">

        <header class="ops-hero">
            <div class="ops-hero-main">
                <span class="ops-eyebrow">
                    <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                    Centro de control &middot; <?= e(company()['name'] ?? 'Inventario') ?>
                </span>
                <h2><?= e($greeting) ?>, <?= e($firstName) ?></h2>
                <p class="ops-hero-date"><?= e($todayLabel) ?></p>

                <p class="ops-hero-status">
                    <?php if ($lowStockCount > 0): ?>
                        <span class="ops-status-chip ops-status-chip--alert">
                            <i class="bi bi-box-seam" aria-hidden="true"></i>
                            <?= $lowStockCount ?> producto<?= $lowStockCount === 1 ? '' : 's' ?> por reponer
                        </span>
                    <?php else: ?>
                        <span class="ops-status-chip ops-status-chip--ok">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            Stock sano, sin productos bajo minimo
                        </span>
                    <?php endif; ?>
                    <span class="ops-status-chip">
                        <i class="bi bi-cash-coin" aria-hidden="true"></i>
                        Por cobrar <?= money($receivablesSecondary) ?> <?= $secondaryCurrency ?>
                    </span>
                    <span class="ops-status-chip">
                        <i class="bi bi-currency-exchange" aria-hidden="true"></i>
                        1 <?= $baseCurrencyEscaped ?> = <?= money($exchangeRate) ?> <?= $secondaryCurrency ?>
                    </span>
                </p>

                <form class="ops-search" method="get" action="<?= e(app_url('/inventory')) ?>" role="search">
                    <i class="bi bi-upc-scan" aria-hidden="true"></i>
                    <input type="search" name="q" placeholder="Buscar producto por nombre o SKU..." aria-label="Buscar producto o SKU">
                    <button type="submit" class="ops-btn ops-btn-primary">Buscar</button>
                </form>

                <div class="ops-hero-actions">
                    <a class="ops-btn ops-btn-primary" href="<?= e(app_url('/purchases')) ?>">
                        <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Registrar entrada
                    </a>
                    <a class="ops-btn ops-btn-outline" href="<?= e(app_url('/invoices')) ?>">
                        <i class="bi bi-receipt" aria-hidden="true"></i> Nueva factura
                    </a>
                    <a class="ops-btn ops-btn-outline" href="<?= e(app_url('/inventory/movements')) ?>">
                        <i class="bi bi-arrow-left-right" aria-hidden="true"></i> Movimientos
                    </a>
                    <a class="ops-btn ops-btn-ghost" href="<?= e(app_url($dashboardPdfUrl)) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exportar PDF
                    </a>
                </div>
            </div>

            <aside class="ops-hero-side">
                <div class="ops-hero-side-head">
                    <span class="ops-eyebrow ops-eyebrow--quiet">Periodo analizado</span>
                    <strong><?= e($rangeLabel) ?></strong>
                </div>

                <form class="ops-range" method="get" action="<?= e(app_url('/dashboard')) ?>">
                    <label>
                        <span>Desde</span>
                        <input type="date" name="from" value="<?= e($from) ?>">
                    </label>
                    <label>
                        <span>Hasta</span>
                        <input type="date" name="to" value="<?= e($to) ?>">
                    </label>
                    <button class="ops-btn ops-btn-primary" type="submit">Actualizar periodo</button>
                </form>

                <div class="ops-quick-ranges" role="group" aria-label="Rangos rapidos" data-ops-quick-ranges>
                    <button type="button" data-range="today">Hoy</button>
                    <button type="button" data-range="7">7 dias</button>
                    <button type="button" data-range="month">Este mes</button>
                </div>
            </aside>
        </header>

        <!-- Salud del inventario: lo primero que importa en un sistema de stock -->
        <section class="ops-section" aria-labelledby="ops-inv-head">
            <div class="ops-section-head">
                <h3 id="ops-inv-head">Estado del inventario</h3>
                <a class="ops-link" href="<?= e(app_url('/inventory')) ?>">Ver catalogo completo <i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>
            </div>

            <div class="ops-inv-grid">
                <article class="ops-stat-card <?= $lowStockCount > 0 ? 'ops-stat-card--alert' : 'ops-stat-card--ok' ?>">
                    <div class="ops-stat-head">
                        <span><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Productos bajo minimo</span>
                    </div>
                    <strong><?= $lowStockCount ?></strong>
                    <small>
                        <?php if ($lowStockCount > 0): ?>
                            <?= $outOfStockCount ?> sin existencia &middot; <?= number_format($reorderUnits, 0, ',', '.') ?> und por reponer
                        <?php else: ?>
                            Todo por encima del minimo
                        <?php endif; ?>
                    </small>
                </article>

                <article class="ops-stat-card ops-stat-card--brand">
                    <div class="ops-stat-head">
                        <span><i class="bi bi-boxes" aria-hidden="true"></i> Valor del inventario</span>
                    </div>
                    <strong><?= money($inventoryBase) ?> <small class="ops-cur"><?= $baseCurrencyEscaped ?></small></strong>
                    <small>~ <?= money($inventorySecondary) ?> <?= $secondaryCurrency ?> al costo actual</small>
                </article>

                <article class="ops-stat-card">
                    <div class="ops-stat-head">
                        <span><i class="bi bi-upc" aria-hidden="true"></i> SKUs en catalogo</span>
                    </div>
                    <strong><?= number_format($productCount, 0, ',', '.') ?></strong>
                    <small><?= $supplierCount ?> proveedores &middot; <?= $clientCount ?> clientes</small>
                </article>
            </div>
        </section>

        <!-- Movimiento del periodo -->
        <section class="ops-section" aria-labelledby="ops-flow-head">
            <div class="ops-section-head">
                <h3 id="ops-flow-head">Movimiento del periodo</h3>
                <span class="ops-section-note">Montos en <?= $baseCurrencyEscaped ?> (referencia en <?= $secondaryCurrency ?>)</span>
            </div>

            <div class="ops-metric-grid">
                <article class="ops-metric ops-metric--sales">
                    <div class="ops-metric-head"><span>Ventas</span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
                    <strong><?= money($salesBase) ?> <small class="ops-cur"><?= $baseCurrencyEscaped ?></small></strong>
                    <small class="ops-metric-sub">~ <?= money($salesSecondary) ?> <?= $secondaryCurrency ?></small>
                </article>
                <article class="ops-metric ops-metric--purchases">
                    <div class="ops-metric-head"><span>Compras</span><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i></div>
                    <strong><?= money($purchasesBase) ?> <small class="ops-cur"><?= $baseCurrencyEscaped ?></small></strong>
                    <small class="ops-metric-sub">~ <?= money($purchasesSecondary) ?> <?= $secondaryCurrency ?></small>
                </article>
                <article class="ops-metric ops-metric--expenses">
                    <div class="ops-metric-head"><span>Gastos</span><i class="bi bi-wallet2" aria-hidden="true"></i></div>
                    <strong><?= money($expensesBase) ?> <small class="ops-cur"><?= $baseCurrencyEscaped ?></small></strong>
                    <small class="ops-metric-sub">~ <?= money($expensesSecondary) ?> <?= $secondaryCurrency ?></small>
                </article>
                <article class="ops-metric ops-metric--balance">
                    <div class="ops-metric-head"><span>Por cobrar / por pagar</span><i class="bi bi-arrow-left-right" aria-hidden="true"></i></div>
                    <strong class="ops-metric-split">
                        <span class="ops-pos"><?= money($receivablesSecondary) ?></span>
                        <span class="ops-neg"><?= money($payablesSecondary) ?></span>
                    </strong>
                    <small class="ops-metric-sub">CxC vs CxP abiertas en <?= $secondaryCurrency ?></small>
                </article>
            </div>

            <div class="ops-chart-grid">
                <article class="ops-panel">
                    <div class="ops-panel-head">
                        <div>
                            <h4>Flujo diario</h4>
                            <p>Ventas, compras y gastos dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <?php if (array_sum($flowSales) + array_sum($flowPurchases) + array_sum($flowExpenses) > 0): ?>
                        <div class="ops-chart-shell"><canvas id="flowChart" aria-label="Flujo diario" role="img"></canvas></div>
                    <?php else: ?>
                        <div class="ops-empty"><i class="bi bi-graph-up" aria-hidden="true"></i> Sin movimientos registrados en este periodo.</div>
                    <?php endif; ?>
                </article>

                <article class="ops-panel">
                    <div class="ops-panel-head">
                        <div>
                            <h4>Distribucion</h4>
                            <p>Como se reparte la actividad economica del periodo.</p>
                        </div>
                    </div>
                    <?php if (array_sum($compositionValues) > 0): ?>
                        <div class="ops-chart-shell ops-chart-shell--donut"><canvas id="compositionChart" aria-label="Distribucion del periodo" role="img"></canvas></div>
                    <?php else: ?>
                        <div class="ops-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i> Aun no hay datos para distribuir.</div>
                    <?php endif; ?>
                </article>
            </div>
        </section>

        <!-- Reposicion prioritaria + productos que mueven la caja -->
        <section class="ops-section ops-section--split" aria-labelledby="ops-reorder-head">
            <article class="ops-panel ops-panel--reorder">
                <div class="ops-panel-head">
                    <div>
                        <h4 id="ops-reorder-head">Reposicion prioritaria</h4>
                        <p>Productos en o por debajo del minimo, ordenados por urgencia.</p>
                    </div>
                    <a class="ops-link" href="<?= e(app_url('/purchases')) ?>">Generar compra <i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>
                </div>

                <?php if ($alerts): ?>
                    <ul class="ops-reorder-list">
                        <?php foreach ($alerts as $alert): ?>
                            <?php
                            $aStock = (float) ($alert['stock'] ?? 0);
                            $aMin = (float) ($alert['stock_min'] ?? 0);
                            $aUnit = trim((string) ($alert['unit_label'] ?? '')) ?: 'und';
                            $aNeed = max($aMin - $aStock, 0);
                            $isOut = $aStock <= 0;
                            ?>
                            <li class="ops-reorder-row">
                                <span class="ops-reorder-mark <?= $isOut ? 'is-critical' : 'is-warning' ?>" aria-hidden="true"></span>
                                <div class="ops-reorder-info">
                                    <strong><?= e((string) ($alert['name'] ?? 'Producto')) ?></strong>
                                    <small>
                                        SKU <?= e((string) ($alert['sku'] ?? 's/n')) ?>
                                        <?php if (!empty($alert['category_name'])): ?>
                                            &middot; <?= e((string) $alert['category_name']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="ops-reorder-stock">
                                    <span class="ops-chip <?= $isOut ? 'ops-chip--critical' : 'ops-chip--warning' ?>">
                                        <?= rtrim(rtrim(number_format($aStock, 2, ',', '.'), '0'), ',') ?> / <?= rtrim(rtrim(number_format($aMin, 2, ',', '.'), '0'), ',') ?> <?= e($aUnit) ?>
                                    </span>
                                    <?php if ($aNeed > 0): ?>
                                        <small>Reponer <?= rtrim(rtrim(number_format($aNeed, 2, ',', '.'), '0'), ',') ?> <?= e($aUnit) ?></small>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="ops-empty ops-empty--ok">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        Ningun producto esta bajo el minimo. El stock esta bajo control.
                    </div>
                <?php endif; ?>
            </article>

            <article class="ops-panel">
                <div class="ops-panel-head">
                    <div>
                        <h4>Productos que mueven la caja</h4>
                        <p>Top por monto facturado (incluye notas de entrega).</p>
                    </div>
                </div>
                <?php if ($topProductTotals && array_sum($topProductTotals) > 0): ?>
                    <div class="ops-chart-shell ops-chart-shell--compact"><canvas id="topProductsChart" aria-label="Productos mas vendidos" role="img"></canvas></div>
                <?php else: ?>
                    <div class="ops-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i> No hubo ventas ni despachos en este rango.</div>
                <?php endif; ?>
            </article>
        </section>

        <!-- Accesos y reportes -->
        <section class="ops-section ops-section--split" aria-labelledby="ops-quick-head">
            <article class="ops-panel">
                <div class="ops-panel-head">
                    <div>
                        <h4 id="ops-quick-head">Operaciones frecuentes</h4>
                        <p>Atajos a las acciones del dia a dia.</p>
                    </div>
                </div>
                <div class="ops-link-list">
                    <a class="ops-link-card" href="<?= e(app_url('/purchases')) ?>">
                        <div><strong>Compras</strong><small>Registra entradas de mercancia y actualiza costos.</small></div>
                        <span class="ops-tag">Entrada</span>
                    </a>
                    <a class="ops-link-card" href="<?= e(app_url('/invoices')) ?>">
                        <div><strong>Facturacion</strong><small>Emite facturas con cliente, productos y PDF.</small></div>
                        <span class="ops-tag">Venta</span>
                    </a>
                    <a class="ops-link-card" href="<?= e(app_url('/delivery-notes')) ?>">
                        <div><strong>Notas de entrega</strong><small>Gestiona despachos y salidas de almacen.</small></div>
                        <span class="ops-tag">Despacho</span>
                    </a>
                    <a class="ops-link-card" href="<?= e(app_url('/inventory')) ?>">
                        <div><strong>Catalogo</strong><small>Productos, categorias, existencias y minimos.</small></div>
                        <span class="ops-tag">Stock</span>
                    </a>
                </div>
            </article>

            <article class="ops-panel">
                <div class="ops-panel-head">
                    <div>
                        <h4>Reportes y analitica</h4>
                        <p>Consultas gerenciales listas para abrir.</p>
                    </div>
                </div>
                <div class="ops-link-list">
                    <?php foreach ($reportLinks as $link): ?>
                        <a class="ops-link-card ops-link-card--report" href="<?= e(app_url($link['href'])) ?>">
                            <div><strong><?= e($link['title']) ?></strong><small><?= e($link['copy']) ?></small></div>
                            <span class="ops-tag ops-tag--report">Abrir</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

    </div>
</section>

<style>
/* Tokens locales: scopeados a .ops-dashboard para no pisar el shell global */
.ops-dashboard {
    --ops-bg-1: rgba(15, 118, 110, 0.05);
    --ops-bg-2: rgba(29, 78, 216, 0.05);
    --ops-surface: #ffffff;
    --ops-text: #0f172a;
    --ops-soft: #475569;
    --ops-muted: #8a94a6;
    --ops-border: rgba(15, 23, 42, 0.08);
    --ops-border-strong: rgba(15, 23, 42, 0.14);
    --ops-brand: #0f766e;
    --ops-brand-strong: #115e59;
    --ops-brand-soft: rgba(15, 118, 110, 0.10);
    --ops-blue: #1d4ed8;
    --ops-blue-soft: rgba(29, 78, 216, 0.10);
    --ops-amber: #b45309;
    --ops-amber-soft: rgba(217, 119, 6, 0.12);
    --ops-danger: #dc2626;
    --ops-danger-soft: rgba(220, 38, 38, 0.10);
    --ops-radius: 20px;
    --ops-radius-sm: 14px;
    --ops-shadow: 0 14px 38px rgba(15, 23, 42, 0.06);
    --ops-shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.05);
    --ops-ease: cubic-bezier(.2, .8, .2, 1);

    padding: 18px 26px 30px;
    color: var(--ops-text);
    background:
        radial-gradient(circle at top left, var(--ops-bg-1), transparent 26%),
        radial-gradient(circle at top right, var(--ops-bg-2), transparent 22%);
}

.ops-dashboard *,
.ops-dashboard *::before,
.ops-dashboard *::after { box-sizing: border-box; min-width: 0; }

.ops-shell { display: grid; gap: 20px; }

/* ---------- Hero ---------- */
.ops-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.9fr);
    gap: 20px;
    padding: 26px;
    border: 1px solid var(--ops-border);
    border-radius: 26px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(255, 255, 255, 0.88));
    box-shadow: var(--ops-shadow);
    animation: opsFade .5s var(--ops-ease) both;
}

.ops-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 13px;
    border-radius: 999px;
    background: var(--ops-brand-soft);
    color: var(--ops-brand-strong);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.ops-eyebrow--quiet { background: rgba(15, 23, 42, 0.05); color: var(--ops-soft); }

.ops-hero-main h2 {
    margin: 14px 0 4px;
    font-size: clamp(26px, 3vw, 36px);
    line-height: 1.06;
    letter-spacing: -0.03em;
    font-weight: 750;
}

.ops-hero-date { margin: 0; color: var(--ops-muted); font-size: 13px; text-transform: capitalize; }

.ops-hero-status { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 0; }

.ops-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 12px;
    border: 1px solid var(--ops-border);
    background: rgba(255, 255, 255, 0.7);
    color: var(--ops-soft);
    font-size: 12.5px;
    font-weight: 650;
}

.ops-status-chip i { font-size: 14px; }
.ops-status-chip--alert { border-color: rgba(220, 38, 38, 0.25); background: var(--ops-danger-soft); color: var(--ops-danger); }
.ops-status-chip--ok { border-color: rgba(15, 118, 110, 0.22); background: var(--ops-brand-soft); color: var(--ops-brand-strong); }

.ops-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
    padding: 7px 8px 7px 14px;
    border: 1px solid var(--ops-border);
    border-radius: 16px;
    background: #fff;
    box-shadow: var(--ops-shadow-sm);
    transition: border-color .2s var(--ops-ease), box-shadow .2s var(--ops-ease);
}

.ops-search:focus-within { border-color: rgba(15, 118, 110, 0.4); box-shadow: 0 0 0 4px var(--ops-brand-soft); }
.ops-search > i { color: var(--ops-muted); font-size: 18px; }
.ops-search input { flex: 1; border: 0; outline: none; background: transparent; font-size: 14px; padding: 8px 0; color: var(--ops-text); }

.ops-hero-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }

.ops-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 13px;
    border: 1px solid transparent;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: transform .18s var(--ops-ease), box-shadow .18s var(--ops-ease), background .18s var(--ops-ease), border-color .18s var(--ops-ease);
}

.ops-btn:hover { transform: translateY(-1px); }
.ops-btn i { font-size: 15px; }
.ops-btn-primary { background: var(--ops-brand); color: #fff; box-shadow: 0 10px 24px rgba(15, 118, 110, 0.2); }
.ops-btn-primary:hover { background: var(--ops-brand-strong); }
.ops-btn-outline { background: #fff; color: var(--ops-text); border-color: var(--ops-border-strong); }
.ops-btn-outline:hover { border-color: var(--ops-brand); color: var(--ops-brand-strong); }
.ops-btn-ghost { background: transparent; color: var(--ops-soft); }
.ops-btn-ghost:hover { background: rgba(15, 23, 42, 0.04); }

/* Hero aside */
.ops-hero-side {
    display: grid;
    gap: 14px;
    align-content: start;
    padding: 20px;
    border: 1px solid var(--ops-border);
    border-radius: 22px;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(241, 245, 249, 0.9));
}

.ops-hero-side-head strong { display: block; margin-top: 8px; font-size: 18px; letter-spacing: -0.02em; }

.ops-range { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ops-range label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: var(--ops-soft); }
.ops-range button { grid-column: 1 / -1; }
.ops-range input {
    width: 100%;
    min-height: 44px;
    padding: 0 12px;
    border: 1px solid var(--ops-border);
    border-radius: 13px;
    background: #fff;
    color: var(--ops-text);
    outline: none;
    transition: border-color .2s var(--ops-ease), box-shadow .2s var(--ops-ease);
}
.ops-range input:focus { border-color: rgba(15, 118, 110, 0.4); box-shadow: 0 0 0 4px var(--ops-brand-soft); }

.ops-quick-ranges { display: flex; flex-wrap: wrap; gap: 8px; }
.ops-quick-ranges button {
    border: 1px solid var(--ops-border);
    background: #fff;
    color: var(--ops-soft);
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12.5px;
    font-weight: 650;
    cursor: pointer;
    transition: border-color .18s var(--ops-ease), background .18s var(--ops-ease), color .18s var(--ops-ease);
}
.ops-quick-ranges button:hover { border-color: var(--ops-brand); background: var(--ops-brand-soft); color: var(--ops-brand-strong); }

/* ---------- Sections ---------- */
.ops-section { display: grid; gap: 14px; animation: opsFade .6s var(--ops-ease) both; }
.ops-section--split { grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr); gap: 18px; align-items: start; }
.ops-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.ops-section-head h3 { margin: 0; font-size: 16px; letter-spacing: -0.01em; }
.ops-section-note { color: var(--ops-muted); font-size: 12.5px; }

.ops-link { display: inline-flex; align-items: center; gap: 2px; color: var(--ops-brand-strong); font-size: 13px; font-weight: 700; text-decoration: none; }
.ops-link:hover { text-decoration: underline; }
.ops-link i { font-size: 18px; }

/* Inventory stat cards */
.ops-inv-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.ops-stat-card {
    padding: 20px;
    border: 1px solid var(--ops-border);
    border-radius: var(--ops-radius);
    background: var(--ops-surface);
    box-shadow: var(--ops-shadow-sm);
    transition: transform .2s var(--ops-ease), box-shadow .2s var(--ops-ease);
}
.ops-stat-card:hover { transform: translateY(-2px); box-shadow: var(--ops-shadow); }
.ops-stat-head span { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: var(--ops-soft); }
.ops-stat-head i { font-size: 15px; }
.ops-stat-card strong { display: block; margin: 12px 0 4px; font-size: clamp(26px, 2.4vw, 34px); line-height: 1.05; letter-spacing: -0.03em; }
.ops-stat-card small { color: var(--ops-muted); font-size: 12.5px; }
.ops-cur { font-size: 0.5em; font-weight: 700; color: var(--ops-muted); letter-spacing: 0; }
.ops-stat-card--alert { background: linear-gradient(180deg, var(--ops-danger-soft), #fff); border-color: rgba(220, 38, 38, 0.22); }
.ops-stat-card--alert .ops-stat-head span,
.ops-stat-card--alert strong { color: var(--ops-danger); }
.ops-stat-card--ok { background: linear-gradient(180deg, var(--ops-brand-soft), #fff); }
.ops-stat-card--brand { background: linear-gradient(180deg, var(--ops-blue-soft), #fff); }

/* Metric grid (period) */
.ops-metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.ops-metric {
    padding: 18px;
    border: 1px solid var(--ops-border);
    border-radius: var(--ops-radius);
    background: var(--ops-surface);
    box-shadow: var(--ops-shadow-sm);
    transition: transform .2s var(--ops-ease), box-shadow .2s var(--ops-ease);
}
.ops-metric:hover { transform: translateY(-2px); box-shadow: var(--ops-shadow); }
.ops-metric-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ops-metric-head span { font-size: 13px; font-weight: 700; color: var(--ops-soft); }
.ops-metric-head i { color: var(--ops-muted); font-size: 16px; }
.ops-metric strong { display: block; margin: 12px 0 3px; font-size: clamp(20px, 1.8vw, 26px); line-height: 1.1; letter-spacing: -0.02em; }
.ops-metric-sub { color: var(--ops-muted); font-size: 12.5px; }
.ops-metric-split { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
.ops-metric-split .ops-pos { color: var(--ops-brand-strong); }
.ops-metric-split .ops-neg { color: var(--ops-danger); }
.ops-metric-split .ops-neg::before { content: "/ "; color: var(--ops-muted); }

/* Panels & charts */
.ops-chart-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.9fr); gap: 18px; }
.ops-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    border: 1px solid var(--ops-border);
    border-radius: var(--ops-radius);
    background: var(--ops-surface);
    box-shadow: var(--ops-shadow-sm);
}
.ops-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.ops-panel-head h4 { margin: 0; font-size: 15px; letter-spacing: -0.01em; }
.ops-panel-head p { margin: 4px 0 0; color: var(--ops-soft); font-size: 13px; line-height: 1.5; }
.ops-chart-shell { position: relative; width: 100%; min-height: 300px; }
.ops-chart-shell--compact { min-height: 260px; }
.ops-chart-shell--donut { min-height: 290px; display: grid; place-items: center; }
.ops-chart-shell canvas { max-width: 100%; }

/* Empty states */
.ops-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 22px;
    border: 1px dashed var(--ops-border-strong);
    border-radius: var(--ops-radius-sm);
    background: rgba(248, 250, 252, 0.8);
    color: var(--ops-soft);
    font-size: 13.5px;
    line-height: 1.5;
}
.ops-empty i { font-size: 20px; color: var(--ops-muted); }
.ops-empty--ok { border-style: solid; border-color: rgba(15, 118, 110, 0.2); background: var(--ops-brand-soft); color: var(--ops-brand-strong); }
.ops-empty--ok i { color: var(--ops-brand); }

/* Reorder list */
.ops-panel--reorder { gap: 12px; }
.ops-reorder-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.ops-reorder-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--ops-border);
    border-radius: var(--ops-radius-sm);
    background: rgba(255, 255, 255, 0.7);
    transition: border-color .18s var(--ops-ease), background .18s var(--ops-ease);
}
.ops-reorder-row:hover { border-color: var(--ops-border-strong); background: #fff; }
.ops-reorder-mark { width: 8px; height: 36px; border-radius: 6px; }
.ops-reorder-mark.is-critical { background: var(--ops-danger); }
.ops-reorder-mark.is-warning { background: var(--ops-amber); }
.ops-reorder-info strong { display: block; font-size: 14px; line-height: 1.3; }
.ops-reorder-info small { display: block; margin-top: 2px; color: var(--ops-muted); font-size: 12px; }
.ops-reorder-stock { text-align: right; }
.ops-reorder-stock small { display: block; margin-top: 4px; color: var(--ops-muted); font-size: 11.5px; }
.ops-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}
.ops-chip--critical { background: var(--ops-danger-soft); color: var(--ops-danger); }
.ops-chip--warning { background: var(--ops-amber-soft); color: var(--ops-amber); }

/* Link cards */
.ops-link-list { display: grid; gap: 10px; }
.ops-link-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 15px 16px;
    border: 1px solid var(--ops-border);
    border-radius: var(--ops-radius-sm);
    background: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    color: inherit;
    transition: transform .18s var(--ops-ease), border-color .18s var(--ops-ease), box-shadow .18s var(--ops-ease), background .18s var(--ops-ease);
}
.ops-link-card:hover { transform: translateY(-2px); border-color: rgba(15, 118, 110, 0.18); box-shadow: var(--ops-shadow-sm); background: #fff; }
.ops-link-card strong { display: block; font-size: 14px; }
.ops-link-card small { display: block; margin-top: 3px; color: var(--ops-soft); font-size: 12.5px; line-height: 1.45; }
.ops-tag {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 7px 12px;
    border-radius: 999px;
    background: var(--ops-brand-soft);
    color: var(--ops-brand-strong);
    font-size: 11.5px;
    font-weight: 800;
}
.ops-tag--report { background: var(--ops-blue-soft); color: var(--ops-blue); }

@keyframes opsFade { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) { .ops-dashboard *, .ops-dashboard *::before, .ops-dashboard *::after { animation: none !important; transition: none !important; } }

/* ---------- Responsive ---------- */
@media (max-width: 1180px) {
    .ops-hero { grid-template-columns: 1fr; }
    .ops-inv-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .ops-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ops-chart-grid { grid-template-columns: 1fr; }
    .ops-section--split { grid-template-columns: 1fr; }
}

@media (max-width: 820px) {
    .ops-dashboard { padding: 16px; }
    .ops-hero { padding: 18px; }
    .ops-inv-grid { grid-template-columns: 1fr; }
    .ops-range { grid-template-columns: 1fr; }
    .ops-hero-actions { flex-direction: column; }
    .ops-hero-actions .ops-btn { width: 100%; justify-content: center; }
}

@media (max-width: 560px) {
    .ops-metric-grid { grid-template-columns: 1fr; }
    .ops-reorder-row { grid-template-columns: auto minmax(0, 1fr); }
    .ops-reorder-stock { grid-column: 2; text-align: left; margin-top: 4px; }
}
</style>

<script>
(function () {
    "use strict";

    const flowLabels = <?= json_encode($flowLabels, JSON_UNESCAPED_UNICODE) ?>;
    const flowSales = <?= json_encode($flowSales, JSON_NUMERIC_CHECK) ?>;
    const flowPurchases = <?= json_encode($flowPurchases, JSON_NUMERIC_CHECK) ?>;
    const flowExpenses = <?= json_encode($flowExpenses, JSON_NUMERIC_CHECK) ?>;
    const compositionLabels = <?= json_encode($compositionLabels, JSON_UNESCAPED_UNICODE) ?>;
    const compositionValues = <?= json_encode($compositionValues, JSON_NUMERIC_CHECK) ?>;
    const topProductLabels = <?= json_encode($topProductLabels, JSON_UNESCAPED_UNICODE) ?>;
    const topProductTotals = <?= json_encode($topProductTotals, JSON_NUMERIC_CHECK) ?>;

    const fmt = new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (v) => fmt.format(Number(v) || 0);

    const ink = "#475569";
    const grid = "rgba(15, 23, 42, 0.07)";
    const brand = "#0f766e";
    const blue = "#1d4ed8";
    const amber = "#d97706";

    if (typeof Chart === "undefined") { return; }

    Chart.defaults.font.family = "'Segoe UI', Tahoma, sans-serif";
    Chart.defaults.color = ink;

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        animation: { duration: 650, easing: "easeOutCubic" },
        plugins: {
            legend: {
                position: "bottom",
                labels: { usePointStyle: true, boxWidth: 9, boxHeight: 9, padding: 16, font: { size: 12, weight: "600" } },
            },
            tooltip: {
                backgroundColor: "rgba(15, 23, 42, 0.94)",
                titleColor: "#fff",
                bodyColor: "#fff",
                padding: 12,
                cornerRadius: 12,
                callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y ?? ctx.parsed)}` },
            },
        },
    };

    const flowCanvas = document.getElementById("flowChart");
    if (flowCanvas) {
        new Chart(flowCanvas, {
            type: "line",
            data: {
                labels: flowLabels,
                datasets: [
                    { label: "Ventas", data: flowSales, borderColor: brand, backgroundColor: "rgba(15,118,110,0.12)", tension: .36, fill: true, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4 },
                    { label: "Compras", data: flowPurchases, borderColor: blue, tension: .36, fill: false, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                    { label: "Gastos", data: flowExpenses, borderColor: amber, tension: .36, fill: false, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                ],
            },
            options: {
                ...baseOptions,
                scales: {
                    x: { grid: { display: false }, ticks: { color: ink, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: ink, callback: (v) => money(v) } },
                },
            },
        });
    }

    const compositionCanvas = document.getElementById("compositionChart");
    if (compositionCanvas) {
        new Chart(compositionCanvas, {
            type: "doughnut",
            data: {
                labels: compositionLabels,
                datasets: [{ data: compositionValues, backgroundColor: [brand, blue, amber], borderWidth: 0, hoverOffset: 8 }],
            },
            options: {
                ...baseOptions,
                cutout: "68%",
                plugins: {
                    ...baseOptions.plugins,
                    legend: { position: window.innerWidth > 900 ? "right" : "bottom", labels: { usePointStyle: true, boxWidth: 9, padding: 14, font: { size: 12, weight: "600" } } },
                    tooltip: { ...baseOptions.plugins.tooltip, callbacks: { label: (ctx) => `${ctx.label}: ${money(ctx.raw)}` } },
                },
            },
        });
    }

    const topCanvas = document.getElementById("topProductsChart");
    if (topCanvas && topProductLabels.length) {
        new Chart(topCanvas, {
            type: "bar",
            data: {
                labels: topProductLabels,
                datasets: [{ label: "Facturado", data: topProductTotals, backgroundColor: brand, borderRadius: 8, borderSkipped: false, maxBarThickness: 22 }],
            },
            options: {
                ...baseOptions,
                indexAxis: "y",
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: ink } },
                    x: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: ink, callback: (v) => money(v) } },
                },
            },
        });
    }

    // Rangos rapidos: rellenan el formulario de periodo y lo envian
    const quick = document.querySelector("[data-ops-quick-ranges]");
    const rangeForm = document.querySelector(".ops-range");
    if (quick && rangeForm) {
        const fromInput = rangeForm.querySelector("input[name='from']");
        const toInput = rangeForm.querySelector("input[name='to']");
        const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

        quick.querySelectorAll("[data-range]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                let start = new Date(today);
                const range = btn.dataset.range;
                if (range === "month") {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                } else if (range === "7") {
                    start.setDate(today.getDate() - 6);
                }
                if (fromInput) fromInput.value = iso(start);
                if (toInput) toInput.value = iso(today);
                rangeForm.submit();
            });
        });
    }
})();
</script>
