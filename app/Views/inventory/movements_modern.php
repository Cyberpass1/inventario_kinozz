<?php
$productOptionsMarkup = (static function (array $products): string {
    ob_start();
    foreach ($products as $product): ?>
        <option
            value="<?= (int) ($product['id'] ?? 0) ?>"
            data-sku="<?= e((string) ($product['sku'] ?? '')) ?>"
            data-stock="<?= e((string) ($product['stock'] ?? 0)) ?>"
            data-price="<?= e((string) ($product['price'] ?? 0)) ?>"
            data-cost="<?= e((string) ($product['cost'] ?? 0)) ?>"
            data-currency="<?= e((string) ($product['currency_code'] ?? base_currency())) ?>"
            data-product-type="<?= e((string) ($product['product_type'] ?? 'merchandise')) ?>"
            data-unit-label="<?= e(product_unit_label($product)) ?>"
            data-type-label="<?= e(product_type_label((string) ($product['product_type'] ?? 'merchandise'))) ?>"
            data-track-stock="<?= product_tracks_inventory($product) ? '1' : '0' ?>"
        >
            <?= e(trim(((string) ($product['sku'] ?? '')) . ' ' . product_display_name($product['name'] ?? '', $product['category_name'] ?? ''))) ?>
        </option>
    <?php endforeach;

    return trim((string) ob_get_clean());
})($products);

// KPIs del periodo
$kpiTotal = count($movements);
$kpiIn = 0.0;
$kpiOut = 0.0;
$kpiNet = 0.0;
$movementTypes = [];
foreach ($movements as $m) {
    $q = (float) ($m['quantity'] ?? 0);
    if ($q > 0) {
        $kpiIn += $q;
    } else {
        $kpiOut += abs($q);
    }
    $kpiNet += $q;
    $type = (string) ($m['movement_type'] ?? '');
    if ($type !== '' && !in_array($type, $movementTypes, true)) {
        $movementTypes[] = $type;
    }
}
sort($movementTypes);

$movementBadge = static function (string $type, float $quantity): array {
    $isIn = $quantity > 0;
    $label = match ($type) {
        'purchase' => 'Compra',
        'sale' => 'Venta',
        'invoice' => 'Factura',
        'delivery_note' => 'Nota de entrega',
        'production' => 'Produccion',
        'production_in' => 'Produccion (ingreso)',
        'production_out' => 'Produccion (consumo)',
        'adjustment_in' => 'Ajuste +',
        'adjustment_out' => 'Ajuste -',
        'cancellation' => 'Anulacion',
        default => $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : 'Movimiento',
    };
    return [$isIn ? 'badge badge-ok' : 'badge badge-danger', $label];
};
?>

