<?php
$detail = $detail ?? [];
$suppliersAll = $suppliersAll ?? [];
$products = $products ?? [];
$productsById = $productsById ?? [];
$purchaseDueDays = (int) ($purchaseDueDays ?? purchase_due_days());
$detailItems = is_array($detail['items'] ?? null) ? array_values(array_filter($detail['items'], 'is_array')) : [];

$productOptionsMarkup = (static function (array $products): string {
    ob_start();
    foreach ($products as $product): ?>
        <option
            value="<?= $product['id'] ?>"
            data-sku="<?= e((string) ($product['sku'] ?? '')) ?>"
            data-cost="<?= e((string) $product['cost']) ?>"
            data-stock="<?= e((string) $product['stock']) ?>"
            data-product-type="<?= e((string) ($product['product_type'] ?? 'merchandise')) ?>"
            data-unit-label="<?= e(product_unit_label($product)) ?>"
            data-currency="<?= e((string) ($product['currency_code'] ?? base_currency())) ?>"
            data-type-label="<?= e(product_type_label($product['product_type'] ?? 'merchandise')) ?>"
            data-track-stock="<?= product_tracks_inventory($product) ? '1' : '0' ?>"
        >
            <?= e(trim(((string) ($product['sku'] ?? '')) . ' ' . ($product['name'] ?? ''))) ?>
        </option>
    <?php endforeach;

    return trim((string) ob_get_clean());
})($products);

$renderPurchaseLine = static function (string $namePrefix, ?array $item = null, ?array $product = null, ?string $documentCurrency = null): void {
    $productId = (int) ($item['product_id'] ?? 0);
    $quantity = (float) ($item['quantity'] ?? 1);
    $costOriginal = (float) ($item['cost_original'] ?? 0);
    $stock = (float) ($product['stock'] ?? $product['product_stock'] ?? 0);
    $productName = trim((string) ($product['name'] ?? $product['product_name'] ?? ''));
    $productSku = trim((string) ($product['sku'] ?? $product['product_sku'] ?? ''));
    $productType = (string) ($product['product_type'] ?? 'merchandise');
    $isRawMaterial = $productType === 'raw_material';
    $unitLabel = product_unit_label($product, 'merchandise');
    $sourceCurrency = trim((string) ($item['source_currency'] ?? $documentCurrency ?? base_currency()));
    $isArchived = trim((string) ($product['deleted_at'] ?? $product['product_deleted_at'] ?? '')) !== ''
        || (string) ($product['status'] ?? $product['product_status'] ?? 'active') === 'inactive';
    $lineMeta = [];
    $lineMeta[] = product_type_label($product['product_type'] ?? 'merchandise');
    $lineMeta[] = $productSku !== '' ? 'SKU ' . $productSku : 'SKU no definido';
    if ($isRawMaterial) {
        $lineMeta[] = 'Unidad ' . $unitLabel;
        $lineMeta[] = 'Stock ' . money($stock) . ' ' . $unitLabel;
    } else {
        $lineMeta[] = 'Stock ' . money($stock);
    }
    if ($costOriginal > 0) {
        $lineMeta[] = $isRawMaterial
            ? 'Costo ' . money($costOriginal) . ' / ' . $unitLabel
            : 'Costo ' . money($costOriginal);
    }
    if ($isArchived) {
        $lineMeta[] = 'Archivado';
    }
    ?>
    <div class="line-item-card" data-line-item data-stock="<?= e((string) $stock) ?>" data-product-type="<?= e($productType) ?>" data-display-unit="<?= $isRawMaterial ? '1' : '0' ?>">
        <div class="line-item-head">
            <div>
                <strong data-line-label>Producto 1</strong>
                <small data-line-head-meta>Producto agregado a la compra. Ajusta cantidad y costo si hace falta.</small>
            </div>
            <div class="line-item-actions">
                <button type="button" class="btn btn-outline btn-sm" data-line-toggle aria-expanded="false">Detalles</button>
                <button type="button" class="btn btn-outline btn-sm" data-line-remove>Quitar</button>
            </div>
        </div>
        <div class="line-item-grid" hidden>
            <div class="line-item-product">
                <span class="line-item-caption">Producto</span>
                <div class="line-item-identity">
                    <strong data-line-product-name><?= e($productName !== '' ? trim($productSku . ' ' . $productName) : 'Sin producto') ?></strong>
                    <small data-line-product-meta><?= e(implode(' | ', $lineMeta)) ?></small>
                </div>
                <input type="hidden" name="<?= e($namePrefix) ?>[product_id]" value="<?= $productId > 0 ? e((string) $productId) : '' ?>" data-line-product-id>
                <input type="hidden" name="<?= e($namePrefix) ?>[source_currency]" value="<?= e($sourceCurrency) ?>" data-line-source-currency>
            </div>
            <label>
                <span data-line-qty-label><?= $isRawMaterial ? 'Cantidad (' . e($unitLabel) . ')' : 'Cantidad' ?></span>
                <input type="number" step="<?= $isRawMaterial ? '0.01' : '1' ?>" min="<?= $isRawMaterial ? '0.01' : '1' ?>" name="<?= e($namePrefix) ?>[quantity]" value="<?= e((string) $quantity) ?>" required data-line-qty-input>
            </label>
            <label>
                <span data-line-price-label><?= $isRawMaterial ? 'Costo por ' . e($unitLabel) : 'Costo unitario' ?></span>
                <input type="number" step="0.01" min="0" name="<?= e($namePrefix) ?>[cost_original]" value="<?= e((string) $costOriginal) ?>" required data-line-price-input>
                <small data-line-price-help>
                    <?= $isRawMaterial
                        ? 'Materia prima: usa el costo por unidad base del insumo.'
                        : 'Producto directo: compra simple sin conversiones adicionales.' ?>
                </small>
            </label>
            <div class="line-item-metrics">
                <div><span>Stock</span><strong data-line-stock><?= $isRawMaterial ? money($stock) . ' ' . e($unitLabel) : money($stock) ?></strong></div>
                <div><span>Total linea</span><strong data-line-subtotal><?= money($quantity * $costOriginal) ?></strong></div>
            </div>
        </div>
    </div>
<?php };
?>
<header class="modal-header">
    <div>
        <span class="eyebrow">Compra</span>
        <h3>Editar <?= e((string) ($detail['doc_number'] ?? '')) ?></h3>
    </div>
    <button type="button" class="modal-close" data-modal-close>&times;</button>
