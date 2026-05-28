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
            data-price="<?= e((string) $product['cost']) ?>"
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
?>

<section class="pos-workspace" data-pos-workspace>
    <form method="post" action="/purchases" class="pos-form" data-calc="purchase" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>" data-due-days="<?= e((string) $purchaseDueDays) ?>">
        <?= csrf_field() ?>

        <header class="pos-topbar">
            <div class="pos-topbar-title">
                <h3>Registrar compra</h3>
                <small>Proveedor, items, pago y total en una sola mesa &middot; <kbd>F2</kbd> guardar &middot; <kbd>/</kbd> buscar item</small>
            </div>
            <div class="pos-topbar-actions">
                <button type="button" class="btn btn-outline btn-sm" data-modal-open="supplier-modal" title="Nuevo proveedor">+ Proveedor</button>
                <a class="btn btn-outline btn-sm" href="/suppliers" title="Gestionar proveedores">Gestionar</a>
            </div>
        </header>

        <div class="pos-grid">
            <!-- Columna 1: Proveedor + Meta -->
            <aside class="pos-col pos-col-left">
                <section class="pos-card">
                    <div class="pos-card-head"><strong>Proveedor</strong></div>
                    <label class="pos-supplier-label">
                        <select name="supplier_id" class="pos-supplier-select" required>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= e($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </section>

                <section class="pos-card">
                    <div class="pos-card-head"><strong>Documento</strong></div>
                    <div class="pos-meta-grid">
                        <label>Numero
                            <input name="doc_number" value="<?= e($nextNumber) ?>" readonly>
                        </label>
                        <label>Fecha
                            <input type="date" name="purchase_date" value="<?= e($defaultPurchaseDate) ?>" required>
                        </label>
                        <label>Vence
                            <input type="text" value="<?= e($defaultPurchaseDueDate) ?>" readonly data-due-date-display>
                        </label>
                        <label>Moneda
                            <select name="currency_code" data-document-currency-select>
                                <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                                <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                            </select>
                        </label>
                        <label class="pos-meta-span">Tasa
                            <input type="number" step="0.0001" name="exchange_rate" value="<?= e($rate['rate'] ?? default_exchange_rate()) ?>" data-rate-input readonly>
                        </label>
                    </div>
                    <small class="pos-meta-hint">Vencimiento automatico: <?= $purchaseDueDays ?> dias despues. Compras sin IVA.</small>
                </section>

                <details class="pos-card pos-notes">
                    <summary>Notas y observaciones</summary>
                    <div class="pos-notes-grid">
                        <label>Notas
                            <textarea name="notes" placeholder="Observaciones de la compra, proveedor o condicion de pago"></textarea>
                        </label>
                        <label>Notas del pago
                            <textarea name="payment_notes" placeholder="Banco, soporte, observaciones del pago al proveedor"></textarea>
                        </label>
                    </div>
                </details>
            </aside>

            <!-- Columna 2: Items (centro) -->
            <main class="pos-col pos-col-center">
                <section class="pos-card pos-items" data-line-items data-line-value-key="cost" data-line-value-label="Costo">
                    <div class="pos-card-head pos-items-head">
                        <strong>Productos de la compra</strong>
                        <span class="pos-hint"><kbd>/</kbd> enfoca buscador &middot; <kbd>Enter</kbd> agrega</span>
                    </div>

                    <div class="pos-search line-catalog-shell" data-line-catalog>
                        <input
                            type="text"
                            class="pos-search-input"
                            value=""
                            placeholder="Buscar por SKU o nombre, o agrega un producto nuevo..."
                            autocomplete="off"
                            data-line-catalog-search
                            data-purchase-custom-source
                        >
                        <button type="button" class="btn btn-outline btn-sm pos-search-add" data-purchase-custom-toggle title="Agregar un producto que no esta en el catalogo">+ Producto nuevo</button>
                        <small class="line-catalog-status pos-search-status" data-line-catalog-status>Escribe al menos 2 caracteres para buscar.</small>
                        <div class="line-catalog-results pos-search-results" data-line-catalog-results></div>
                        <select data-line-product-catalog hidden>
                            <option value="" data-sku="" data-stock="0" data-cost="0" data-price="0" data-product-type="merchandise" data-unit-label="und" data-currency="<?= e(base_currency()) ?>" selected></option>
                            <?= $productOptionsMarkup ?>
                        </select>
                    </div>

                    <!-- Panel inline para producto personalizado -->
                    <div class="pos-custom-product" data-purchase-custom-panel hidden>
                        <div class="pos-custom-product-head">
                            <strong>Agregar producto no catalogado</strong>
                            <button type="button" class="pos-custom-close" data-purchase-custom-close aria-label="Cerrar">&times;</button>
                        </div>
                        <small>Se creara como producto nuevo en tu catalogo al guardar la compra.</small>
                        <div class="pos-custom-grid">
                            <label class="pos-custom-span">Nombre del producto
                                <input type="text" data-purchase-custom-name placeholder="Ej. Caja de pernos 1/2&quot;" autocomplete="off">
                            </label>
                            <label>SKU
                                <input type="text" data-purchase-custom-sku placeholder="Opcional, se autogenera" autocomplete="off">
                            </label>
                            <label>Tipo
                                <select data-purchase-custom-type>
                                    <option value="merchandise" selected>Producto</option>
                                    <option value="raw_material">Materia prima</option>
                                </select>
                            </label>
                            <label>Unidad
                                <input type="text" data-purchase-custom-unit value="und" placeholder="und, kg, m, lt...">
                            </label>
                            <label>Cantidad
                                <input type="number" step="0.01" min="0.01" data-purchase-custom-qty value="1">
                            </label>
                            <label>Costo unitario
                                <input type="number" step="0.01" min="0" data-purchase-custom-cost value="0">
                            </label>
                        </div>
                        <div class="pos-custom-actions">
                            <button type="button" class="btn" data-purchase-custom-add>Agregar al lote</button>
                            <button type="button" class="btn btn-outline" data-purchase-custom-close>Cancelar</button>
                        </div>
                    </div>

                    <div class="line-items-summary pos-hidden-summary" data-line-summary aria-hidden="true">
                        <div class="line-items-summary-head">
                            <strong data-line-summary-title></strong>
                            <small data-line-summary-meta></small>
                        </div>
                        <div class="line-items-summary-list" data-line-summary-list></div>
                    </div>

                    <div class="pos-items-list line-items-list" data-line-items-list></div>
                    <div class="pos-items-empty" data-pos-items-empty>
                        <strong>Sin productos en la compra</strong>
                        <span>Busca uno existente o pulsa <strong>+ Producto nuevo</strong> para registrar uno suelto.</span>
                    </div>

                    <!-- Template renglon catalogado -->
                    <template data-line-item-template>
                        <div class="line-item-card" data-line-item>
                            <div class="line-item-head">
                                <div>
                                    <strong data-line-label>Renglon 1</strong>
                                    <small data-line-head-meta>Producto agregado a la compra.</small>
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
                                        <strong data-line-product-name>Sin producto</strong>
                                        <small data-line-product-meta>Selecciona un producto.</small>
                                    </div>
                                    <input type="hidden" name="items[__INDEX__][product_id]" value="" data-line-product-id>
                                    <input type="hidden" name="items[__INDEX__][source_currency]" value="<?= e(base_currency()) ?>" data-line-source-currency>
                                </div>
                                <label><span data-line-qty-label>Cantidad</span>
                                    <input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" value="1" required data-line-qty-input>
                                </label>
                                <label><span data-line-price-label>Costo unitario</span>
                                    <input type="number" step="0.01" min="0" name="items[__INDEX__][cost_original]" value="0" required data-line-price-input>
                                    <small data-line-price-help>Se convierte segun la moneda del documento.</small>
                                </label>
                                <div class="line-item-metrics">
                                    <div><span>Stock</span><strong data-line-stock>0,00</strong></div>
                                    <div><span>Total linea</span><strong data-line-subtotal>0,00</strong></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Template renglon custom (producto nuevo) -->
                    <template data-purchase-custom-template>
                        <div class="line-item-card line-item-card-custom" data-line-item data-purchase-custom-item>
                            <div class="line-item-head">
                                <div>
                                    <strong data-line-label>Renglon 1</strong>
                                    <small data-line-head-meta><span class="badge badge-neutral">Nuevo en catalogo</span> Se crea al guardar la compra.</small>
                                </div>
                                <div class="line-item-actions">
                                    <button type="button" class="btn btn-outline btn-sm" data-line-toggle aria-expanded="false">Detalles</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-line-remove>Quitar</button>
                                </div>
                            </div>
                            <div class="line-item-grid" hidden>
                                <div class="line-item-product">
                                    <span class="line-item-caption">Producto nuevo</span>
                                    <div class="line-item-identity">
                                        <strong data-line-product-name>Nuevo producto</strong>
                                        <small data-line-product-meta>Se creara al registrar la compra.</small>
                                    </div>
                                    <input type="hidden" name="items[__INDEX__][product_id]" value="" data-line-product-id>
                                    <input type="hidden" name="items[__INDEX__][source_currency]" value="<?= e(base_currency()) ?>" data-line-source-currency>
                                    <input type="hidden" name="items[__INDEX__][custom_name]" value="" data-custom-name>
                                    <input type="hidden" name="items[__INDEX__][custom_sku]" value="" data-custom-sku>
                                    <input type="hidden" name="items[__INDEX__][custom_product_type]" value="merchandise" data-custom-type>
                                    <input type="hidden" name="items[__INDEX__][custom_unit_label]" value="und" data-custom-unit>
                                </div>
                                <label><span>Cantidad</span>
                                    <input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" value="1" required data-line-qty-input>
                                </label>
                                <label><span>Costo unitario</span>
                                    <input type="number" step="0.01" min="0" name="items[__INDEX__][cost_original]" value="0" required data-line-price-input>
                                </label>
                                <div class="line-item-metrics">
                                    <div><span>Stock</span><strong data-line-stock>0</strong></div>
                                    <div><span>Total linea</span><strong data-line-subtotal>0,00</strong></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </section>
            </main>

            <!-- Columna 3: Totales + Pago (sticky) -->
            <aside class="pos-col pos-col-right">
                <section class="pos-card pos-total-card">
                    <div class="pos-total-hero">
                        <span>Total compra</span>
                        <strong data-purchase-original>0,00</strong>
                        <em data-purchase-total-currency><?= e(secondary_currency()) ?></em>
                    </div>
                    <div class="pos-total-grid">
                        <div><span>Renglones</span><strong data-line-count>0</strong></div>
                        <div><span>Unidades</span><strong data-line-quantity-total>0</strong></div>
                        <div><span>Pagas</span><strong data-payment-applied>0,00</strong></div>
                        <div><span>Restante</span><strong data-payment-remaining>0,00</strong></div>
                        <div class="pos-total-grid-wide"><span>Equiv. Bs</span><strong data-purchase-converted>0,00</strong></div>
                    </div>
                </section>

                <section class="pos-card pos-checkout">
                    <div class="pos-card-head">
                        <strong>Pago al proveedor</strong>
                        <span class="pos-hint">Opcional</span>
                    </div>
                    <div class="pos-quick-methods" data-payment-quick-actions>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-method-quick="cash">Efectivo</button>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-method-quick="point_of_sale">Punto</button>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-method-quick="bank_transfer">Transferencia</button>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-method-quick="mobile_payment">P. movil</button>
                    </div>
                    <div class="pos-checkout-grid">
                        <label class="pos-checkout-full">Metodo
                            <select name="payment_method" data-payment-method-select>
                                <option value="cash" selected>Efectivo</option>
                                <?php foreach (payment_method_options() as $value => $label): ?>
                                    <?php if ($value === 'cash') { continue; } ?>
                                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Monto
                            <input type="number" step="0.01" min="0" name="payment_amount_original" value="0" data-payment-amount-input>
                        </label>
                        <label>Moneda
                            <select name="payment_currency_code" data-payment-currency-select>
                                <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                                <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                            </select>
                        </label>
                        <label class="pos-checkout-full">Referencia
                            <input name="payment_reference" placeholder="Opcional">
                        </label>
                    </div>
                    <div class="pos-quick-amounts">
                        <button type="button" class="btn btn-outline btn-sm" data-payment-quick="full">Total</button>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-quick="half">50%</button>
                        <button type="button" class="btn btn-outline btn-sm" data-payment-quick="clear">Limpiar</button>
                    </div>
                </section>

                <button class="btn pos-submit" type="submit" title="F2">
                    <span>Guardar compra</span>
                    <kbd>F2</kbd>
                </button>
            </aside>
        </div>
    </form>
</section>

<details class="card pos-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3>Historial de compras <span class="pos-history-count">(<?= count($purchases) ?>)</span></h3>
            <p>Consulta por periodos, busca una compra puntual y exporta a Excel.</p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>

    <div class="pos-history-body">
        <div class="actions-row pos-history-export">
            <a class="btn btn-outline" href="<?= e($exportHistoryUrl) ?>">Exportar historial</a>
        </div>
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
                            <td colspan="15" class="empty-state">No hay compras que coincidan con los filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

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

<script>
(function () {
    "use strict";

    const workspace = document.querySelector("[data-pos-workspace]");
    if (!workspace) return;

    const panel = workspace.querySelector("[data-purchase-custom-panel]");
    const toggleBtn = workspace.querySelector("[data-purchase-custom-toggle]");
    const closeButtons = workspace.querySelectorAll("[data-purchase-custom-close]");
    const sourceSearch = workspace.querySelector("[data-purchase-custom-source]");
    const customTemplate = workspace.querySelector("[data-purchase-custom-template]");
    const itemsList = workspace.querySelector("[data-line-items-list]");
    const itemsShell = workspace.querySelector("[data-line-items]");

    const nameInput = workspace.querySelector("[data-purchase-custom-name]");
    const skuInput = workspace.querySelector("[data-purchase-custom-sku]");
    const typeInput = workspace.querySelector("[data-purchase-custom-type]");
    const unitInput = workspace.querySelector("[data-purchase-custom-unit]");
    const qtyInput = workspace.querySelector("[data-purchase-custom-qty]");
    const costInput = workspace.querySelector("[data-purchase-custom-cost]");
    const addBtn = workspace.querySelector("[data-purchase-custom-add]");

    if (!panel || !toggleBtn || !customTemplate || !itemsList || !nameInput) return;

    const showPanel = () => {
        panel.hidden = false;
        if (sourceSearch && sourceSearch.value.trim().length >= 2) {
            nameInput.value = sourceSearch.value.trim();
        }
        window.setTimeout(() => nameInput.focus(), 50);
    };

    const hidePanel = () => {
        panel.hidden = true;
        nameInput.value = "";
        if (skuInput) skuInput.value = "";
        if (qtyInput) qtyInput.value = "1";
        if (costInput) costInput.value = "0";
        if (unitInput) unitInput.value = "und";
        if (typeInput) typeInput.value = "merchandise";
    };

    toggleBtn.addEventListener("click", showPanel);
    closeButtons.forEach((btn) => btn.addEventListener("click", hidePanel));

    let customIndex = 0;
    const nextIndex = () => {
        const allHidden = workspace.querySelectorAll("input[name^='items['][name$='][product_id]']");
        let max = 0;
        allHidden.forEach((el) => {
            const m = el.name.match(/items\[(\d+)\]/);
            if (m) {
                const n = parseInt(m[1], 10);
                if (n > max) max = n;
            }
        });
        return max + 1 + customIndex++;
    };

    const formatNumber = (n) => {
        const v = Number(n) || 0;
        return v.toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    addBtn.addEventListener("click", () => {
        const name = nameInput.value.trim();
        if (name === "") {
            nameInput.focus();
            return;
        }

        const sku = skuInput ? skuInput.value.trim() : "";
        const type = typeInput ? typeInput.value : "merchandise";
        const unit = unitInput ? (unitInput.value.trim() || "und") : "und";
        const qty = parseFloat(qtyInput.value) || 1;
        const cost = parseFloat(costInput.value) || 0;

        const idx = nextIndex();
        const fragment = customTemplate.content.cloneNode(true);
        fragment.querySelectorAll("[name]").forEach((el) => {
            el.name = el.name.replace(/__INDEX__/g, String(idx));
        });

        const card = fragment.querySelector("[data-line-item]");
        card.querySelector("[data-line-product-name]").textContent = (sku !== "" ? sku + " " : "") + name;
        card.querySelector("[data-line-product-meta]").textContent =
            (type === "raw_material" ? "Materia prima" : "Producto") + " | Unidad " + unit + " | Sera creado al guardar";

        card.querySelector("[data-custom-name]").value = name;
        card.querySelector("[data-custom-sku]").value = sku;
        card.querySelector("[data-custom-type]").value = type;
        card.querySelector("[data-custom-unit]").value = unit;
        card.querySelector("[data-line-qty-input]").value = String(qty);
        card.querySelector("[data-line-price-input]").value = String(cost);
        card.querySelector("[data-line-subtotal]").textContent = formatNumber(qty * cost);

        // Bind locales (toggle, remove, recalcular subtotal)
        const toggleBtnRow = card.querySelector("[data-line-toggle]");
        const grid = card.querySelector(".line-item-grid");
        if (toggleBtnRow && grid) {
            toggleBtnRow.addEventListener("click", () => {
                const expanded = toggleBtnRow.getAttribute("aria-expanded") === "true";
                toggleBtnRow.setAttribute("aria-expanded", expanded ? "false" : "true");
                grid.hidden = expanded;
            });
        }
        const removeBtn = card.querySelector("[data-line-remove]");
        if (removeBtn) {
            removeBtn.addEventListener("click", () => {
                card.remove();
                if (itemsShell) {
                    itemsShell.dataset.hasItems = itemsList.querySelectorAll("[data-line-item]").length > 0 ? "1" : "0";
                }
                // Forzar recalculo
                workspace.querySelector(".pos-form")?.dispatchEvent(new Event("input", { bubbles: true }));
            });
        }
        const recalcRow = () => {
            const q = parseFloat(card.querySelector("[data-line-qty-input]").value) || 0;
            const c = parseFloat(card.querySelector("[data-line-price-input]").value) || 0;
            card.querySelector("[data-line-subtotal]").textContent = formatNumber(q * c);
        };
        card.querySelectorAll("[data-line-qty-input], [data-line-price-input]").forEach((input) => {
            input.addEventListener("input", recalcRow);
        });

        itemsList.appendChild(card);
        if (itemsShell) itemsShell.dataset.hasItems = "1";

        // Dispatch input event para que el calculo de totales corra
        workspace.querySelector(".pos-form")?.dispatchEvent(new Event("input", { bubbles: true }));

        hidePanel();
    });
})();
</script>
