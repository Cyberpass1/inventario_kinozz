
<?php
$purchaseDueDays = (int) ($purchaseDueDays ?? purchase_due_days());
$purchaseFilters = $purchaseFilters ?? ['search' => '', 'date_from' => '', 'date_to' => ''];
$historySearch = trim((string) ($purchaseFilters['search'] ?? ''));
$historyDateFrom = trim((string) ($purchaseFilters['date_from'] ?? ''));
$historyDateTo = trim((string) ($purchaseFilters['date_to'] ?? ''));
$nextNumber = (string) ($nextNumber ?? 'C-0001');
$exportQuery = http_build_query(array_filter([
    'q' => $historySearch,
    'from' => $historyDateFrom,
    'to' => $historyDateTo,
], static fn (string $value): bool => $value !== ''));
$exportHistoryUrl = '/purchases/export' . ($exportQuery !== '' ? '?' . $exportQuery : '');
$defaultPurchaseDate = date('Y-m-d');
$defaultPurchaseDueDate = document_due_date($defaultPurchaseDate, $purchaseDueDays);
$currentRole = (string) (($user['role'] ?? '') ?: ((auth_user()['role'] ?? '')));
$isAdministrator = $currentRole === 'administrator';
$resolvePaymentStatus = static function (array $purchase): string {
    if (($purchase['status'] ?? 'active') === 'cancelled') {
        return 'cancelled';
    }

    $balance = (float) ($purchase['balance_converted'] ?? 0);
    $paid = (float) ($purchase['amount_paid_converted'] ?? 0);
    $dueDate = (string) ($purchase['due_date'] ?? $purchase['purchase_date'] ?? '');

    if ($balance <= 0.01) {
        return 'paid';
    }

    if ($dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return $paid > 0 ? 'partial_overdue' : 'overdue';
    }

    return $paid > 0 ? 'partial' : 'pending';
};
$paymentMeta = static function (string $status): array {
    return match ($status) {
        'paid' => ['badge badge-ok', 'Pagada'],
        'partial' => ['badge badge-neutral', 'Pago parcial'],
        'overdue' => ['badge badge-danger', 'Vencida'],
        'partial_overdue' => ['badge badge-danger', 'Parcial vencida'],
        'cancelled' => ['badge badge-danger', 'Anulada'],
        default => ['badge badge-neutral', 'Pendiente'],
    };
};
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
                <small>Producto agregado a la compra. Ajusta cantidad y costo si hace falta.</small>
            </div>
            <button type="button" class="btn btn-outline btn-sm" data-line-remove>Quitar</button>
        </div>
        <div class="line-item-grid">
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

