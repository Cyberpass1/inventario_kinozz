<?php
$records = $records ?? [];
$balance = $balance ?? ['assets' => [], 'liabilities' => [], 'equity' => []];
$summaryCards = $summaryCards ?? [];
$infoCards = $infoCards ?? [];
$baseCurrency = (string) ($baseCurrency ?? base_currency());
$secondaryCurrency = (string) ($secondaryCurrency ?? secondary_currency());
$currentRate = (float) ($currentRate ?? default_exchange_rate());
$reportingCurrency = $secondaryCurrency;
$canManageTreasury = (bool) ($canManageTreasury ?? false);
$inventoryBreakdown = (string) ($inventoryBreakdown ?? 'category');
$inventoryBreakdownOptions = is_array($inventoryBreakdownOptions ?? null) ? $inventoryBreakdownOptions : [];
$inventoryGroups = is_array($inventoryGroups ?? null) ? $inventoryGroups : [];
$inventoryOverview = is_array($inventoryOverview ?? null) ? $inventoryOverview : [];
$inventoryRecap = is_array($inventoryRecap ?? null) ? $inventoryRecap : [];
$balanceOverview = is_array($balanceOverview ?? null) ? $balanceOverview : [];
$treasuryMovements = is_array($treasuryMovements ?? null) ? $treasuryMovements : [];
$productSummary = is_array($productSummary ?? null) ? $productSummary : [];
$productSummaryOverview = is_array($productSummaryOverview ?? null) ? $productSummaryOverview : [];
$toReference = static fn (float $amount): string => '~ ' . money(convert_currency_amount($amount, $secondaryCurrency, $baseCurrency, $currentRate)) . ' ' . $baseCurrency;
$paymentStatusLabel = static fn (string $status): string => match ($status) {
    'paid' => 'Pagada',
    'partial' => 'Parcial',
    'overdue' => 'Vencida',
    'partial_overdue' => 'Parcial vencida',
    'cancelled' => 'Anulada',
    default => 'Pendiente',
};
$treasurySourceLabel = static fn (string|null $source): string => match ((string) $source) {
    'invoice_payment' => 'Cobro factura',
    'delivery_note_payment' => 'Cobro nota',
    'purchase_payment' => 'Pago compra',
    'expense' => 'Gasto',
    'treasury_adjustment' => 'Ajuste tesoreria',
    default => 'Movimiento manual',
};
$reportLinks = [
    ['type' => 'sales', 'label' => 'Ventas', 'title' => 'Facturacion', 'tag' => 'Comercial'],
    ['type' => 'delivery_notes', 'label' => 'Entregas', 'title' => 'Notas de entrega', 'tag' => 'Comercial'],
    ['type' => 'purchases', 'label' => 'Compras', 'title' => 'Abastecimiento', 'tag' => 'Operacion'],
    ['type' => 'expenses', 'label' => 'Gastos', 'title' => 'Egresos', 'tag' => 'Operacion'],
    ['type' => 'receivables', 'label' => 'Por cobrar', 'title' => 'Clientes pendientes', 'tag' => 'Cobranza'],
    ['type' => 'payables', 'label' => 'Por pagar', 'title' => 'Proveedores pendientes', 'tag' => 'Tesoreria'],
    ['type' => 'treasury', 'label' => 'Tesoreria', 'title' => 'Caja y bancos', 'tag' => 'Tesoreria'],
    ['type' => 'inventory', 'label' => 'Inventario', 'title' => 'Existencias', 'tag' => 'Control'],
    ['type' => 'movements', 'label' => 'Movimientos', 'title' => 'Trazabilidad', 'tag' => 'Control'],
    ['type' => 'journal', 'label' => 'Libro diario', 'title' => 'Asientos', 'tag' => 'Finanzas'],
    ['type' => 'ledger', 'label' => 'Libro mayor', 'title' => 'Mayor por cuentas', 'tag' => 'Finanzas'],
    ['type' => 'balance', 'label' => 'Balance general', 'title' => 'Resumen financiero', 'tag' => 'Finanzas'],
];
?>

