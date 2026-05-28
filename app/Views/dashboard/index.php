<div class="grid cards">
    <div class="card stat">
        <span>Productos</span>
        <strong><?= (int) $stats["products"] ?></strong>
    </div>
    <div class="card stat">
        <span>Clientes</span>
        <strong><?= (int) $stats["clients"] ?></strong>
    </div>
    <div class="card stat">
        <span>Proveedores</span>
        <strong><?= (int) $stats["suppliers"] ?></strong>
    </div>
    <div class="card stat">
        <span>Ventas (<?= e(secondary_currency()) ?>)</span>
        <strong><?= money($stats["sales"]) ?></strong>
    </div>
    <div class="card stat">
        <span>Compras (<?= e(secondary_currency()) ?>)</span>
        <strong><?= money($stats["purchases"]) ?></strong>
    </div>
    <div class="card stat">
        <span>Gastos (<?= e(secondary_currency()) ?>)</span>
        <strong><?= money($stats["expenses"]) ?></strong>
    </div>
    <div class="card stat">
        <span>CxC abiertas (<?= e(secondary_currency()) ?>)</span>
        <strong><?= money($stats["receivables"] ?? 0) ?></strong>
    </div>
    <div class="card stat">
        <span>CxP abiertas (<?= e(secondary_currency()) ?>)</span>
        <strong><?= money($stats["payables"] ?? 0) ?></strong>
    </div>
    <div class="card stat">
        <span>Inventario valorizado</span>
        <strong><?= money($stats["inventory_value"]) ?></strong>
    </div>
    <div class="card stat">
        <span>Stock bajo minimo</span>
        <strong><?= (int) $stats["low_stock"] ?></strong>
    </div>
</div>

<div class="grid two">
    <div class="card">
        <h3>Resumen operativo</h3>
        <p>Moneda base: <strong><?= e(base_currency()) ?></strong></p>
        <p>Moneda secundaria: <strong><?= e(secondary_currency()) ?></strong></p>
        <p>
            Tasa vigente:
            <strong><?= $rate ? money($rate["rate"]) : money((float) env("DEFAULT_EXCHANGE_RATE", 1)) ?></strong>
        </p>
        <p>Empresa: <strong><?= e(company()["name"]) ?></strong></p>
        <p>RIF: <strong><?= e(company()["rif"]) ?></strong></p>
    </div>

    <div class="card">
        <h3>Accesos rapidos</h3>
        <div class="quick-links">
            <a class="btn" href="/invoices">Nueva factura</a>
            <a class="btn" href="/purchases">Nueva compra</a>
            <a class="btn" href="/expenses">Registrar gasto</a>
            <a class="btn" href="/reports?type=receivables">Ver CxC</a>
            <a class="btn" href="/reports?type=payables">Ver CxP</a>
            <a class="btn" href="/reports">Ver reportes</a>
        </div>
    </div>
</div>