<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Movimientos de inventario</h3>
            <small>Trazabilidad completa: entradas, salidas y ajustes en el rango <?= e($from) ?> &rarr; <?= e($to) ?>.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-movements-quick-toggle>+ Ajuste rapido</button>
            <button type="button" class="btn btn-outline btn-sm" data-movements-bulk-toggle>Reajuste masivo</button>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi"><span>Movimientos</span><strong><?= $kpiTotal ?></strong></div>
        <div class="inventory-kpi inventory-kpi-in"><span>Entradas</span><strong>+<?= money($kpiIn) ?></strong></div>
        <div class="inventory-kpi inventory-kpi-out"><span>Salidas</span><strong>-<?= money($kpiOut) ?></strong></div>
        <div class="inventory-kpi"><span>Neto</span><strong><?= ($kpiNet >= 0 ? '+' : '') . money($kpiNet) ?></strong></div>
    </div>

    <!-- Panel inline: Ajuste rapido -->
    <article class="card card-feature inventory-create-panel" data-movements-quick-panel hidden>
        <header class="section-head">
            <div>
                <h3>Ajuste rapido</h3>
                <p>Busca el producto, indica cantidad (+/-) y aplica un ajuste individual.</p>
            </div>
            <button type="button" class="pos-custom-close" data-movements-quick-toggle aria-label="Cerrar">&times;</button>
        </header>
        <form method="post" action="/inventory/adjust" class="form two-cols" data-calc="movement">
            <?= csrf_field() ?>
            <div class="col-span-2 client-search-shell" data-product-picker>
                <label>Producto
                    <input
                        type="text"
                        value=""
                        placeholder="Escribe SKU o nombre para buscar"
                        autocomplete="off"
                        data-product-search
                    >
                </label>
                <div
                    class="client-search-selected"
                    data-product-selected
                    data-empty-name="Sin producto seleccionado"
                    data-empty-meta="Busca y elige un producto para aplicar el ajuste."
                    data-pending-label="Pendiente"
                    data-selected-label="Seleccionado"
                >
                    <div class="client-search-selected-top">
                        <span>Producto ajustado</span>
                        <div class="client-search-badge" data-product-selected-state>Pendiente</div>
                    </div>
                    <strong data-product-selected-name>Sin producto seleccionado</strong>
                    <small data-product-selected-meta>Busca y elige un producto para aplicar el ajuste.</small>
                    <button type="button" class="client-search-clear" data-product-clear hidden>Cambiar</button>
                </div>
                <div class="client-search-panel" data-product-panel hidden>
                    <small class="client-search-status" data-product-status>Escribe SKU o nombre para filtrar productos.</small>
                    <div class="client-search-results" data-product-results></div>
                </div>
                <select name="product_id" data-product-select required hidden>
                    <option value="" data-sku="" data-stock="0" data-price="0" data-cost="0" data-currency="<?= e(base_currency()) ?>" selected></option>
                    <?= $productOptionsMarkup ?>
                </select>
            </div>
            <label>
                <span data-adjust-quantity-label>Cantidad (+/-)</span>
                <input type="number" step="1" name="quantity" required data-qty-input data-adjust-quantity-input>
                <small data-adjust-quantity-hint>Para productos por unidad el ajuste trabaja con enteros. Si eliges materia prima, habilita decimales.</small>
            </label>
            <label>
                <span>Notas</span>
                <input name="notes" placeholder="Motivo del ajuste">
            </label>
            <div class="col-span-2 live-panel live-panel-compact">
                <div><span>Stock actual</span><strong data-selected-stock>0,00</strong></div>
                <div><span>Precio referencia</span><strong data-selected-price>0,00</strong></div>
            </div>
            <button class="btn col-span-2">Aplicar ajuste</button>
        </form>
    </article>

    <!-- Panel inline: Reajuste masivo -->
    <article class="card card-feature inventory-create-panel" data-movements-bulk-panel hidden>
        <header class="section-head">
            <div>
                <h3>Reajuste masivo por conteo</h3>
                <p>Escribe el stock contado final solo en los productos necesarios. Solo se procesan filas con diferencia real.</p>
            </div>
            <button type="button" class="pos-custom-close" data-movements-bulk-toggle aria-label="Cerrar">&times;</button>
        </header>
        <form method="post" action="/inventory/adjust-bulk" class="form" data-bulk-adjust-form>
            <?= csrf_field() ?>
            <div class="inventory-catalog-toolbar">
                <label class="inventory-filter inventory-filter-search">
                    <span>Buscar producto</span>
                    <input
                        type="search"
                        placeholder="SKU, nombre, categoria o unidad..."
                        autocomplete="off"
                        data-table-filter-input
                        data-table-filter-target="inventory-bulk-adjust"
                    >
                </label>
                <div class="inventory-filter-meta">
                    <strong data-table-filter-count data-table-filter-target="inventory-bulk-adjust" data-table-filter-label="productos"><?= count($products) ?></strong>
                    <small>productos</small>
                </div>
            </div>
            <label>Nota general
                <textarea name="notes" placeholder="Conteo general, motivo del reajuste, observacion comun para todas las filas editadas"></textarea>
            </label>
            <div class="live-panel live-panel-compact">
                <div><span>Filas con cambio</span><strong data-bulk-adjust-count>0</strong></div>
                <div><span>Entradas</span><strong data-bulk-adjust-in>0,00</strong></div>
                <div><span>Salidas</span><strong data-bulk-adjust-out>0,00</strong></div>
                <div><span>Neto</span><strong data-bulk-adjust-net>0,00</strong></div>
            </div>
            <div class="table-wrap table-wrap-scrollable table-wrap-mobile-slider" data-table-filter-container="inventory-bulk-adjust">
                <table class="table mobile-cards">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Unidad</th>
                            <th>Stock actual</th>
                            <th>Stock contado</th>
                            <th>Ajuste</th>
                        </tr>
                    </thead>
                    <tbody data-table-filter-rows="inventory-bulk-adjust">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $productId = (int) ($product['id'] ?? 0);
                            $productType = (string) ($product['product_type'] ?? 'merchandise');
                            $unitLabel = product_unit_label($product);
                            $step = $productType === 'raw_material' ? '0.01' : '1';
                            $filterSearch = strtolower(trim(
                                ((string) ($product['sku'] ?? '')) . ' '
                                . ((string) ($product['name'] ?? '')) . ' '
                                . ((string) ($product['category_name'] ?? '')) . ' '
                                . $unitLabel
                            ));
                            ?>
                            <tr
                                data-filter-search="<?= e($filterSearch) ?>"
                                data-bulk-adjust-row
                                data-stock="<?= e((string) ($product['stock'] ?? 0)) ?>"
                                data-step="<?= e($step) ?>"
                                data-unit-label="<?= e($unitLabel) ?>"
                            >
                                <td data-label="SKU"><?= e((string) ($product['sku'] ?? '')) !== '' ? e((string) ($product['sku'] ?? '')) : 'Sin SKU' ?></td>
                                <td data-label="Producto">
                                    <strong><?= e(product_display_name((string) ($product['name'] ?? ''), (string) ($product['category_name'] ?? ''))) ?></strong>
                                </td>
                                <td data-label="Tipo"><span class="badge badge-neutral"><?= e(product_type_label($productType)) ?></span></td>
                                <td data-label="Unidad"><?= e($unitLabel) ?></td>
                                <td data-label="Stock actual">
                                    <strong><?= money((float) ($product['stock'] ?? 0)) ?></strong>
                                </td>
                                <td data-label="Stock contado">
                                    <input
                                        type="number"
                                        step="<?= e($step) ?>"
                                        name="target_stock[<?= $productId ?>]"
                                        value=""
                                        placeholder="<?= money((float) ($product['stock'] ?? 0)) ?>"
                                        data-bulk-target-input
                                    >
                                </td>
                                <td data-label="Ajuste">
                                    <span class="badge badge-neutral" data-bulk-difference>0,00</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="empty-state" data-table-filter-empty="inventory-bulk-adjust" hidden>No hay productos que coincidan con la busqueda.</div>
            </div>
            <small class="pos-meta-hint">Deja vacias las filas que no quieras tocar. Solo se procesan las que tengan stock contado escrito con diferencia real contra el stock actual.</small>
            <button class="btn">Aplicar reajuste masivo</button>
        </form>
    </article>