<section class="page-header">
    <div>
        <span class="eyebrow">Reportes</span>
        <h2><?= e($title) ?></h2>
        <p><?= e($description) ?></p>
    </div>
    <div class="header-summary">
        <?php foreach ($summaryCards as $card): ?>
            <div>
                <span><?= e($card['label'] ?? '') ?></span>
                <strong><?= e($card['value'] ?? '') ?></strong>
                <?php if (($card['hint'] ?? '') !== ''): ?><small><?= e($card['hint']) ?></small><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="report-links">
    <?php foreach ($reportLinks as $link): ?>
        <a class="report-card <?= $type === $link['type'] ? 'is-active' : '' ?>" href="/reports?type=<?= e($link['type']) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">
            <span><?= e($link['label']) ?></span>
            <strong><?= e($link['title']) ?></strong>
            <p><?= $type === $link['type'] ? 'Vista actual seleccionada.' : 'Abrir este reporte en el mismo espacio.' ?></p>
            <span class="tag"><?= e($link['tag']) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<?php if ($type === 'inventory'): ?>
    <article class="card">
        <header class="section-head">
            <div>
                <h3>Graficas de inventario</h3>
                <p>Exporta rankings visuales de productos: mayor stock, menor stock, mayor movimiento y mayor valor.</p>
            </div>
            <div class="report-tools">
                <a
                    class="btn btn-secondary"
                    href="/reports/inventory-charts/pdf?chart=all&from=<?= e($from) ?>&to=<?= e($to) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >PDF completo</a>
            </div>
        </header>

        <form method="get" action="/reports/inventory-charts/pdf" class="form inline-form" target="_blank" data-no-submit-loading="1">
            <label>Grafica
                <select name="chart">
                    <option value="all">Todas las graficas</option>
                    <option value="highest_stock">Mayor stock</option>
                    <option value="lowest_stock">Menor stock</option>
                    <option value="highest_movement">Mayor movimiento</option>
                    <option value="highest_value">Mayor valor de inventario</option>
                </select>
            </label>
            <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn">Exportar graficas completas</button>
        </form>
    </article>