<section class="process-layout process-layout-single">
    <article class="card card-feature process-main">
        <header class="section-head">
            <div>
                <h3>Proceso de compra</h3>
                <p>Selecciona proveedor y agrega uno o varios productos con sus costos en un mismo documento.</p>
            </div>
            <div class="quick-actions">
                <button type="button" class="btn btn-outline" data-modal-open="supplier-modal">Agregar proveedor</button>
            </div>
        </header>

        <form method="post" action="/purchases" class="form two-cols process-form" data-calc="purchase" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-due-days="<?= e((string) $purchaseDueDays) ?>">
            <?= csrf_field() ?>
            <label>Proveedor
                <select name="supplier_id">
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier['id'] ?>"><?= e($supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Documento
                <input name="doc_number" value="<?= e($nextNumber) ?>" readonly>
           
            </label>
            <label>Fecha
                <input type="date" name="purchase_date" value="<?= e($defaultPurchaseDate) ?>" required>
            </label>
            <label>Vencimiento
                <input type="text" value="<?= e($defaultPurchaseDueDate) ?>" readonly data-due-date-display>
                <small>Automatico segun ajustes: <?= $purchaseDueDays ?> dias despues de la fecha.</small>
            </label>
            <label>Moneda
                <select name="currency_code" data-document-currency-select>
                    <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                    <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                </select>
            </label>
            <label>Tasa
                <input type="number" step="0.0001" name="exchange_rate" value="<?= e($rate['rate'] ?? default_exchange_rate()) ?>" data-rate-input readonly>
            </label>
            <div class="col-span-2 line-items-shell" data-line-items data-line-value-key="cost" data-line-value-label="Costo">
                <div class="line-items-inline">
                    <div>
                        <strong>Productos de la compra</strong>
                        <small>Registra la compra en la unidad base del producto para que el costo de produccion salga correcto.</small>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" data-modal-open="purchase-items-modal">Seleccionar productos</button>
                </div>
                <div class="line-items-summary" data-line-summary>
                    <div class="line-items-summary-head">
                        <strong data-line-summary-title>Sin productos agregados</strong>
                        <small data-line-summary-meta>Abre el modal para agregar uno o varios renglones.</small>
                    </div>
                    <div class="line-items-summary-list" data-line-summary-list></div>
                </div>
                <div class="modal-shell" data-modal="purchase-items-modal" aria-hidden="true">
                    <div class="modal-backdrop" data-modal-close></div>
                    <div class="modal-card modal-card-wide line-items-modal-card" role="dialog" aria-modal="true" aria-labelledby="purchase-items-title">
                        <header class="modal-header">
                            <div>
                                <span class="eyebrow">Productos</span>
                                <h3 id="purchase-items-title">Seleccionar productos de la compra</h3>
                            </div>
                            <button type="button" class="modal-close" data-modal-close>&times;</button>
                        </header>
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
                        <div class="line-items-list" data-line-items-list></div>
                        <template data-line-item-template>
                            <?php $renderPurchaseLine('items[__INDEX__]'); ?>
                        </template>
                        <div class="line-items-modal-actions">
                            <button type="button" class="btn btn-outline" data-line-item-add>Buscar otro</button>
                            <button type="button" class="btn" data-modal-close>Listo</button>
                        </div>
                    </div>
                </div>
            </div>
            <label class="col-span-2">Notas
                <textarea name="notes" placeholder="Observaciones de la compra, proveedor o condicion de pago"></textarea>
            </label>
            <label>Metodo de pago
                <select name="payment_method" data-payment-method-select>
                    <option value="cash" selected>Efectivo</option>
                    <?php foreach (payment_method_options() as $value => $label): ?>
                        <?php if ($value === 'cash') { continue; } ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Monto pagado ahora
                <input type="number" step="0.01" min="0" name="payment_amount_original" value="0" data-payment-amount-input>
                <small>Opcional. Si lo dejas en `0`, la compra quedara pendiente o parcial.</small>
            </label>
            <label>Moneda de pago
                <select name="payment_currency_code" data-payment-currency-select>
                    <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                    <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                </select>
            </label>
            <label>Referencia del pago
                <input name="payment_reference" placeholder="Opcional si quieres registrar el pago ahora">
            </label>
            <label class="col-span-2">Notas del pago
                <textarea name="payment_notes" placeholder="Banco, soporte, observaciones del pago al proveedor"></textarea>
            </label>
            <div class="col-span-2 empty-state">
                Si una materia prima se consume en metros, kilos o unidades, compra y controla ese producto en esa misma unidad base. El costo guardado sera costo por unidad base. El pago inicial es opcional.
            </div>

            <div class="col-span-2 live-panel process-totals">
                <div><span>Total original</span><strong data-purchase-original>0,00</strong></div>
                <div><span>Equiv. en Bs</span><strong data-purchase-converted>0,00</strong></div>
                <div><span>Renglones</span><strong data-line-count>0,00</strong></div>
                <div><span>Unidades</span><strong data-line-quantity-total>0,00</strong></div>
                <div><span>Pagas ahora</span><strong data-payment-applied>0,00</strong></div>
                <div><span>Restante</span><strong data-payment-remaining>0,00</strong></div>
            </div>

            <button class="btn col-span-2 process-submit">Guardar compra</button>
        </form>
    </article>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Historial reciente</h3>
            <p>Consulta detallada con productos, cantidad total, tasa de cierre y equivalente en bolivares segun la tasa registrada.</p>
        </div>
        <div class="actions-row">
            <a class="btn btn-outline" href="<?= e($exportHistoryUrl) ?>">Exportar historial</a>
        </div>
    </header>
    <form method="get" action="/purchases" class="form history-filters-form purchase-history-filters">
        <label class="form-span-2">Buscar compra
            <input type="search" name="q" value="<?= e($historySearch) ?>" placeholder="Documento, proveedor, producto o nota">
        </label>
        <label>Desde
            <input type="date" name="from" value="<?= e($historyDateFrom) ?>">
        </label>
        <label>Hasta
            <input type="date" name="to" value="<?= e($historyDateTo) ?>">
        </label>
        <div class="history-filters-actions">
            <button class="btn">Filtrar</button>
            <a class="btn btn-outline" href="/purchases">Limpiar</a>
        </div>
    </form>
    <div class="table-wrap table-wrap-scrollable table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Vence</th>
                    <th>Documento</th>
                    <th>Proveedor</th>
                    <th>Productos</th>
  
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Moneda</th>
                    <th>Tasa cierre</th>
                    <th>Total doc.</th>
                    <th>Abonado</th>
                    <th>Saldo</th>
                    <th>Equiv. Bs</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-table-pagination data-table-pagination-size="15">
                <?php if ($purchases): ?>
                    <?php foreach ($purchases as $purchase): ?>
                        <?php
                        $paymentStatus = $resolvePaymentStatus($purchase);
                        [$paymentBadge, $paymentLabel] = $paymentMeta($paymentStatus);
                        ?>
                        <tr>
                            <td data-label="Fecha"><?= e($purchase['purchase_date']) ?></td>
                            <td data-label="Vence"><?= e($purchase['due_date'] ?? $purchase['purchase_date']) ?></td>
                            <td data-label="Documento"><?= e($purchase['doc_number']) ?></td>
                            <td data-label="Proveedor"><?= e($purchase['supplier_name']) ?></td>
                            <td data-label="Productos">
                                <div class="money-stack">
                                    <strong><?= e($purchase['products_summary'] ?? 'Sin detalle') ?></strong>
                                    <small><?= trim((string) ($purchase['notes'] ?? '')) !== '' ? e($purchase['notes']) : 'Sin observaciones registradas.' ?></small>
                                </div>
                            </td>
                      
                            <td data-label="Cantidad">
                                <span class="badge badge-ok"><?= money($purchase['total_quantity'] ?? 0) ?></span>
                            </td>
                            <td data-label="Estado">
                                <span class="badge <?= ($purchase['status'] ?? 'active') === 'cancelled' ? 'badge-danger' : 'badge-ok' ?>">
                                    <?= ($purchase['status'] ?? 'active') === 'cancelled' ? 'Anulada' : 'Activa' ?>
                                </span>
                            </td>
                            <td data-label="Pago"><span class="<?= e($paymentBadge) ?>"><?= e($paymentLabel) ?></span></td>
                            <td data-label="Moneda"><?= e($purchase['currency_code']) ?></td>
                            <td data-label="Tasa cierre"><?= money($purchase['exchange_rate']) ?></td>
                            <td data-label="Total doc.">
                                <div class="money-stack">
                                    <strong><?= money($purchase['total_original']) ?> <?= e($purchase['currency_code']) ?></strong>
                                    <small>Monto registrado en la compra.</small>
                                </div>
                            </td>
                            <td data-label="Abonado">
                                <div class="money-stack">
                                    <strong><?= money($purchase['amount_paid_original'] ?? 0) ?> <?= e($purchase['currency_code']) ?></strong>
                                    <small><?= money($purchase['amount_paid_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></small>
                                </div>
                            </td>
                            <td data-label="Saldo">
                                <div class="money-stack">
                                    <strong><?= money($purchase['balance_original'] ?? 0) ?> <?= e($purchase['currency_code']) ?></strong>
                                    <small><?= money($purchase['balance_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></small>
                                </div>
                            </td>
                            <td data-label="Equiv. Bs">
                                <div class="money-stack">
                                    <strong><?= money(equivalent_in_bolivars($purchase['total_original'] ?? 0, $purchase['currency_code'] ?? '', $purchase['exchange_rate'] ?? 0)) ?> <?= e(secondary_currency()) ?></strong>
                                    <small>Equivalente en bolivares al cierre.</small>
                                </div>
                            </td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <!-- <a class="btn btn-sm btn-outline" href="/purchases/print/<?= $purchase['id'] ?>">Vista</a> -->
                                <a class="btn btn-sm btn-pdf" href="/purchases/pdf/<?= $purchase['id'] ?>" target="_blank" rel="noopener noreferrer">PDF</a>
                                <?php if ($isAdministrator && ($purchase['status'] ?? 'active') !== 'cancelled' && (float) ($purchase['balance_converted'] ?? 0) > 0.01): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        data-modal-open="purchase-payment-modal"
                                        data-document-payment-open
                                        data-document-payment-verb="Registrar pago"
                                        data-document-payment-title="<?= e((string) ($purchase['doc_number'] ?? '')) ?>"
                                        data-document-payment-action="<?= e(app_url('/purchases/payments/' . (int) $purchase['id'])) ?>"
                                        data-document-payment-total="<?= e((string) ($purchase['total_original'] ?? 0)) ?>"
                                        data-document-payment-paid="<?= e((string) ($purchase['amount_paid_original'] ?? 0)) ?>"
                                        data-document-payment-balance="<?= e((string) ($purchase['balance_original'] ?? 0)) ?>"
                                        data-document-payment-due-date="<?= e((string) ($purchase['due_date'] ?? $purchase['purchase_date'] ?? '')) ?>"
                                        data-document-payment-currency="<?= e((string) ($purchase['currency_code'] ?? secondary_currency())) ?>"
                                    >Pagar</button>
                                <?php endif; ?>
                                <?php if ($isAdministrator && ($purchase['status'] ?? 'active') !== 'cancelled'): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        data-modal-open="purchase-edit-modal"
                                        data-purchase-edit-open
                                        data-purchase-edit-url="<?= e(app_url('/purchases/edit-modal/' . (int) $purchase['id'])) ?>"
                                    >Editar</button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        data-modal-open="purchase-delete-modal"
                                        data-confirm-delete-open
                                        data-confirm-delete-action="<?= e(app_url('/purchases/delete/' . (int) $purchase['id'])) ?>"
                                        data-confirm-delete-title="<?= e('Eliminar compra ' . (string) ($purchase['doc_number'] ?? '')) ?>"
                                        data-confirm-delete-prompt="Esta accion elimina la compra y revierte el stock ingresado. Solo continua si estas seguro y el inventario actual permite revertirla."
                                        data-confirm-delete-placeholder="<?= e((string) ($purchase['doc_number'] ?? '')) ?>"
                                    >Eliminar</button>
                                <?php endif; ?>
                                <?php if (($purchase['status'] ?? 'active') !== 'cancelled'): ?>
                                    <form method="post" action="/purchases/cancel/<?= $purchase['id'] ?>" class="document-action-form" onsubmit="return confirm('Se anulara la compra y se descontara el stock incorporado.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-danger-soft">Anular</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="16" class="empty-state">No hay compras que coincidan con los filtros aplicados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>

<div class="modal-shell" data-modal="supplier-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="supplier-modal-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Proveedor</span>
                <h3 id="supplier-modal-title">Agregar proveedor</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/purchases/suppliers" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <label>Documento<input name="document"></label>
            <label>Telefono<input name="phone"></label>
            <label>Email<input name="email"></label>
            <label class="col-span-2">Direccion<textarea name="address"></textarea></label>
            <button class="btn col-span-2">Guardar proveedor</button>
        </form>
    </div>
</div>

<div
    class="modal-shell"
    data-modal="purchase-payment-modal"
    data-payment-currency-base="<?= e(base_currency()) ?>"
    data-payment-currency-secondary="<?= e(secondary_currency()) ?>"
    data-document-payment-verb="Registrar pago"
    aria-hidden="true"
>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="purchase-payment-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Pago</span>
                <h3 id="purchase-payment-title" data-document-payment-modal-title>Registrar pago</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="" class="form two-cols" data-document-payment-form>
            <?= csrf_field() ?>
            <div class="col-span-2 live-panel">
                <div><span>Total documento</span><strong data-document-payment-total>0,00</strong></div>
                <div><span>Pagado</span><strong data-document-payment-paid>0,00</strong></div>
                <div><span>Saldo pendiente</span><strong data-document-payment-balance>0,00</strong></div>
                <div><span>Vencimiento</span><strong data-document-payment-due-date>--</strong></div>
            </div>
            <label>Fecha de pago
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Referencia
                <input name="reference" required placeholder="Transferencia, cheque, efectivo...">
            </label>
            <label>Monto pagado
                <input type="number" step="0.01" name="amount_original" required>
            </label>
            <label>Moneda
                <select name="currency_code" data-document-payment-currency-select></select>
            </label>
            <label>Metodo de pago
                <select name="payment_method" data-payment-method-select>
                    <?php foreach (payment_method_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === 'cash' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="col-span-2">Notas
                <textarea name="notes" placeholder="Banco, soporte, observaciones del pago"></textarea>
            </label>
            <button class="btn col-span-2">Registrar pago</button>
        </form>
    </div>
</div>

<div class="modal-shell" data-modal="purchase-delete-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="purchase-delete-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Eliminacion</span>
                <h3 id="purchase-delete-title" data-confirm-delete-title>Eliminar compra</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="" class="form" data-confirm-delete-form>
            <?= csrf_field() ?>
            <div class="empty-state" data-confirm-delete-prompt>
                Esta accion elimina la compra y revierte el stock ingresado.
            </div>
            <label>Contrasena de administrador
                <input type="password" name="admin_password" required placeholder="Confirma tu contrasena">
            </label>
            <label>Escribe el documento exacto para confirmar
                <input name="confirm_doc_number" required data-confirm-delete-input>
            </label>
            <button class="btn">Eliminar compra</button>
        </form>
    </div>
</div>

<?php if ($isAdministrator): ?>
    <div class="modal-shell" data-modal="purchase-edit-modal" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="purchase-edit-shared-title">
            <div data-purchase-edit-container>
                <header class="modal-header">
                    <div>
                        <span class="eyebrow">Compra</span>
                        <h3 id="purchase-edit-shared-title">Editar compra</h3>
                    </div>
                    <button type="button" class="modal-close" data-modal-close>&times;</button>
                </header>
                <div class="empty-state">Cargando formulario...</div>
            </div>
        </div>
    </div>
<?php endif; ?>
