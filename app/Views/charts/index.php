<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$baseCurrency = strtoupper((string) base_currency());
$secondaryCurrency = strtoupper((string) secondary_currency());
$rateValue = (float) (system_exchange_rate($to) ?: env('DEFAULT_EXCHANGE_RATE', 1));

$abcRows = $abc['rows'] ?? [];
$abcCounts = $abc['classCount'] ?? ['A' => 0, 'B' => 0, 'C' => 0];

$topProductsLabels = array_map(static fn (array $r): string => (string) ($r['name'] ?? 'Producto'), $topProducts);
$topProductsAmounts = array_map(static fn (array $r): float => (float) ($r['total'] ?? 0), $topProducts);
$topProductsUnits = array_map(static fn (array $r): float => (float) ($r['quantity'] ?? 0), $topProducts);

$topClientsLabels = array_map(static fn (array $r): string => (string) ($r['name'] ?? 'Cliente'), $topClients);
$topClientsAmounts = array_map(static fn (array $r): float => (float) ($r['total'] ?? 0), $topClients);

$abcAmounts = array_map(static fn (array $r): float => (float) ($r['total'] ?? 0), $abcRows);
$abcCumulative = array_map(static fn (array $r): float => (float) ($r['cumulative'] ?? 0) * 100, $abcRows);
$abcLabels = array_map(static fn (array $r): string => (string) ($r['name'] ?? ''), $abcRows);
$abcClassColors = array_map(static fn (array $r): string => match ($r['class'] ?? 'C') {
    'A' => '#2f6f68',
    'B' => '#c89553',
    default => '#94a3b8',
}, $abcRows);

$pdfQuery = http_build_query(array_filter([
    'from' => $from,
    'to' => $to,
    'granularity' => $granularity,
], static fn ($v): bool => $v !== ''));
$pdfUrl = '/charts/pdf' . ($pdfQuery !== '' ? '?' . $pdfQuery : '');
?>

