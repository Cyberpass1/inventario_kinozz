<?php
$clientHints = $clientHints ?? [];
$invoiceDueDays = (int) ($invoiceDueDays ?? invoice_due_days());
$defaultInvoiceDate = date('Y-m-d');
$defaultInvoiceDueDate = document_due_date($defaultInvoiceDate, $invoiceDueDays);
$currentRole = (string) (auth_user()['role'] ?? '');
$canRegisterCollection = in_array($currentRole, ['administrator', 'vendor'], true);
$canFilterHistory = (bool) ($canFilterHistory ?? in_array($currentRole, ['administrator', 'general_consultant'], true));
$historyFilters = is_array($historyFilters ?? null) ? $historyFilters : [
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-d'),
    'q' => '',
];
$historyExportUrl = '/invoices/export' . (string) ($historyExportQuery ?? '');
$resolvePaymentStatus = static function (array $invoice): string {
    if (($invoice['status'] ?? 'active') === 'cancelled') {
        return 'cancelled';
    }

    $balance = (float) ($invoice['balance_converted'] ?? 0);
    $paid = (float) ($invoice['amount_paid_converted'] ?? 0);
    $dueDate = (string) ($invoice['due_date'] ?? $invoice['invoice_date'] ?? '');

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
        'paid' => ['badge badge-ok', 'Cobrada'],
        'partial' => ['badge badge-neutral', 'Abono parcial'],
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
            data-stock="<?= e((string) $product['stock']) ?>"
            data-price="<?= e((string) $product['price']) ?>"
            data-currency="<?= e((string) ($product['currency_code'] ?? base_currency())) ?>"
            data-product-type="<?= e((string) ($product['product_type'] ?? 'merchandise')) ?>"
            data-type-label="<?= e(product_type_label($product['product_type'] ?? 'merchandise')) ?>"
            data-track-stock="<?= product_tracks_inventory($product) ? '1' : '0' ?>"
        >
            <?= e(trim(((string) ($product['sku'] ?? '')) . ' ' . ($product['name'] ?? ''))) ?>
        </option>
    <?php endforeach;

    return trim((string) ob_get_clean());
})($products);


$renderInvoiceLine = static function (string $namePrefix) use ($productOptionsMarkup): void { ?>
    <div class="line-item-card" data-line-item>
        <div class="line-item-head">
            <div>
                <strong data-line-label>Renglon 1</strong>
                <small data-line-head-meta>Producto o servicio agregado al documento.</small>
            </div>
            <div class="line-item-actions">
                <button type="button" class="btn btn-outline btn-sm" data-line-toggle aria-expanded="false" title="Mostrar detalles">Detalles</button>
                <button type="button" class="btn btn-outline btn-sm" data-line-remove title="Quitar (Supr)">Quitar</button>
            </div>
        </div>
        <div class="line-item-grid" hidden>
                <div class="line-item-product">
                    <span class="line-item-caption">Producto</span>
                    <div class="line-item-identity">
                        <strong data-line-product-name>Sin producto</strong>
                        <small data-line-product-meta>Selecciona un producto o servicio.</small>
                    </div>
                    <input type="hidden" name="<?= e($namePrefix) ?>[product_id]" value="" data-line-product-id>
                    <input type="hidden" name="<?= e($namePrefix) ?>[source_currency]" value="<?= e(base_currency()) ?>" data-line-source-currency>
                </div>
            <label><span data-line-qty-label>Cantidad</span>
                <input type="number" step="1" min="1" name="<?= e($namePrefix) ?>[quantity]" value="1" required data-line-qty-input>
            </label>
            <label><span data-line-price-label>Precio unitario</span>
                <input type="number" step="0.01" min="0" name="<?= e($namePrefix) ?>[price_original]" value="0" required data-line-price-input>
                <small data-line-price-help>Se convierte segun la moneda.</small>
            </label>
            <div class="line-item-metrics">
                <div><span>Stock</span><strong data-line-stock>0,00</strong></div>
                <div><span>Subtotal</span><strong data-line-subtotal>0,00</strong></div>
            </div>
        </div>
    </div>
<?php };
?>