</header>
<form method="post" action="<?= e(app_url('/purchases/' . (int) ($detail['id'] ?? 0))) ?>" class="form two-cols" data-purchase-edit-form data-due-days="<?= e((string) $purchaseDueDays) ?>">
    <?= csrf_field() ?>
    <label>Proveedor
        <select name="supplier_id">
            <?php foreach ($suppliersAll as $supplier): ?>
                <option value="<?= $supplier['id'] ?>" <?= (int) ($detail['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                    <?= e($supplier['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Documento
        <input name="doc_number" value="<?= e((string) ($detail['doc_number'] ?? '')) ?>" required>
    </label>
    <label>Fecha
        <input type="date" name="purchase_date" value="<?= e((string) ($detail['purchase_date'] ?? date('Y-m-d'))) ?>" required>
    </label>
    <label>Vencimiento
        <input type="text" value="<?= e((string) ($detail['due_date'] ?? $detail['purchase_date'] ?? date('Y-m-d'))) ?>" readonly data-due-date-display>
        <small>Se recalcula automaticamente segun la fecha y los ajustes vigentes.</small>
    </label>
    <label>Moneda
        <select name="currency_code" data-document-currency-select>
            <option value="<?= e(secondary_currency()) ?>" <?= ($detail['currency_code'] ?? '') === secondary_currency() ? 'selected' : '' ?>><?= e(secondary_currency()) ?></option>
            <option value="<?= e(base_currency()) ?>" <?= ($detail['currency_code'] ?? '') === base_currency() ? 'selected' : '' ?>><?= e(base_currency()) ?></option>
        </select>
    </label>
    <label>Tasa
        <input type="number" step="0.0001" name="exchange_rate" value="<?= e((string) ($detail['exchange_rate'] ?? default_exchange_rate())) ?>" required readonly>
    </label>
    <div class="col-span-2 line-items-shell" data-line-items data-line-value-key="cost" data-line-value-label="Costo">
        <div class="line-items-inline">
            <div>
                <strong>Productos de la compra</strong>
                <small>Busca otro producto y quedara agregado a este documento.</small>
            </div>
            <button type="button" class="btn btn-outline btn-sm" data-line-item-add>Agregar producto</button>
        </div>
        <div class="line-items-summary" data-line-summary>
            <div class="line-items-summary-head">
                <strong data-line-summary-title>Productos listos</strong>
                <small data-line-summary-meta>Revisa cantidades y costos antes de guardar.</small>
            </div>
            <div class="line-items-summary-list" data-line-summary-list></div>
        </div>
        <div class="line-catalog-shell" data-line-catalog>
            <label>Buscar producto
                <input
                    type="text"
                    value=""
                    placeholder="Escribe SKU o nombre y agrega con un clic"
                    autocomplete="off"
                    data-line-catalog-search
                >
            </label>
            <small class="line-catalog-status" data-line-catalog-status>Escribe al menos 2 caracteres, SKU o nombre para buscar.</small>
            <div class="line-catalog-results" data-line-catalog-results></div>
            <select data-line-product-catalog hidden>
                <option value="" data-sku="" data-stock="0" data-cost="0" data-product-type="merchandise" data-unit-label="und" data-currency="<?= e(base_currency()) ?>" selected></option>
                <?= $productOptionsMarkup ?>
            </select>
        </div>
        <div class="line-items-list" data-line-items-list>
            <?php foreach ($detailItems as $index => $detailItem): ?>
                <?php
                $detailProductId = (int) ($detailItem['product_id'] ?? 0);
                $detailProduct = $productsById[$detailProductId] ?? $detailItem;
                $detailItem['source_currency'] = (string) ($detail['currency_code'] ?? base_currency());
                ?>
                <?php $renderPurchaseLine('items[' . $index . ']', $detailItem, $detailProduct, (string) ($detail['currency_code'] ?? base_currency())); ?>
            <?php endforeach; ?>
        </div>
        <template data-line-item-template>
            <?php $renderPurchaseLine('items[__INDEX__]'); ?>
        </template>
    </div>
    <label class="col-span-2">Notas
        <textarea name="notes"><?= e((string) ($detail['notes'] ?? '')) ?></textarea>
    </label>
    <label class="col-span-2">Segunda validacion
        <input type="password" name="admin_password" required placeholder="Ingresa tu contrasena de administrador">
    </label>
    <button class="btn col-span-2">Guardar cambios</button>
</form>