</section>

<!-- Historial protagonista -->
<article class="card inventory-catalog-card">
    <div class="inventory-catalog-toolbar" data-movements-history-filters>
        <form method="get" action="/inventory/movements" class="inventory-history-date-form" data-movements-date-form>
            <label class="inventory-filter">
                <span>Desde</span>
                <input type="date" name="from" value="<?= e($from) ?>">
            </label>
            <label class="inventory-filter">
                <span>Hasta</span>
                <input type="date" name="to" value="<?= e($to) ?>">
            </label>
            <div class="inventory-history-date-actions">
                <button class="btn btn-outline btn-sm">Aplicar</button>
            </div>
        </form>
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Producto, referencia, notas..."
                autocomplete="off"
                data-movements-filter-text
            >
        </label>
        <label class="inventory-filter">
            <span>Tipo</span>
            <select data-movements-filter-type>
                <option value="">Todos</option>
                <?php foreach ($movementTypes as $type): ?>
                    <?php [$badgeClass, $label] = $movementBadge($type, 1.0); ?>
                    <option value="<?= e($type) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="inventory-filter">
            <span>Direccion</span>
            <select data-movements-filter-direction>
                <option value="">Todas</option>
                <option value="in">Entradas</option>
                <option value="out">Salidas</option>
            </select>
        </label>
        <div class="inventory-filter-meta">
            <strong data-movements-filter-count><?= count($movements) ?></strong>
            <small>de <?= count($movements) ?></small>
        </div>
    </div>
    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Referencia</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody data-movements-history-rows data-table-pagination data-table-pagination-size="20">
                <?php if ($movements): ?>
                    <?php foreach ($movements as $row): ?>
                        <?php
                            $type = (string) ($row['movement_type'] ?? '');
                            $qty = (float) ($row['quantity'] ?? 0);
                            [$badgeClass, $label] = $movementBadge($type, $qty);
                            $direction = $qty >= 0 ? 'in' : 'out';
                            $haystack = strtolower(trim(
                                ((string) ($row['product_name'] ?? '')) . ' '
                                . ((string) ($row['category_name'] ?? '')) . ' '
                                . $type . ' '
                                . ((string) ($row['reference'] ?? '')) . ' '
                                . ((string) ($row['notes'] ?? ''))
                            ));
                        ?>
                        <tr
                            data-filter-search="<?= e($haystack) ?>"
                            data-row-type="<?= e($type) ?>"
                            data-row-direction="<?= e($direction) ?>"
                        >
                            <td data-label="Fecha"><?= e((string) ($row['created_at'] ?? '')) ?></td>
                            <td data-label="Producto"><?= e(product_display_name($row['product_name'] ?? '', $row['category_name'] ?? '')) ?></td>
                            <td data-label="Tipo"><span class="<?= e($badgeClass) ?>"><?= e($label) ?></span></td>
                            <td data-label="Cantidad">
                                <span class="badge <?= $qty < 0 ? 'badge-danger' : 'badge-ok' ?>">
                                    <?= ($qty >= 0 ? '+' : '') . money($qty) ?>
                                </span>
                            </td>
                            <td data-label="Referencia"><?= e((string) ($row['reference'] ?? '')) ?></td>
                            <td data-label="Notas"><?= e((string) ($row['notes'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty-state">No hay movimientos en el rango seleccionado.</td></tr>
                <?php endif; ?>
                <tr class="table-empty-state" data-movements-history-empty hidden>
                    <td colspan="6">No hay movimientos que coincidan con los filtros.</td>
                </tr>
            </tbody>
        </table>
    </div>
</article>

<script>
(function () {
    "use strict";

    // Toggle de paneles inline (Ajuste rapido y Reajuste masivo).
    // Solo uno puede estar abierto a la vez para no saturar la pantalla.
    const togglePanel = (panel, opener) => {
        if (!panel) return;
        const willOpen = panel.hidden;

        // Cierra el otro panel si esta abierto
        document.querySelectorAll("[data-movements-quick-panel], [data-movements-bulk-panel]").forEach((other) => {
            if (other !== panel) other.hidden = true;
        });

        panel.hidden = !willOpen;
        if (willOpen) {
            panel.scrollIntoView({ behavior: "smooth", block: "start" });
            if (opener === "quick") {
                window.setTimeout(() => {
                    const search = panel.querySelector("[data-product-search]");
                    search?.focus();
                }, 80);
            }
        }
    };

    const quickPanel = document.querySelector("[data-movements-quick-panel]");
    const bulkPanel = document.querySelector("[data-movements-bulk-panel]");

    document.querySelectorAll("[data-movements-quick-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => togglePanel(quickPanel, "quick"));
    });
    document.querySelectorAll("[data-movements-bulk-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => togglePanel(bulkPanel, "bulk"));
    });

    // Filtros del historial (texto + tipo + direccion). Las fechas se aplican al recargar la pagina.
    const textInput = document.querySelector("[data-movements-filter-text]");
    const typeSelect = document.querySelector("[data-movements-filter-type]");
    const directionSelect = document.querySelector("[data-movements-filter-direction]");
    const rows = Array.from(document.querySelectorAll("[data-movements-history-rows] tr[data-filter-search]"));
    const emptyState = document.querySelector("[data-movements-history-empty]");
    const countNode = document.querySelector("[data-movements-filter-count]");

    if (rows.length === 0) return;

    const sync = () => {
        const term = String(textInput?.value || "").trim().toLowerCase();
        const type = String(typeSelect?.value || "").trim();
        const dir = String(directionSelect?.value || "").trim();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = String(row.dataset.filterSearch || "").toLowerCase();
            const matchText = term === "" || haystack.includes(term);
            const matchType = type === "" || String(row.dataset.rowType || "") === type;
            const matchDir = dir === "" || String(row.dataset.rowDirection || "") === dir;
            const matches = matchText && matchType && matchDir;
            row.classList.toggle("is-filter-hidden", !matches);
            if (matches) visible += 1;
        });

        if (emptyState) emptyState.hidden = visible !== 0;
        if (countNode) countNode.textContent = String(visible);
    };

    [textInput, typeSelect, directionSelect].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", sync);
        el.addEventListener("change", sync);
    });
    sync();
})();
</script>
