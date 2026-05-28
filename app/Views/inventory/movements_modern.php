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
?>
<section class="page-header">
    <div>
        <span class="eyebrow">Movimientos</span>
        <h2>Ajustes y trazabilidad</h2>
    </div>
</section>

<section class="workspace-grid">
    <article class="card">
        <header class="section-head"><h3>Filtrar historial</h3></header>
        <form method="get" action="/inventory/movements" class="form inline-form">
            <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn">Consultar</button>
        </form>
    </article>

    <article class="card card-feature">
        <header class="section-head"><h3>Ajuste rapido</h3></header>
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
                <small data-adjust-quantity-hint>Para productos por unidad el ajuste trabaja con enteros. Si eliges una materia prima, habilitara decimales.</small>
            </label>
            <label class="col-span-2">Notas<textarea name="notes"></textarea></label>
            <div class="col-span-2 live-panel">
                <div><span>Stock actual</span><strong data-selected-stock>0,00</strong></div>
                <div><span>Precio referencia</span><strong data-selected-price>0,00</strong></div>
            </div>
            <button class="btn col-span-2">Aplicar ajuste</button>
        </form>
    </article>
</section>

<article class="card card-feature">
    <header class="section-head">
        <div>
            <h3>Reajuste masivo por conteo</h3>
            <p>Escribe el stock contado final solo en los productos necesarios. El sistema calcula el ajuste automaticamente y guarda todo en un solo envio.</p>
        </div>
    </header>
    <form method="post" action="/inventory/adjust-bulk" class="form" data-bulk-adjust-form>
        <?= csrf_field() ?>
        <div class="catalog-toolbar">
            <label class="catalog-search">
                <span>Buscar por SKU, nombre o categoria</span>
                <input
                    type="search"
                    placeholder="Ej. tela, TORN-001, cierre..."
                    autocomplete="off"
                    data-table-filter-input
                    data-table-filter-target="inventory-bulk-adjust"
                >
            </label>
            <div class="catalog-results" data-table-filter-count data-table-filter-target="inventory-bulk-adjust" data-table-filter-label="productos">
                <?= count($products) ?> productos
            </div>
        </div>
        <label>Nota general
            <textarea name="notes" placeholder="Conteo general, motivo del reajuste, observacion comun para todas las filas editadas"></textarea>
        </label>
        <div class="live-panel">
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
        <div class="empty-state">
            Deja vacias las filas que no quieras tocar. Solo se procesan las que tengan stock contado escrito y una diferencia real contra el stock actual.
        </div>
        <button class="btn">Aplicar reajuste masivo</button>
    </form>
</article>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Historial</h3>
            <p>Consulta la trazabilidad y filtra rapido por producto o referencia.</p>
        </div>
    </header>
    <div class="catalog-toolbar">
        <label class="catalog-search">
            <span>Buscar en historial</span>
            <input
                type="search"
                placeholder="Ej. ajuste, tela, sku..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="inventory-movements-history"
            >
        </label>
        <div class="catalog-results" data-table-filter-count data-table-filter-target="inventory-movements-history" data-table-filter-label="movimientos">
            <?= count($movements) ?> movimientos
        </div>
    </div>
    <div class="table-wrap table-wrap-mobile-slider" data-table-filter-container="inventory-movements-history">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Referencia</th><th>Notas</th></tr></thead>
            <tbody data-table-filter-rows="inventory-movements-history" data-table-pagination data-table-pagination-size="15" data-table-pagination-filter-target="inventory-movements-history"><?php foreach ($movements as $row): ?><tr data-filter-search="<?= e(strtolower(trim(((string) ($row['product_name'] ?? '')) . ' ' . ((string) ($row['category_name'] ?? '')) . ' ' . ((string) ($row['movement_type'] ?? '')) . ' ' . ((string) ($row['reference'] ?? '')) . ' ' . ((string) ($row['notes'] ?? ''))))) ?>"><td><?= e($row['created_at']) ?></td><td><?= e(product_display_name($row['product_name'] ?? '', $row['category_name'] ?? '')) ?></td><td><?= e($row['movement_type']) ?></td><td><span class="badge <?= (float) $row['quantity'] < 0 ? 'badge-danger' : 'badge-ok' ?>"><?= money($row['quantity']) ?></span></td><td><?= e($row['reference']) ?></td><td><?= e($row['notes']) ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <div class="empty-state" data-table-filter-empty="inventory-movements-history" hidden>No hay movimientos que coincidan con la busqueda.</div>
    </div>
</article>