<?php endif; ?>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Centro de reportes</h3>
            <p>Todo se consulta desde este mismo espacio. Cambia tipo, fecha y exporta el resultado actual sin brincar entre vistas.</p>
        </div>
        <div class="report-tools">
            <a class="btn btn-secondary" href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener noreferrer">PDF</a>
            <?php if ($mode === 'treasury_accounts' && $canManageTreasury): ?>
                <button type="button" class="btn btn-outline" data-modal-open="treasury-reconcile-modal">Conciliar saldo real</button>
                <button type="button" class="btn btn-outline" data-modal-open="treasury-opening-balance-modal">Saldo inicial</button>
            <?php endif; ?>
        </div>
    </header>

    <form method="get" action="/reports" class="form inline-form">
        <label>Tipo
            <select name="type">
                <?php foreach ($reportLinks as $link): ?>
                    <option value="<?= e($link['type']) ?>" <?= $type === $link['type'] ? 'selected' : '' ?>><?= e($link['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($type === 'inventory'): ?>
            <label>Desglosar
                <select name="inventory_view">
                    <?php foreach ($inventoryBreakdownOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $inventoryBreakdown === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn">Actualizar</button>
    </form>

    <div class="live-panel">
        <div><span>Moneda referencia</span><strong><?= e($baseCurrency) ?></strong></div>
        <div><span>Moneda consolidada</span><strong><?= e($reportingCurrency) ?></strong></div>
        <div><span>Tasa actual</span><strong>1 <?= e($baseCurrency) ?> = <?= money($currentRate) ?> <?= e($secondaryCurrency) ?></strong></div>
    </div>

    <?php if ($infoCards): ?>
        <div class="kpi-strip">
            <?php foreach ($infoCards as $card): ?>
                <div class="kpi-pill">
                    <div>
                        <span><?= e($card['label'] ?? '') ?></span>
                        <strong><?= e($card['value'] ?? '') ?></strong>
                        <?php if (($card['hint'] ?? '') !== ''): ?><small><?= e($card['hint']) ?></small><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>

<?php if ($mode === 'balance'): ?>
    <article class="card">
        <header class="section-head">
            <div>
                <h3>Lectura rapida</h3>
                <p><?= e($balanceDisclaimer ?? '') ?></p>
            </div>
        </header>

        <div class="kpi-strip">
            <div class="kpi-pill">
                <div>
                    <span>Dinero disponible</span>
                    <strong><?= money($balanceOverview['cash_available'] ?? 0) ?> <?= e($reportingCurrency) ?></strong>
                    <small><?= $toReference((float) ($balanceOverview['cash_available'] ?? 0)) ?></small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Vendiste</span>
                    <strong><?= money($balanceOverview['sales'] ?? 0) ?> <?= e($reportingCurrency) ?></strong>
                    <small><?= $toReference((float) ($balanceOverview['sales'] ?? 0)) ?></small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Gastaste</span>
                    <strong><?= money($balanceOverview['outflows'] ?? 0) ?> <?= e($reportingCurrency) ?></strong>
                    <small>Compras + gastos</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Resultado estimado</span>
                    <strong><?= money($balanceOverview['estimated_result'] ?? 0) ?> <?= e($reportingCurrency) ?></strong>
                    <small><?= $toReference((float) ($balanceOverview['estimated_result'] ?? 0)) ?></small>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Monto <?= e($reportingCurrency) ?></th>
                        <th>Lectura</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="Concepto">Ventas del periodo</td>
                        <td data-label="Monto"><?= money($balanceOverview['sales'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Lo que facturaste o entregaste como venta.</td>
                    </tr>
                    <tr>
                        <td data-label="Concepto">Compras</td>
                        <td data-label="Monto"><?= money($balanceOverview['purchases'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Mercancia, materiales o insumos comprados.</td>
                    </tr>
                    <tr>
                        <td data-label="Concepto">Gastos</td>
                        <td data-label="Monto"><?= money($balanceOverview['expenses'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Egresos operativos registrados.</td>
                    </tr>
                    <tr>
                        <td data-label="Concepto">Por cobrar</td>
                        <td data-label="Monto"><?= money($balanceOverview['receivables'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Dinero vendido que aun no ha entrado.</td>
                    </tr>
                    <tr>
                        <td data-label="Concepto">Por pagar</td>
                        <td data-label="Monto"><?= money($balanceOverview['payables'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Compromisos pendientes con proveedores.</td>
                    </tr>
                    <tr>
                        <td data-label="Concepto">Inventario valorizado</td>
                        <td data-label="Monto"><?= money($balanceOverview['inventory_value'] ?? 0) ?> <?= e($reportingCurrency) ?></td>
                        <td data-label="Lectura">Valor actual registrado en existencias.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>

    <section class="balance-grid">
        <?php foreach (['assets', 'liabilities', 'equity'] as $section): ?>
                            <article class="balance-card">
                <h3><?= e($balanceLabels[$section] ?? ucfirst($section)) ?></h3>
                <?php if (!empty($balance[$section])): ?>
                    <?php foreach ($balance[$section] as $row): ?>
                        <div class="balance-row">
                            <div>
                                <span><?= e($row['name']) ?></span>
                                <small><?= $toReference((float) ($row['amount'] ?? 0)) ?></small>
                            </div>
                            <strong><?= money($row['amount'] ?? 0) ?> <?= e($reportingCurrency) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">Sin datos en esta seccion.</div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($mode === 'inventory' && $inventoryBreakdown === 'category'): ?>
    <article class="card">
        <header class="section-head">
            <div>
                <h3>Vista global por categorias</h3>
                <p>Cada bloque resume una categoria completa y luego lista sus productos por SKU. Al final veras un recuento general de todo el inventario.</p>
            </div>
        </header>

        <div class="kpi-strip">
            <div class="kpi-pill">
                <div>
                    <span>Productos totales</span>
                    <strong><?= (int) ($inventoryOverview['product_count'] ?? count($records)) ?></strong>
                    <small>Items visibles en este corte.</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Categorias</span>
                    <strong><?= (int) ($inventoryOverview['category_count'] ?? count($inventoryGroups)) ?></strong>
                    <small>Bloques resumidos dentro del reporte.</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Criticos</span>
                    <strong><?= (int) ($inventoryOverview['critical_count'] ?? 0) ?></strong>
                    <small>Productos en o por debajo del minimo.</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Totales por moneda</span>
                    <strong><?= e((string) ($inventoryOverview['totals_text'] ?? 'Sin monto registrado')) ?></strong>
                    <small>Se mantiene separado por moneda original.</small>
                </div>
            </div>
        </div>
    </article>

    <?php foreach ($inventoryGroups as $group): ?>
        <article class="card inventory-category-card">
            <header class="section-head inventory-report-section-head">
                <div class="section-head-copy">
                    <h3><?= e($group['category_name'] ?? 'Sin categoria') ?></h3>
                    <p><?= (int) ($group['product_count'] ?? 0) ?> productos, <?= money((float) ($group['units'] ?? 0)) ?> unidades acumuladas y <?= (int) ($group['critical_count'] ?? 0) ?> criticos.</p>
                </div>
            </header>

            <div class="kpi-strip inventory-category-strip">
                <div class="kpi-pill">
                    <div><span>Productos</span><strong><?= (int) ($group['product_count'] ?? 0) ?></strong></div>
                </div>
                <div class="kpi-pill">
                    <div><span>Existencia</span><strong><?= money((float) ($group['units'] ?? 0)) ?></strong></div>
                </div>
                <div class="kpi-pill">
                    <div><span>Criticos</span><strong><?= (int) ($group['critical_count'] ?? 0) ?></strong></div>
                </div>
                <div class="kpi-pill">
                    <div><span>Total categoria</span><strong><?= e((string) ($group['totals_text'] ?? 'Sin monto registrado')) ?></strong></div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table mobile-cards">
                    <thead>
                        <tr>
                            <th>SKU</th><th>Producto</th><th>Existencia</th><th>Minimo</th><th>Moneda</th><th>Costo</th><th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($group['records'] ?? []) as $row): ?>
                            <tr>
                                <td data-label="SKU"><?= e($row['sku'] ?? '') ?></td>
                                <td data-label="Producto">
                                    <div class="money-stack">
                                        <strong><?= e($row['name'] ?? '') ?></strong>
                                        <small><?= !empty($row['is_critical']) ? 'Stock critico' : 'Stock estable' ?></small>
                                    </div>
                                </td>
                                <td data-label="Existencia"><span class="badge <?= !empty($row['is_critical']) ? 'badge-danger' : 'badge-ok' ?>"><?= money((float) ($row['stock'] ?? 0)) ?> <?= e($row['unit_label'] ?? 'und') ?></span></td>
                                <td data-label="Minimo"><?= money((float) ($row['stock_min'] ?? 0)) ?> <?= e($row['unit_label'] ?? 'und') ?></td>
                                <td data-label="Moneda"><?= e($row['currency_code'] ?? '') ?></td>
                                <td data-label="Costo"><?= money((float) ($row['cost'] ?? 0)) ?></td>
                                <td data-label="Total"><?= money((float) ($row['inventory_total'] ?? 0)) ?> <?= e($row['currency_code'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endforeach; ?>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Recuento general</h3>
                <p>Resumen final de todo el inventario visible en este reporte, categoria por categoria.</p>
            </div>
        </header>

        <div class="table-wrap">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <th>Categoria</th><th>Productos</th><th>Existencia</th><th>Criticos</th><th>Total registrado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryRecap as $row): ?>
                        <tr>
                            <td data-label="Categoria"><?= e($row['category_name'] ?? 'Sin categoria') ?></td>
                            <td data-label="Productos"><span class="badge badge-neutral"><?= (int) ($row['product_count'] ?? 0) ?></span></td>
                            <td data-label="Existencia"><?= money((float) ($row['units'] ?? 0)) ?></td>
                            <td data-label="Criticos"><span class="badge <?= (int) ($row['critical_count'] ?? 0) > 0 ? 'badge-danger' : 'badge-ok' ?>"><?= (int) ($row['critical_count'] ?? 0) ?></span></td>
                            <td data-label="Total registrado"><?= e($row['totals_text'] ?? 'Sin monto registrado') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inventoryRecap): ?>
                        <tr><td colspan="5" class="empty-state">No hay categorias para resumir en este rango.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
<?php else: ?>
    <article class="card">
        <header class="section-head">
            <div>
                <h3>Resultado</h3>
                <p>
                    <?= $mode === 'purchase_lines'
                        ? 'Cada renglon refleja la compra real por producto, con costo en USD y equivalente en bolivares usando la tasa de cierre del documento.'
                        : ($mode === 'open_items'
                            ? 'Cada fila representa un documento con saldo abierto, su vencimiento y el atraso acumulado para gestionar cobranza o pagos.'
                        : ($mode === 'treasury_accounts'
                            ? 'Cada cuenta conserva su saldo original por moneda y a la vez muestra su equivalente para que tesoreria no pierda contexto multimoneda.'
                        : ($mode === 'journal_entries'
                            ? 'Cada documento genera sus renglones contables base, con cuenta y tercero asociado.'
                            : ($mode === 'ledger_accounts'
                                ? 'El mayor resume saldos por cuenta a partir de los renglones integrados del diario.'
                                : 'Los montos en base muestran abajo una referencia rapida en ' . e($secondaryCurrency) . ' con la tasa vigente.')))) ?>
                </p>
            </div>
        </header>

        <div class="table-wrap">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <?php if ($mode === 'purchase_lines'): ?>
                            <th>Fecha</th><th>Documento</th><th>Proveedor</th><th>Producto</th><th>Cantidad</th><th>Moneda doc.</th><th>Tasa cierre</th><th>Costo USD</th><th>Total USD</th><th>Equiv. VES</th>
                        <?php elseif ($mode === 'documents'): ?>
                            <th>Fecha</th><th>Referencia</th><th>Tercero</th><th>Moneda</th><th>Tasa</th><th>Monto doc.</th><th>Consolidado <?= e($reportingCurrency) ?></th>
                        <?php elseif ($mode === 'open_items'): ?>
                            <th>Fecha</th><th>Vence</th><th>Referencia</th><th>Tercero</th><th>Moneda</th><th>Total doc.</th><th>Abonado</th><th>Saldo</th><th>Dias atraso</th><th>Estado</th>
                        <?php elseif ($mode === 'inventory'): ?>
                            <th>SKU</th><th>Producto</th><th>Categoria</th><th>Existencia</th><th>Minimo</th><th>Moneda</th><th>Costo</th><th>Total</th>
                        <?php elseif ($mode === 'movements'): ?>
                            <th>Fecha</th><th>Producto</th><th>Almacen</th><th>Tipo</th><th>Cantidad</th><th>Referencia</th>
                        <?php elseif ($mode === 'treasury_accounts'): ?>
                            <th>Cuenta</th><th>Metodo</th><th>Moneda</th><th>Saldo inicial</th><th>Saldo antes</th><th>Entradas</th><th>Salidas</th><th>Saldo corte</th><th>Equiv. <?= e($secondaryCurrency) ?></th><th>Equiv. <?= e($baseCurrency) ?></th><th>Acciones</th>
                        <?php elseif ($mode === 'journal_entries'): ?>
                            <th>Fecha</th><th>Origen</th><th>Referencia</th><th>Cuenta</th><th>Tercero</th><th>Moneda</th><th>Monto doc.</th><th>Debe <?= e($reportingCurrency) ?></th><th>Haber <?= e($reportingCurrency) ?></th>
                        <?php else: ?>
                            <th>Codigo</th><th>Cuenta</th><th>Movimientos</th><th>Ult. mov.</th><th>Debe <?= e($reportingCurrency) ?></th><th>Haber <?= e($reportingCurrency) ?></th><th>Saldo <?= e($reportingCurrency) ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($records): ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <?php if ($mode === 'purchase_lines'): ?>
                                    <td data-label="Fecha"><?= e($row['date']) ?></td>
                                    <td data-label="Documento"><?= e($row['reference']) ?></td>
                                    <td data-label="Proveedor"><?= e($row['party']) ?></td>
                                    <td data-label="Producto"><?= e($row['product_name']) ?></td>
                                    <td data-label="Cantidad"><span class="badge badge-ok"><?= money($row['quantity']) ?></span></td>
                                    <td data-label="Moneda doc."><?= e($row['currency_code']) ?></td>
                                    <td data-label="Tasa cierre"><?= money($row['exchange_rate']) ?></td>
                                    <td data-label="Costo USD"><?= money($row['cost_usd']) ?> USD</td>
                                    <td data-label="Total USD"><div class="money-stack"><strong><?= money($row['line_usd']) ?> USD</strong><small>Proveedor: <?= e($row['party']) ?></small></div></td>
                                    <td data-label="Equiv. VES"><div class="money-stack"><strong><?= money($row['line_ves']) ?> VES</strong><small>Fijo por tasa de cierre</small></div></td>
                                <?php elseif ($mode === 'documents'): ?>
                                    <td data-label="Fecha"><?= e($row['date']) ?></td>
                                    <td data-label="Referencia"><?= e($row['reference']) ?></td>
                                    <td data-label="Tercero"><?= e($row['party']) ?></td>
                                    <td data-label="Moneda"><?= e($row['currency_code']) ?></td>
                                    <td data-label="Tasa"><?= money($row['exchange_rate']) ?></td>
                                    <td data-label="Monto doc."><?= money($row['original_amount']) ?> <?= e($row['currency_code']) ?></td>
                                    <td data-label="Consolidado"><div class="money-stack"><strong><?= money($row['base_amount']) ?> <?= e($reportingCurrency) ?></strong><small><?= $toReference((float) $row['base_amount']) ?></small></div></td>
                                <?php elseif ($mode === 'open_items'): ?>
                                    <td data-label="Fecha"><?= e($row['invoice_date'] ?? $row['note_date'] ?? $row['purchase_date'] ?? $row['document_date'] ?? '') ?></td>
                                    <td data-label="Vence"><?= e($row['due_date'] ?? '') ?></td>
                                    <td data-label="Referencia"><?= e($row['invoice_number'] ?? $row['doc_number'] ?? '') ?></td>
                                    <td data-label="Tercero"><?= e($row['client_name'] ?? $row['supplier_name'] ?? '') ?></td>
                                    <td data-label="Moneda"><?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Total doc."><?= money($row['total_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Abonado"><?= money($row['amount_paid_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Saldo">
                                        <div class="money-stack">
                                            <strong><?= money($row['balance_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong>
                                            <small><?= money($row['balance_converted'] ?? 0) ?> <?= e($reportingCurrency) ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Dias atraso"><span class="badge <?= (int) ($row['days_overdue'] ?? 0) > 0 ? 'badge-danger' : 'badge-neutral' ?>"><?= (int) ($row['days_overdue'] ?? 0) ?></span></td>
                                    <td data-label="Estado"><?= e($paymentStatusLabel((string) ($row['payment_status_effective'] ?? 'pending'))) ?></td>
                                <?php elseif ($mode === 'inventory'): ?>
                                    <td data-label="SKU"><?= e($row['sku'] ?? '') ?></td>
                                    <td data-label="Producto"><?= e($row['name']) ?></td>
                                    <td data-label="Categoria"><?= e($row['category_name']) ?></td>
                                    <td data-label="Existencia"><span class="badge <?= !empty($row['is_critical']) ? 'badge-danger' : 'badge-ok' ?>"><?= money((float) ($row['stock'] ?? 0)) ?> <?= e($row['unit_label'] ?? 'und') ?></span></td>
                                    <td data-label="Minimo"><?= money((float) ($row['stock_min'] ?? 0)) ?> <?= e($row['unit_label'] ?? 'und') ?></td>
                                    <td data-label="Moneda"><?= e($row['currency_code']) ?></td>
                                    <td data-label="Costo"><?= money((float) ($row['cost'] ?? 0)) ?></td>
                                    <td data-label="Total"><?= money((float) ($row['inventory_total'] ?? 0)) ?> <?= e($row['currency_code']) ?></td>
                                <?php elseif ($mode === 'movements'): ?>
                                    <td data-label="Fecha"><?= e($row['created_at']) ?></td>
                                    <td data-label="Producto"><?= e($row['product_label'] ?? product_display_name($row['product_name'] ?? '', $row['category_name'] ?? '')) ?></td>
                                    <td data-label="Almacen"><?= e($row['warehouse_name']) ?></td>
                                    <td data-label="Tipo"><?= e($row['movement_type']) ?></td>
                                    <td data-label="Cantidad"><span class="badge <?= (float) $row['quantity'] < 0 ? 'badge-danger' : 'badge-ok' ?>"><?= money($row['quantity']) ?></span></td>
                                    <td data-label="Referencia"><?= e($row['reference']) ?></td>
                                <?php elseif ($mode === 'treasury_accounts'): ?>
                                    <td data-label="Cuenta">
                                        <div class="money-stack">
                                            <strong><?= e($row['account_name'] ?? '') ?></strong>
                                            <small>Codigo <?= e($row['account_code'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Metodo"><?= e(payment_method_label((string) ($row['method_type'] ?? ''))) ?></td>
                                    <td data-label="Moneda"><?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Saldo inicial"><?= money($row['opening_balance'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Saldo antes"><?= money($row['previous_balance_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Entradas"><?= money($row['period_in_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Salidas"><?= money($row['period_out_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></td>
                                    <td data-label="Saldo corte">
                                        <div class="money-stack">
                                            <strong><?= money($row['balance_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong>
                                            <small>Neto periodo: <?= money($row['period_net_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Equiv. <?= e($secondaryCurrency) ?>"><?= money($row['balance_reporting'] ?? 0) ?> <?= e($secondaryCurrency) ?></td>
                                    <td data-label="Equiv. <?= e($baseCurrency) ?>"><?= money($row['balance_reference'] ?? 0) ?> <?= e($baseCurrency) ?></td>
                                    <td data-label="Acciones" class="actions-row">
                                        <button type="button" class="btn btn-sm btn-outline" data-modal-open="treasury-audit-<?= (int) ($row['id'] ?? 0) ?>">Auditar</button>
                                        <?php if ($canManageTreasury): ?>
                                            <button type="button" class="btn btn-sm btn-outline" data-modal-open="treasury-reconcile-modal">Conciliar</button>
                                        <?php endif; ?>
                                    </td>
                                <?php elseif ($mode === 'journal_entries'): ?>
                                    <td data-label="Fecha"><?= e($row['trans_date']) ?></td>
                                    <td data-label="Origen"><?= e($row['source']) ?></td>
                                    <td data-label="Referencia"><?= e($row['reference']) ?></td>
                                    <td data-label="Cuenta">
                                        <div class="money-stack">
                                            <strong><?= e($row['account_name'] ?? '') ?></strong>
                                            <small>Codigo <?= e($row['account_code'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Tercero"><?= e($row['counterparty'] ?? 'Sin tercero') ?></td>
                                    <td data-label="Moneda"><?= e($row['currency_code']) ?></td>
                                    <td data-label="Monto doc."><?= money($row['original_amount']) ?> <?= e($row['currency_code']) ?></td>
                                    <td data-label="Debe"><div class="money-stack"><strong><?= money($row['debit']) ?> <?= e($reportingCurrency) ?></strong><small><?= $toReference((float) $row['debit']) ?></small></div></td>
                                    <td data-label="Haber"><div class="money-stack"><strong><?= money($row['credit']) ?> <?= e($reportingCurrency) ?></strong><small><?= $toReference((float) $row['credit']) ?></small></div></td>
                                <?php else: ?>
                                    <td data-label="Codigo"><?= e($row['account_code'] ?? '') ?></td>
                                    <td data-label="Cuenta">
                                        <div class="money-stack">
                                            <strong><?= e($row['account_name'] ?? '') ?></strong>
                                            <small><?= (int) ($row['entry_count'] ?? 0) ?> movimientos</small>
                                        </div>
                                    </td>
                                    <td data-label="Movimientos"><span class="badge badge-neutral"><?= (int) ($row['entry_count'] ?? 0) ?></span></td>
                                    <td data-label="Ult. mov."><?= e($row['last_date'] ?? '') ?></td>
                                    <td data-label="Debe"><?= money($row['debit']) ?> <?= e($reportingCurrency) ?></td>
                                    <td data-label="Haber"><?= money($row['credit']) ?> <?= e($reportingCurrency) ?></td>
                                    <td data-label="Saldo"><div class="money-stack"><strong class="<?= (float) $row['balance'] < 0 ? 'ledger-negative' : 'ledger-positive' ?>"><?= money($row['balance']) ?> <?= e($reportingCurrency) ?></strong><small><?= $toReference((float) $row['balance']) ?></small></div></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $mode === 'purchase_lines' ? 10 : ($mode === 'documents' ? 7 : ($mode === 'open_items' ? 10 : ($mode === 'inventory' ? 8 : ($mode === 'movements' ? 6 : ($mode === 'treasury_accounts' ? 11 : ($mode === 'journal_entries' ? 9 : 7))))) ) ?>" class="empty-state">No hay datos para el rango seleccionado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
<?php endif; ?>

<?php if ($type === 'sales' && $productSummary): ?>
    <article class="card">
        <header class="section-head">
            <div>
                <h3>Cantidades vendidas por producto</h3>
                <p>Resumen de unidades facturadas en el periodo. Aqui podras ver rapido cuantas franelas u otros productos salieron por facturacion.</p>
            </div>
        </header>

        <div class="kpi-strip">
            <div class="kpi-pill">
                <div>
                    <span>Unidades vendidas</span>
                    <strong><?= money((float) ($productSummaryOverview['total_quantity'] ?? 0)) ?></strong>
                    <small>Suma total facturada en el rango.</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Productos vendidos</span>
                    <strong><?= (int) ($productSummaryOverview['product_count'] ?? count($productSummary)) ?></strong>
                    <small>Productos distintos con salida.</small>
                </div>
            </div>
            <div class="kpi-pill">
                <div>
                    <span>Producto lider</span>
                    <strong><?= e((string) ($productSummaryOverview['lead_product'] ?? 'Sin datos')) ?></strong>
                    <small><?= money((float) ($productSummaryOverview['lead_quantity'] ?? 0)) ?> <?= e((string) ($productSummaryOverview['lead_unit_label'] ?? 'und')) ?></small>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <th>SKU</th><th>Producto</th><th>Unidad</th><th>Cantidad vendida</th><th>Facturas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productSummary as $row): ?>
                        <tr>
                            <td data-label="SKU"><?= e($row['sku'] !== '' ? $row['sku'] : 'Sin SKU') ?></td>
                            <td data-label="Producto"><?= e($row['product_label'] ?? '') ?></td>
                            <td data-label="Unidad"><?= e($row['unit_label'] ?? 'und') ?></td>
                            <td data-label="Cantidad vendida"><span class="badge badge-ok"><?= money((float) ($row['quantity'] ?? 0)) ?></span></td>
                            <td data-label="Facturas"><span class="badge badge-neutral"><?= (int) ($row['document_count'] ?? 0) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
<?php endif; ?>

<?php if ($mode === 'treasury_accounts'): ?>
    <?php foreach ($records as $row): ?>
        <?php
            $accountId = (int) ($row['id'] ?? 0);
            $accountMovements = $treasuryMovements[$accountId] ?? [];
        ?>
        <div class="modal-shell" data-modal="treasury-audit-<?= $accountId ?>" aria-hidden="true">
            <div class="modal-backdrop" data-modal-close></div>
            <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="treasury-audit-title-<?= $accountId ?>">
                <header class="modal-header">
                    <div>
                        <span class="eyebrow">Auditoria de tesoreria</span>
                        <h3 id="treasury-audit-title-<?= $accountId ?>"><?= e($row['account_name'] ?? '') ?></h3>
                    </div>
                    <button type="button" class="modal-close" data-modal-close>&times;</button>
                </header>

                <div class="live-panel">
                    <div><span>Saldo inicial</span><strong><?= money($row['opening_balance'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong></div>
                    <div><span>Saldo antes del rango</span><strong><?= money($row['previous_balance_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong></div>
                    <div><span>Entradas del rango</span><strong><?= money($row['period_in_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong></div>
                    <div><span>Salidas del rango</span><strong><?= money($row['period_out_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong></div>
                    <div><span>Saldo al corte</span><strong><?= money($row['balance_original'] ?? 0) ?> <?= e($row['currency_code'] ?? '') ?></strong></div>
                </div>

                <p class="form-helper">El saldo al corte sale de saldo inicial + movimientos anteriores + entradas del rango - salidas del rango. Si el banco real no coincide, registra una conciliacion para dejar el ajuste auditado.</p>

                <div class="table-wrap">
                    <table class="table mobile-cards">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Origen</th>
                                <th>Referencia</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                                <th>Notas</th>
                                <?php if ($canManageTreasury): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($accountMovements): ?>
                                <?php foreach ($accountMovements as $movement): ?>
                                    <?php
                                        $isReversed = (int) ($movement['is_reversed'] ?? 0) === 1;
                                        $isIn = ($movement['direction'] ?? '') === 'in';
                                    ?>
                                    <tr>
                                        <td data-label="Fecha"><?= e($movement['movement_date'] ?? '') ?></td>
                                        <td data-label="Origen"><?= e($treasurySourceLabel($movement['source_type'] ?? '')) ?></td>
                                        <td data-label="Referencia"><?= e($movement['reference'] ?? '') ?></td>
                                        <td data-label="Entrada"><?= $isIn ? money($movement['amount_original'] ?? 0) . ' ' . e($row['currency_code'] ?? '') : '-' ?></td>
                                        <td data-label="Salida"><?= ! $isIn ? money($movement['amount_original'] ?? 0) . ' ' . e($row['currency_code'] ?? '') : '-' ?></td>
                                        <td data-label="Estado"><span class="badge <?= $isReversed ? 'badge-danger' : 'badge-ok' ?>"><?= $isReversed ? 'Reversado' : 'Activo' ?></span></td>
                                        <td data-label="Notas"><?= e($movement['notes'] ?? '') ?></td>
                                        <?php if ($canManageTreasury): ?>
                                            <td data-label="Acciones" class="actions-row">
                                                <?php if (($movement['source_type'] ?? '') === 'treasury_adjustment' && ! $isReversed): ?>
                                                    <form method="post" action="/reports/treasury/adjustments/<?= (int) ($movement['id'] ?? 0) ?>/reverse" onsubmit="return confirm('Se reversara este ajuste manual.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="from" value="<?= e($from) ?>">
                                                        <input type="hidden" name="to" value="<?= e($to) ?>">
                                                        <input type="hidden" name="reason" value="Reversion manual desde auditoria de tesoreria">
                                                        <button class="btn btn-sm btn-danger-soft">Reversar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= $canManageTreasury ? 8 : 7 ?>" class="empty-state">No hay movimientos para esta cuenta en el rango seleccionado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($mode === 'treasury_accounts' && $canManageTreasury): ?>
    <div class="modal-shell" data-modal="treasury-reconcile-modal" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="treasury-reconcile-title">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Conciliacion</span>
                    <h3 id="treasury-reconcile-title">Ajustar a saldo real</h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>
            <form method="post" action="/reports/treasury/adjustments" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="from" value="<?= e($from) ?>">
                <input type="hidden" name="to" value="<?= e($to) ?>">
                <label>Cuenta
                    <select name="cash_account_id" required>
                        <?php foreach ($records as $row): ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>">
                                <?= e(($row['account_name'] ?? '') . ' - ' . ($row['currency_code'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha del ajuste
                    <input type="date" name="movement_date" value="<?= e($to) ?>" required>
                </label>
                <label>Saldo real
                    <input type="number" step="0.01" name="real_balance" required>
                </label>
                <label>Soporte o nota
                    <textarea name="notes" placeholder="Ej: saldo segun banco, cierre de caja, referencia del corte"></textarea>
                </label>
                <p class="form-helper">Se creara una entrada o salida por la diferencia entre el saldo del sistema y el saldo real indicado. No se borran movimientos anteriores.</p>
                <button class="btn">Registrar conciliacion</button>
            </form>
        </div>
    </div>

    <div class="modal-shell" data-modal="treasury-opening-balance-modal" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="treasury-opening-balance-title">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Tesoreria</span>
                    <h3 id="treasury-opening-balance-title">Configurar saldo inicial</h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>
            <form method="post" action="/reports/treasury/opening-balances" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="from" value="<?= e($from) ?>">
                <input type="hidden" name="to" value="<?= e($to) ?>">
                <p class="form-helper">Este valor funciona como base de arranque del sistema para cada cuenta. No elimina cobros, pagos ni gastos ya registrados.</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cuenta</th>
                                <th>Metodo</th>
                                <th>Moneda</th>
                                <th>Saldo inicial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td>
                                        <div class="money-stack">
                                            <strong><?= e($row['account_name'] ?? '') ?></strong>
                                            <small>Codigo <?= e($row['account_code'] ?? '') ?></small>
                                        </div>
                                    </td>
                                    <td><?= e(payment_method_label((string) ($row['method_type'] ?? ''))) ?></td>
                                    <td><?= e($row['currency_code'] ?? '') ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            name="opening_balances[<?= (int) ($row['id'] ?? 0) ?>]"
                                            value="<?= e((string) ($row['opening_balance'] ?? 0)) ?>"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn">Guardar saldos iniciales</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