<section class="pos-workspace" data-pos-workspace>
    <form method="post" action="/invoices" class="pos-form" data-calc="invoice" data-tax-rate="<?= e((string) tax_percent()) ?>" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>" data-due-days="<?= e((string) $invoiceDueDays) ?>" data-ajax-form="1">
        <?= csrf_field() ?>

        <header class="pos-topbar">
            <div class="pos-topbar-title">
                <h3>Facturacion rapida</h3>
                <small>Cliente, items, cobro y total en una sola mesa &middot; <kbd>F2</kbd> guardar &middot; <kbd>/</kbd> buscar item &middot; <kbd>Ctrl</kbd>+<kbd>K</kbd> cliente</small>
            </div>
            <div class="pos-topbar-actions">
                <button type="button" class="btn btn-outline btn-sm" data-modal-open="client-invoice-modal" title="Nuevo cliente">+ Cliente</button>
                <a class="btn btn-outline btn-sm" href="/clients" title="Gestionar clientes">Gestionar</a>
            </div>
        </header>

        <div class="pos-grid">
            <!-- Columna 1: Cliente + Meta -->
            <aside class="pos-col pos-col-left">
                <section class="pos-card">
                    <div class="pos-card-head">
                        <strong>Cliente</strong>
                        <span class="pos-hint">Ctrl + K</span>
                    </div>
                    <div class="client-search-shell pos-client" data-client-picker data-search-url="<?= e(app_url('/clients/search')) ?>" data-client-create-modal="client-invoice-modal">
                        <input type="hidden" name="client_id" value="">
                        <input
                            type="text"
                            class="pos-client-input"
                            value=""
                            placeholder="Nombre, cedula o documento..."
                            autocomplete="off"
                            data-client-search
                            data-pos-client-input
                        >
                        <div class="client-search-panel" data-client-panel hidden>
                            <div class="client-search-status" data-client-status>Escribe 2+ letras o numeros para buscar.</div>
                            <div class="client-search-results" data-client-results>
                                <?php foreach ($clientHints as $client): ?>
                                    <button
                                        type="button"
                                        class="client-option"
                                        data-client-option
                                        data-id="<?= (int) $client['id'] ?>"
                                        data-name="<?= e($client['name']) ?>"
                                        data-document="<?= e($client['document'] ?? '') ?>"
                                        data-phone="<?= e($client['phone'] ?? '') ?>"
                                        data-email="<?= e($client['email'] ?? '') ?>"
                                    >
                                        <strong><?= e($client['name']) ?></strong>
                                        <span><?= e($client['document'] ?? 'Sin documento') ?></span>
                                        <small><?= (int) ($client['invoices_count'] ?? 0) ?> registros recientes</small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div
                            class="pos-client-selected"
                            data-client-selected
                            data-empty-name="Sin cliente seleccionado"
                            data-empty-meta="Busca y elige un cliente."
                            data-pending-label="Pendiente"
                            data-selected-label="Seleccionado"
                        >
                            <div class="pos-client-selected-top">
                                <span class="client-search-badge" data-client-selected-state>Pendiente</span>
                                <button type="button" class="client-search-clear" data-client-clear hidden>Limpiar</button>
                            </div>
                            <strong data-client-selected-name>Sin cliente seleccionado</strong>
                            <small data-client-selected-meta>Busca y elige un cliente.</small>
                        </div>
                    </div>
                </section>

                <section class="pos-card">
                    <div class="pos-card-head"><strong>Documento</strong></div>
                    <div class="pos-meta-grid">
                        <label>Numero
                            <input name="invoice_number" value="<?= e($nextNumber) ?>" required>
                        </label>
                        <label>Fecha
                            <input type="date" name="invoice_date" value="<?= e($defaultInvoiceDate) ?>" required>
                        </label>
                        <label>Vence
                            <input type="text" value="<?= e($defaultInvoiceDueDate) ?>" readonly data-due-date-display>
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
                    <small class="pos-meta-hint">Vencimiento automatico: <?= $invoiceDueDays ?> dias despues de la fecha.</small>
                </section>

                <details class="pos-card pos-notes">
                    <summary>Notas y observaciones</summary>
                    <div class="pos-notes-grid">
                        <label>Notas
                            <textarea name="notes" placeholder="Observaciones de la venta, condicion comercial o datos de entrega"></textarea>
                        </label>
                        <label>Notas del pago
                            <textarea name="payment_notes" placeholder="Banco, soporte o aclaracion del cobro inicial"></textarea>
                        </label>
                    </div>
                </details>
            </aside>

            <!-- Columna 2: Items (centro) -->
            <main class="pos-col pos-col-center">
                <section class="pos-card pos-items" data-line-items data-line-value-key="price" data-line-value-label="Precio">
                    <div class="pos-card-head pos-items-head">
                        <strong>Items del documento</strong>
                        <span class="pos-hint"><kbd>/</kbd> enfoca buscador &middot; <kbd>Enter</kbd> agrega</span>
                    </div>

                    <div class="pos-search line-catalog-shell" data-line-catalog>
                        <input
                            type="text"
                            class="pos-search-input"
                            value=""
                            placeholder="Buscar por SKU o nombre y presiona Enter..."
                            autocomplete="off"
                            data-line-catalog-search
                        >
                        <button type="button" class="btn btn-outline btn-sm pos-search-add" data-line-item-add title="Agregar manualmente">+ Renglon</button>
                        <small class="line-catalog-status pos-search-status" data-line-catalog-status>Escribe al menos 2 caracteres para buscar.</small>
                        <div class="line-catalog-results pos-search-results" data-line-catalog-results></div>
                        <select data-line-product-catalog hidden>
                            <option value="" data-sku="" data-stock="0" data-price="0" data-currency="<?= e(base_currency()) ?>" selected></option>
                            <?= $productOptionsMarkup ?>
                        </select>
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
                        <strong>Sin productos agregados</strong>
                        <span>Usa el buscador o pulsa <kbd>/</kbd> para empezar.</span>
                    </div>
                    <template data-line-item-template>
                        <?php $renderInvoiceLine('items[__INDEX__]'); ?>
                    </template>
                </section>
            </main>

            <!-- Columna 3: Totales + Cobro (sticky) -->
            <aside class="pos-col pos-col-right">
                <section class="pos-card pos-total-card">
                    <div class="pos-total-hero">
                        <span>Total documento</span>
                        <strong data-invoice-total>0,00</strong>
                        <em data-invoice-total-currency><?= e(secondary_currency()) ?></em>
                    </div>
                    <div class="pos-total-grid">
                        <div><span>Subtotal</span><strong data-invoice-subtotal>0,00</strong></div>
                        <div><span>IVA</span><strong data-invoice-tax>0,00</strong></div>
                        <div><span>Renglones</span><strong data-line-count>0</strong></div>
                        <div><span>Unidades</span><strong data-line-quantity-total>0</strong></div>
                        <div><span>Pagado</span><strong data-payment-applied>0,00</strong></div>
                        <div><span>Saldo</span><strong data-payment-remaining>0,00</strong></div>
                        <div class="pos-total-grid-wide"><span data-invoice-equivalent-label>Equiv. <?= e(secondary_currency()) ?></span><strong data-invoice-total-bolivars>0,00</strong></div>
                    </div>
                </section>

                <section class="pos-card pos-checkout">
                    <div class="pos-card-head">
                        <strong>Cobro</strong>
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
                    <span>Guardar factura</span>
                    <kbd>F2</kbd>
                </button>
            </aside>
        </div>
    </form>