<section class="charts-page">
    <header class="charts-page-head">
        <div class="charts-page-head-title">
            <span class="eyebrow">Analitica</span>
            <h2>Graficas</h2>
            <p>Tendencias, comparativos y predicciones del negocio. Todo en una vista.</p>
            <div class="charts-page-head-actions">
                <a class="btn btn-pdf" href="<?= e(app_url($pdfUrl)) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    <span>Exportar PDF</span>
                </a>
            </div>
        </div>

        <form method="get" action="<?= e(app_url('/charts')) ?>" class="charts-filters" data-charts-filters>
            <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
            <label>Granularidad
                <select name="granularity">
                    <option value="auto" <?= ($_GET['granularity'] ?? '') === '' || ($_GET['granularity'] ?? '') === 'auto' ? 'selected' : '' ?>>Automatica</option>
                    <option value="day" <?= ($_GET['granularity'] ?? '') === 'day' ? 'selected' : '' ?>>Diaria</option>
                    <option value="week" <?= ($_GET['granularity'] ?? '') === 'week' ? 'selected' : '' ?>>Semanal</option>
                    <option value="month" <?= ($_GET['granularity'] ?? '') === 'month' ? 'selected' : '' ?>>Mensual</option>
                </select>
            </label>
            <div class="charts-filter-actions">
                <button type="submit" class="btn">Aplicar</button>
                <a class="btn btn-outline" href="<?= e(app_url('/charts')) ?>">Restablecer</a>
            </div>
            <div class="charts-quick-ranges" role="group" aria-label="Rangos rapidos">
                <button type="button" data-quick-range="7">Ultimos 7 dias</button>
                <button type="button" data-quick-range="30">Ultimos 30 dias</button>
                <button type="button" data-quick-range="month">Este mes</button>
                <button type="button" data-quick-range="year">Este ano</button>
                <button type="button" data-quick-range="365">Ultimos 12 meses</button>
            </div>
        </form>
    </header>

    <section class="charts-grid">
        <article class="card chart-card chart-card-span-2">
            <header class="section-head">
                <div>
                    <h3>Ventas globales por periodo</h3>
                    <p>Granularidad <strong><?= e($granularity === 'day' ? 'diaria' : ($granularity === 'week' ? 'semanal' : 'mensual')) ?></strong>. Montos en <?= e($secondaryCurrency) ?>.</p>
                </div>
            </header>
            <div class="chart-shell chart-shell--tall">
                <canvas id="salesByPeriodChart" aria-label="Ventas por periodo" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card chart-card-span-2">
            <header class="section-head">
                <div>
                    <h3>Comparativo: Ventas vs Compras vs Gastos</h3>
                    <p>Tres lineas con el mismo eje temporal para detectar margenes y meses de aprieto.</p>
                </div>
            </header>
            <div class="chart-shell chart-shell--tall">
                <canvas id="flowsCompareChart" aria-label="Comparativo de flujos" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card">
            <header class="section-head">
                <div>
                    <h3>Top productos</h3>
                    <p>Por monto facturado en el periodo. Tambien se muestran las unidades.</p>
                </div>
            </header>
            <div class="chart-shell">
                <canvas id="topProductsChart" aria-label="Top productos" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card">
            <header class="section-head">
                <div>
                    <h3>Top clientes</h3>
                    <p>Quien factura mas en el periodo seleccionado.</p>
                </div>
            </header>
            <div class="chart-shell">
                <canvas id="topClientsChart" aria-label="Top clientes" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card chart-card-span-2">
            <header class="section-head">
                <div>
                    <h3>Analisis ABC</h3>
                    <p>Productos ordenados por aporte al ingreso, con la curva acumulada que regla 80/20.</p>
                </div>
                <div class="abc-summary">
                    <span class="badge badge-ok">A: <?= (int) $abcCounts['A'] ?></span>
                    <span class="badge badge-neutral">B: <?= (int) $abcCounts['B'] ?></span>
                    <span class="badge badge-danger">C: <?= (int) $abcCounts['C'] ?></span>
                </div>
            </header>
            <div class="chart-shell chart-shell--tall">
                <canvas id="abcChart" aria-label="Analisis ABC" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card">
            <header class="section-head">
                <div>
                    <h3>Antiguedad de cuentas por cobrar</h3>
                    <p>Saldos pendientes al <?= e($to) ?>, agrupados por dias vencidos.</p>
                </div>
                <span class="chart-card-headline"><?= money($aging['total'] ?? 0) ?> <?= e($secondaryCurrency) ?></span>
            </header>
            <div class="chart-shell">
                <canvas id="agingChart" aria-label="Aging de cobros" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card">
            <header class="section-head">
                <div>
                    <h3>Ventas por metodo de pago</h3>
                    <p>Distribucion de los cobros aplicados en el periodo.</p>
                </div>
            </header>
            <div class="chart-shell">
                <canvas id="paymentMethodsChart" aria-label="Ventas por metodo de pago" role="img"></canvas>
            </div>
        </article>

        <article class="card chart-card chart-card-span-2">
            <header class="section-head">
                <div>
                    <h3>Prediccion de ventas</h3>
                    <p>Regresion lineal sobre los ultimos 12 meses, proyectada 3 meses hacia adelante.</p>
                </div>
            </header>
            <div class="chart-shell chart-shell--tall">
                <canvas id="forecastChart" aria-label="Prediccion de ventas" role="img"></canvas>
            </div>
        </article>
    </section>
</section>

<style>
.charts-page {
    padding: 14px 24px 28px;
    display: grid;
    gap: 18px;
}

.charts-page-head {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr);
    gap: 18px;
    align-items: start;
}

.charts-page-head h2 {
    margin: 0.2rem 0 0.25rem;
    font-size: 1.6rem;
    letter-spacing: -0.02em;
}