</section>

<details class="card pos-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3><?= $canFilterHistory ? 'Historial de facturas' : 'Ultimas 10 facturas' ?> <span class="pos-history-count">(<?= count($invoices) ?>)</span></h3>
            <p>
                <?= $canFilterHistory
                    ? 'Consulta por periodos, busca una factura puntual y exporta a Excel.'
                    : 'Solo veras las 10 mas recientes.' ?>
            </p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>

    <div class="pos-history-body">
        <div class="actions-row pos-history-export">
            <a class="btn btn-outline" href="<?= e($historyExportUrl) ?>">Exportar a Excel</a>
        </div>
    <?php if ($canFilterHistory): ?>
        <form method="get" action="/invoices" class="form history-filters-form">
            <label>Desde
                <input type="date" name="date_from" value="<?= e((string) ($historyFilters['date_from'] ?? '')) ?>">
            </label>
            <label>Hasta
                <input type="date" name="date_to" value="<?= e((string) ($historyFilters['date_to'] ?? '')) ?>">
            </label>
            <label class="history-filters-search">Buscar
                <input type="text" name="q" value="<?= e((string) ($historyFilters['q'] ?? '')) ?>" placeholder="Numero, cliente, documento, SKU o producto">
            </label>
            <div class="actions-row history-filters-actions">
                <button type="submit" class="btn btn-outline">Filtrar</button>
                <a class="btn btn-outline" href="/invoices">Limpiar</a>
            </div>
        </form>
    <?php endif; ?>
    <div class="history-caption">
        <strong><?= count($invoices) ?></strong>
        <span><?= $canFilterHistory ? 'registros encontrados para el filtro actual.' : 'registros visibles en el historial rapido del vendedor.' ?></span>
    </div>
    <div class="table-wrap">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Vence</th>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th>Productos</th>

                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Cobro</th>
                    <th>Moneda</th>
                    <th>Tasa</th>
                    <th>Subtotal</th>
                    <th>IVA</th>
                    <th>Total doc.</th>
                    <th>Abonado</th>
                    <th>Saldo</th>
                    <th>Equiv. Bs</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-table-pagination data-table-pagination-size="15">
                <?php if ($invoices): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $paymentStatus = $resolvePaymentStatus($invoice);
                        [$paymentBadge, $paymentLabel] = $paymentMeta($paymentStatus);
                        ?>
                        <tr>
                            <td data-label="Fecha"><?= e($invoice['invoice_date']) ?></td>
                            <td data-label="Vence"><?= e($invoice['due_date'] ?? $invoice['invoice_date']) ?></td>
                            <td data-label="Numero"><?= e($invoice['invoice_number']) ?></td>
                            <td data-label="Cliente">
                                <div class="money-stack">
                                    <strong><?= e($invoice['client_name']) ?></strong>
                                    <small><?= e($invoice['client_document'] ?? 'Sin documento') ?></small>
                                </div>
                            </td>
                            <td data-label="Productos">
                                <div class="document-products-cell">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline btn-icon btn-eye"
                                        data-modal-open="invoice-products-modal"
                                        data-document-preview-open
                                        data-document-preview-url="<?= e(app_url('/invoices/details/' . (int) $invoice['id'])) ?>"
                                        aria-label="Ver productos de la factura <?= e($invoice['invoice_number'] ?? '') ?>"
                                        title="Ver productos"
                                    >
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>

                            <td data-label="Cantidad"><span class="badge badge-ok"><?= money($invoice['total_quantity'] ?? 0) ?></span></td>
                            <td data-label="Estado">
                                <span class="badge <?= ($invoice['status'] ?? 'active') === 'cancelled' ? 'badge-danger' : 'badge-ok' ?>">
                                    <?= ($invoice['status'] ?? 'active') === 'cancelled' ? 'Anulada' : 'Activa' ?>
                                </span>
                            </td>
                            <td data-label="Cobro"><span class="<?= e($paymentBadge) ?>"><?= e($paymentLabel) ?></span></td>
                            <td data-label="Moneda"><?= e($invoice['currency_code']) ?></td>
                            <td data-label="Tasa"><?= money($invoice['exchange_rate']) ?></td>
                            <td data-label="Subtotal"><?= money($invoice['subtotal_original']) ?> <?= e($invoice['currency_code']) ?></td>
                            <td data-label="IVA"><?= money($invoice['tax_original']) ?> <?= e($invoice['currency_code']) ?></td>
                            <td data-label="Total doc.">
                                <div class="money-stack">
                                    <strong><?= money($invoice['total_original']) ?> <?= e($invoice['currency_code']) ?></strong>
                                    <small>Total final facturado.</small>
                                </div>
                            </td>
                            <td data-label="Abonado">
                                <div class="money-stack">
                                    <strong><?= money($invoice['amount_paid_original'] ?? 0) ?> <?= e($invoice['currency_code']) ?></strong>
                                    <small><?= money($invoice['amount_paid_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></small>
                                </div>
                            </td>
                            <td data-label="Saldo">
                                <div class="money-stack">
                                    <strong><?= money($invoice['balance_original'] ?? 0) ?> <?= e($invoice['currency_code']) ?></strong>
                                    <small><?= money($invoice['balance_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></small>
                                </div>
                            </td>
                            <td data-label="Equiv. Bs">
                                <div class="money-stack">
                                    <strong><?= money(equivalent_in_bolivars($invoice['total_original'] ?? 0, $invoice['currency_code'] ?? '', $invoice['exchange_rate'] ?? 0)) ?> <?= e(secondary_currency()) ?></strong>
                                    <small>Equivalente en bolivares al cierre.</small>
                                </div>
                            </td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <!-- <a class="btn btn-sm btn-outline" href="/invoices/print/<?= $invoice['id'] ?>">Vista</a> -->
                                <a class="btn btn-sm btn-pdf" href="/invoices/pdf/<?= $invoice['id'] ?>" target="_blank" rel="noopener noreferrer">PDF</a>
                                <?php if ($canRegisterCollection && ($invoice['status'] ?? 'active') !== 'cancelled' && (float) ($invoice['balance_converted'] ?? 0) > 0.01): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        data-modal-open="invoice-payment-modal"
                                        data-document-payment-open
                                        data-document-payment-title="<?= e((string) ($invoice['invoice_number'] ?? '')) ?>"
                                        data-document-payment-action="<?= e(app_url('/invoices/payments/' . (int) $invoice['id'])) ?>"
                                        data-document-payment-total="<?= e((string) ($invoice['total_original'] ?? 0)) ?>"
                                        data-document-payment-paid="<?= e((string) ($invoice['amount_paid_original'] ?? 0)) ?>"
                                        data-document-payment-balance="<?= e((string) ($invoice['balance_original'] ?? 0)) ?>"
                                        data-document-payment-due-date="<?= e((string) ($invoice['due_date'] ?? $invoice['invoice_date'] ?? '')) ?>"
                                        data-document-payment-currency="<?= e((string) ($invoice['currency_code'] ?? secondary_currency())) ?>"
                                    >Cobrar</button>
                                <?php endif; ?>
                                <?php if (($invoice['status'] ?? 'active') !== 'cancelled'): ?>
                                    <form method="post" action="/invoices/cancel/<?= $invoice['id'] ?>" class="document-action-form" onsubmit="return confirm('Se anulara la factura y se devolvera el stock al inventario.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-danger-soft">Anular</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="17" class="empty-state">Aun no hay facturas registradas en el historial.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
</details>

<div class="modal-shell" data-modal="invoice-products-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="invoice-products-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Productos</span>
                <h3 id="invoice-products-title" data-document-preview-title>Factura</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <div class="document-products-summary">
            <div><span>Cliente</span><strong data-document-preview-client>Cargando...</strong></div>
            <div><span>Fecha</span><strong data-document-preview-date>--</strong></div>
            <div><span>Renglones</span><strong data-document-preview-lines>0</strong></div>
            <div><span>Total</span><strong data-document-preview-total>0,00</strong></div>
        </div>
        <div class="table-wrap document-products-table-wrap">
            <table class="table document-products-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody data-document-preview-body>
                    <tr>
                        <td colspan="4" class="empty-state">Cargando detalle...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="document-products-notes">
            <strong>Observaciones</strong>
            <p data-document-preview-notes>Sin observaciones registradas.</p>
        </div>
        <div class="actions-row">
            <button type="button" class="btn btn-outline" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<div class="modal-shell" data-modal="client-invoice-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="client-invoice-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Cliente</span>
                <h3 id="client-invoice-title">Agregar cliente</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/clients" class="form two-cols">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="/invoices">
            <label>Nombre<input name="name" required data-client-create-name></label>
            <label>Documento<input name="document" data-client-create-document></label>
            <label>Telefono<input name="phone"></label>
            <label>Email<input name="email"></label>
            <label class="col-span-2">Direccion<textarea name="address"></textarea></label>
            <button class="btn col-span-2">Guardar cliente</button>
        </form>
    </div>
</div>

<div
    class="modal-shell"
    data-modal="invoice-payment-modal"
    data-payment-currency-base="<?= e(base_currency()) ?>"
    data-payment-currency-secondary="<?= e(secondary_currency()) ?>"
    aria-hidden="true"
>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="invoice-payment-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Cobranza</span>
                <h3 id="invoice-payment-title" data-document-payment-modal-title>Registrar cobro</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="" class="form two-cols" data-ajax-form="1" data-document-payment-form>
            <?= csrf_field() ?>
            <div class="col-span-2 live-panel">
                <div><span>Total documento</span><strong data-document-payment-total>0,00</strong></div>
                <div><span>Abonado</span><strong data-document-payment-paid>0,00</strong></div>
                <div><span>Saldo pendiente</span><strong data-document-payment-balance>0,00</strong></div>
                <div><span>Vencimiento</span><strong data-document-payment-due-date>--</strong></div>
            </div>
            <label>Fecha de cobro
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Referencia
                <input name="reference" required placeholder="Transferencia, pago movil, efectivo...">
            </label>
            <label>Monto recibido
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
                <textarea name="notes" placeholder="Banco, persona que paga, observaciones del cobro inicial"></textarea>
            </label>
            <button class="btn col-span-2">Registrar cobro</button>
        </form>
    </div>
</div>