.charts-page-head p {
    margin: 0;
    color: var(--muted, #64748b);
    font-size: 0.92rem;
}

.charts-page-head-actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.charts-page-head-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.charts-page-head-actions .btn i {
    font-size: 1.05rem;
    line-height: 1;
}

.charts-filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
    gap: 0.75rem;
    align-items: end;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.94));
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}

.charts-filters label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
}

.charts-filters input,
.charts-filters select {
    min-height: 38px;
    padding: 0.45rem 0.6rem;
    border: 1px solid rgba(148, 163, 184, 0.4);
    border-radius: 10px;
    background: #fff;
    font-size: 0.88rem;
}

.charts-filter-actions {
    display: flex;
    gap: 0.45rem;
}

.charts-quick-ranges {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.1rem;
}

.charts-quick-ranges button {
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: #fff;
    color: #475569;
    border-radius: 999px;
    padding: 0.32rem 0.85rem;
    font-size: 0.78rem;
    cursor: pointer;
    transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.charts-quick-ranges button:hover {
    border-color: rgba(15, 118, 110, 0.45);
    background: rgba(15, 118, 110, 0.08);
    color: var(--brand-strong, #0f766e);
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.chart-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 1.1rem 1.2rem 1.25rem;
}

.chart-card-span-2 {
    grid-column: 1 / -1;
}

.chart-card .section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.8rem;
}

.chart-card .section-head h3 {
    margin: 0;
    font-size: 1.02rem;
}

.chart-card .section-head p {
    margin: 0.18rem 0 0;
    color: var(--muted, #64748b);
    font-size: 0.82rem;
    line-height: 1.4;
}

.chart-card-headline {
    color: var(--brand-strong, #0f766e);
    font-weight: 800;
    white-space: nowrap;
}

.abc-summary {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.chart-shell {
    position: relative;
    height: 280px;
}

.chart-shell--tall {
    height: 340px;
}

.chart-shell canvas {
    width: 100% !important;
    height: 100% !important;
}

@media (max-width: 1100px) {
    .charts-page-head {
        grid-template-columns: 1fr;
    }

    .charts-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .charts-grid {
        grid-template-columns: 1fr;
    }

    .chart-card-span-2 {
        grid-column: 1;
    }
}

@media (max-width: 640px) {
    .charts-page {
        padding: 12px 14px 24px;
    }

    .charts-filters {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function () {
    "use strict";

    const fmt = new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtMoney = (value) => fmt.format(Number(value) || 0);
    const fmtUnits = (value) => new Intl.NumberFormat("es-VE").format(Number(value) || 0);
    const baseCurrency = <?= json_encode($baseCurrency) ?>;
    const secondaryCurrency = <?= json_encode($secondaryCurrency) ?>;

    const fontColor = "#5f6c80";
    const gridColor = "rgba(15, 23, 42, 0.06)";

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: "easeOutCubic" },
        plugins: {
            legend: {
                position: "bottom",
                labels: { usePointStyle: true, boxWidth: 10, padding: 14, color: fontColor, font: { size: 12, weight: "600" } },
            },
            tooltip: {
                backgroundColor: "rgba(22, 32, 51, 0.94)",
                titleColor: "#fff",
                bodyColor: "#fff",
                padding: 12,
                cornerRadius: 12,
                displayColors: true,
            },
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: fontColor } },
            y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { color: fontColor, callback: (v) => fmtMoney(v) } },
        },
    };

    const cloneOptions = (overrides = {}) => ({
        ...baseOptions,
        ...overrides,
        plugins: { ...baseOptions.plugins, ...(overrides.plugins || {}) },
        scales: { ...baseOptions.scales, ...(overrides.scales || {}) },
    });

    // Ventas por periodo
    const salesData = <?= json_encode($salesByPeriod) ?>;
    const salesCanvas = document.getElementById("salesByPeriodChart");
    if (salesCanvas) {
        new Chart(salesCanvas, {
            type: "line",
            data: {
                labels: salesData.labels || [],
                datasets: [{
                    label: "Ventas",
                    data: salesData.values || [],
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderColor: "#2f6f68",
                    backgroundColor: "rgba(47, 111, 104, 0.15)",
                }],
            },
            options: cloneOptions(),
        });
    }

    // Comparativo
    const compareData = <?= json_encode($compareFlows) ?>;
    const compareCanvas = document.getElementById("flowsCompareChart");
    if (compareCanvas) {
        new Chart(compareCanvas, {
            type: "line",
            data: {
                labels: compareData.labels || [],
                datasets: [
                    { label: "Ventas", data: compareData.sales || [], borderColor: "#2f6f68", backgroundColor: "rgba(47,111,104,0.12)", tension: 0.35, fill: true, borderWidth: 2.4, pointRadius: 3, pointBackgroundColor: "#2f6f68" },
                    { label: "Compras", data: compareData.purchases || [], borderColor: "#2563eb", tension: 0.35, fill: false, borderWidth: 2.4, pointRadius: 3, pointBackgroundColor: "#2563eb" },
                    { label: "Gastos", data: compareData.expenses || [], borderColor: "#c89553", tension: 0.35, fill: false, borderWidth: 2.4, pointRadius: 3, pointBackgroundColor: "#c89553" },
                ],
            },
            options: cloneOptions(),
        });
    }

    // Top productos (barra con monto + linea con unidades)
    const topProductsCanvas = document.getElementById("topProductsChart");
    if (topProductsCanvas) {
        new Chart(topProductsCanvas, {
            type: "bar",
            data: {
                labels: <?= json_encode($topProductsLabels) ?>,
                datasets: [
                    { type: "bar", label: "Monto", data: <?= json_encode($topProductsAmounts) ?>, backgroundColor: "#2f6f68", borderRadius: 8, maxBarThickness: 22, yAxisID: "y" },
                    { type: "line", label: "Unidades", data: <?= json_encode($topProductsUnits) ?>, borderColor: "#c89553", backgroundColor: "rgba(200,149,83,0.12)", tension: 0.35, fill: false, borderWidth: 2, pointRadius: 3, yAxisID: "y1" },
                ],
            },
            options: cloneOptions({
                indexAxis: "y",
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: fontColor, callback: (v) => fmtMoney(v) } },
                    y: { grid: { display: false }, ticks: { color: fontColor } },
                    y1: { display: false },
                },
            }),
        });
    }

    // Top clientes
    const topClientsCanvas = document.getElementById("topClientsChart");
    if (topClientsCanvas) {
        new Chart(topClientsCanvas, {
            type: "bar",
            data: {
                labels: <?= json_encode($topClientsLabels) ?>,
                datasets: [{ label: "Monto", data: <?= json_encode($topClientsAmounts) ?>, backgroundColor: "#607d9b", borderRadius: 8, maxBarThickness: 22 }],
            },
            options: cloneOptions({
                indexAxis: "y",
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: fontColor, callback: (v) => fmtMoney(v) } },
                    y: { grid: { display: false }, ticks: { color: fontColor } },
                },
            }),
        });
    }

    // ABC
    const abcCanvas = document.getElementById("abcChart");
    if (abcCanvas) {
        const labels = <?= json_encode($abcLabels) ?>;
        const amounts = <?= json_encode($abcAmounts) ?>;
        const cumulative = <?= json_encode($abcCumulative) ?>;
        const colors = <?= json_encode($abcClassColors) ?>;
        new Chart(abcCanvas, {
            data: {
                labels,
                datasets: [
                    { type: "bar", label: "Aporte al ingreso", data: amounts, backgroundColor: colors, borderRadius: 6, yAxisID: "y" },
                    { type: "line", label: "Acumulado %", data: cumulative, borderColor: "#c45f5f", backgroundColor: "rgba(196,95,95,0.15)", tension: 0.25, fill: false, borderWidth: 2, pointRadius: 3, yAxisID: "y1" },
                ],
            },
            options: cloneOptions({
                scales: {
                    x: { grid: { display: false }, ticks: { color: fontColor, maxRotation: 35, minRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, position: "left", grid: { color: gridColor }, ticks: { color: fontColor, callback: (v) => fmtMoney(v) } },
                    y1: { beginAtZero: true, max: 100, position: "right", grid: { display: false }, ticks: { color: fontColor, callback: (v) => v + "%" } },
                },
            }),
        });
    }

    // Aging
    const agingCanvas = document.getElementById("agingChart");
    if (agingCanvas) {
        new Chart(agingCanvas, {
            type: "doughnut",
            data: {
                labels: <?= json_encode($aging['labels'] ?? []) ?>,
                datasets: [{
                    data: <?= json_encode($aging['values'] ?? []) ?>,
                    backgroundColor: ["#2f6f68", "#c89553", "#d97706", "#c45f5f", "#7f1d1d"],
                    borderWidth: 0,
                }],
            },
            options: cloneOptions({
                cutout: "62%",
                scales: {},
                plugins: {
                    ...baseOptions.plugins,
                    tooltip: {
                        ...baseOptions.plugins.tooltip,
                        callbacks: { label: (ctx) => `${ctx.label}: ${fmtMoney(ctx.raw)} ${secondaryCurrency}` },
                    },
                },
            }),
        });
    }

    // Metodos de pago
    const methodsCanvas = document.getElementById("paymentMethodsChart");
    if (methodsCanvas) {
        new Chart(methodsCanvas, {
            type: "doughnut",
            data: {
                labels: <?= json_encode($paymentMethods['labels'] ?? []) ?>,
                datasets: [{
                    data: <?= json_encode($paymentMethods['values'] ?? []) ?>,
                    backgroundColor: ["#2f6f68", "#607d9b", "#c89553", "#0ea5e9", "#a855f7", "#f97316"],
                    borderWidth: 0,
                }],
            },
            options: cloneOptions({
                cutout: "62%",
                scales: {},
                plugins: {
                    ...baseOptions.plugins,
                    tooltip: {
                        ...baseOptions.plugins.tooltip,
                        callbacks: { label: (ctx) => `${ctx.label}: ${fmtMoney(ctx.raw)} ${secondaryCurrency}` },
                    },
                },
            }),
        });
    }

    // Pronostico
    const forecastData = <?= json_encode($forecast) ?>;
    const forecastCanvas = document.getElementById("forecastChart");
    if (forecastCanvas) {
        new Chart(forecastCanvas, {
            type: "line",
            data: {
                labels: forecastData.labels || [],
                datasets: [
                    { label: "Historico", data: forecastData.historical || [], borderColor: "#2f6f68", backgroundColor: "rgba(47,111,104,0.12)", tension: 0.35, fill: true, borderWidth: 2.5, pointRadius: 0 },
                    { label: "Tendencia", data: forecastData.trend || [], borderColor: "#c89553", borderDash: [4, 4], tension: 0, fill: false, borderWidth: 2, pointRadius: 0 },
                    { label: "Pronostico", data: forecastData.forecast || [], borderColor: "#c45f5f", borderDash: [6, 4], tension: 0.25, fill: false, borderWidth: 2.5, pointRadius: 4 },
                ],
            },
            options: cloneOptions(),
        });
    }

    // Atajos de rango
    const form = document.querySelector("[data-charts-filters]");
    if (form) {
        const fromInput = form.querySelector("input[name='from']");
        const toInput = form.querySelector("input[name='to']");
        const fmtDate = (d) => d.toISOString().slice(0, 10);

        form.querySelectorAll("[data-quick-range]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                let start = new Date(today);
                const range = btn.dataset.quickRange;
                if (range === "month") {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                } else if (range === "year") {
                    start = new Date(today.getFullYear(), 0, 1);
                } else {
                    const days = parseInt(range, 10);
                    if (Number.isFinite(days)) {
                        start.setDate(today.getDate() - days + 1);
                    }
                }
                fromInput.value = fmtDate(start);
                toInput.value = fmtDate(today);
                form.submit();
            });
        });
    }
})();
</script>
