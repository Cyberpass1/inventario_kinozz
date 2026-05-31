<?php
$clientHints = $clientHints ?? [];
$quoteValidDays = (int) ($quoteValidDays ?? invoice_due_days());
$defaultQuoteDate = date('Y-m-d');
$defaultValidUntil = document_due_date($defaultQuoteDate, $quoteValidDays);
$currentRole = (string) (auth_user()['role'] ?? '');
$canConvert = in_array($currentRole, ['administrator', 'vendor'], true);
$canFilterHistory = (bool) ($canFilterHistory ?? in_array($currentRole, ['administrator', 'general_consultant'], true));
$historyFilters = is_array($historyFilters ?? null) ? $historyFilters : [
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-d'),
    'q' => '',
];
$quotationStatusMeta = static function (string $status): array {
    return match ($status) {
        'converted_invoice' => ['badge badge-ok', 'Facturada'],
        'converted_delivery' => ['badge badge-ok', 'Entregada'],
        'converted_both' => ['badge badge-ok', 'Facturada y entregada'],
        'cancelled' => ['badge badge-danger', 'Anulada'],
        default => ['badge badge-neutral', 'Abierta'],
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

$renderQuotationLine = static function (string $namePrefix): void { ?>
    <div class="line-item-card" data-line-item>
        <div class="line-item-head">
            <div>
                <strong data-line-label>Renglon 1</strong>
                <small data-line-head-meta>Producto o servicio agregado a la cotizacion.</small>
            </div>
            <div class="line-item-actions">
                <button type="button" class="btn btn-outline btn-sm" data-line-toggle aria-expanded="false" title="Mostrar detalles">Detalles</button>
                <button type="button" class="btn btn-outline btn-sm" data-line-remove title="Quitar">Quitar</button>
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
    <form method="post" action="/quotations" class="pos-form" data-calc="delivery-note" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>" data-due-days="<?= e((string) $quoteValidDays) ?>" data-ajax-form="1">
        <?= csrf_field() ?>

        <header class="pos-topbar">
            <div class="pos-topbar-title">
                <h3>Cotizacion rapida</h3>
                <small>Cliente, items y total estimado en una sola mesa. No mueve inventario hasta convertirla.</small>
            </div>
            <div class="pos-topbar-actions">
                <button type="button" class="btn btn-outline btn-sm" data-modal-open="client-quotation-modal" title="Nuevo cliente">+ Cliente</button>
                <a class="btn btn-outline btn-sm" href="/clients" title="Gestionar clientes">Gestionar</a>
            </div>
        </header>

        <div class="pos-grid">
            <aside class="pos-col pos-col-left">
                <section class="pos-card">
                    <div class="pos-card-head">
                        <strong>Cliente</strong>
                        <span class="pos-hint">Ctrl + K</span>
                    </div>
                    <div class="client-search-shell pos-client" data-client-picker data-search-url="<?= e(app_url('/clients/search')) ?>" data-client-create-modal="client-quotation-modal">
                        <input type="hidden" name="client_id" value="">
                        <input type="text" class="pos-client-input" value="" placeholder="Nombre, cedula o documento..." autocomplete="off" data-client-search data-pos-client-input>
                        <div class="client-search-panel" data-client-panel hidden>
                            <div class="client-search-status" data-client-status>Escribe 2+ letras o numeros para buscar.</div>
                            <div class="client-search-results" data-client-results>
                                <?php foreach ($clientHints as $client): ?>
                                    <button type="button" class="client-option" data-client-option data-id="<?= (int) $client['id'] ?>" data-name="<?= e($client['name']) ?>" data-document="<?= e($client['document'] ?? '') ?>" data-phone="<?= e($client['phone'] ?? '') ?>" data-email="<?= e($client['email'] ?? '') ?>">
                                        <strong><?= e($client['name']) ?></strong>
                                        <span><?= e($client['document'] ?? 'Sin documento') ?></span>
                                        <small><?= (int) ($client['invoices_count'] ?? 0) ?> registros recientes</small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="pos-client-selected" data-client-selected data-empty-name="Sin cliente seleccionado" data-empty-meta="Busca y elige un cliente." data-pending-label="Pendiente" data-selected-label="Seleccionado">
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
                            <input name="quotation_number" value="<?= e($nextNumber) ?>" required>
                        </label>
                        <label>Fecha
                            <input type="date" name="quotation_date" value="<?= e($defaultQuoteDate) ?>" required>
                        </label>
                        <label>Valida hasta
                            <input type="text" value="<?= e($defaultValidUntil) ?>" readonly data-due-date-display>
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
                    <small class="pos-meta-hint">La cotizacion no reserva stock ni genera cuentas por cobrar.</small>
                </section>

                <details class="pos-card pos-notes">
                    <summary>Notas y condiciones</summary>
                    <div class="pos-notes-grid">
                        <label>Notas
                            <textarea name="notes" placeholder="Condiciones comerciales, tiempo de entrega o vigencia especial"></textarea>
                        </label>
                    </div>
                </details>
            </aside>

            <main class="pos-col pos-col-center">
                <section class="pos-card pos-items" data-line-items data-line-value-key="price" data-line-value-label="Precio">
                    <div class="pos-card-head pos-items-head">
                        <strong>Items cotizados</strong>
                        <span class="pos-hint"><kbd>/</kbd> enfoca buscador &middot; <kbd>Enter</kbd> agrega</span>
                    </div>

                    <div class="pos-search line-catalog-shell" data-line-catalog>
                        <input type="text" class="pos-search-input" value="" placeholder="Buscar por SKU o nombre y presiona Enter..." autocomplete="off" data-line-catalog-search>
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
                        <?php $renderQuotationLine('items[__INDEX__]'); ?>
                    </template>
                </section>
            </main>

            <aside class="pos-col pos-col-right">
                <section class="pos-card pos-total-card">
                    <div class="pos-total-hero">
                        <span>Total cotizado</span>
                        <strong data-delivery-original>0,00</strong>
                        <em data-delivery-total-currency><?= e(secondary_currency()) ?></em>
                    </div>
                    <div class="pos-total-grid">
                        <div><span>Renglones</span><strong data-line-count>0</strong></div>
                        <div><span>Unidades</span><strong data-line-quantity-total>0</strong></div>
                        <div class="pos-total-grid-wide"><span data-delivery-equivalent-label>Equiv. <?= e(secondary_currency()) ?></span><strong data-delivery-converted>0,00</strong></div>
                    </div>
                </section>

                <button class="btn pos-submit" type="submit" title="F2">
                    <span>Guardar cotizacion</span>
                    <kbd>F2</kbd>
                </button>
            </aside>
        </div>
    </form>
</section>

<details class="card pos-history quotation-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3><?= $canFilterHistory ? 'Historial de cotizaciones' : 'Ultimas 10 cotizaciones' ?> <span class="pos-history-count">(<?= count($quotations) ?>)</span></h3>
            <p><?= $canFilterHistory ? 'Consulta por periodos, busca una cotizacion puntual y conviertela cuando el cliente apruebe.' : 'Vista rapida de las cotizaciones recientes.' ?></p>
        </div>
    </summary>

    <?php if ($canFilterHistory): ?>
        <form method="get" action="/quotations" class="history-filters quotation-history-filters">
            <label>Desde <input type="date" name="date_from" value="<?= e((string) ($historyFilters['date_from'] ?? '')) ?>"></label>
            <label>Hasta <input type="date" name="date_to" value="<?= e((string) ($historyFilters['date_to'] ?? '')) ?>"></label>
            <label>Buscar <input name="q" value="<?= e((string) ($historyFilters['q'] ?? '')) ?>" placeholder="Numero, cliente o producto"></label>
            <button class="btn btn-outline btn-sm" type="submit">Filtrar</button>
        </form>
    <?php endif; ?>

    <div class="table-wrap table-wrap-scrollable table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Valida</th>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Moneda</th>
                    <th>Total</th>
                    <th>Equiv. Bs</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($quotations): ?>
                    <?php foreach ($quotations as $quotation): ?>
                        <?php [$statusBadge, $statusLabel] = $quotationStatusMeta((string) ($quotation['status'] ?? 'open')); ?>
                        <tr>
                            <td data-label="Fecha"><?= e($quotation['quotation_date'] ?? '') ?></td>
                            <td data-label="Valida"><?= e($quotation['valid_until'] ?? '') ?></td>
                            <td data-label="Numero"><?= e($quotation['quotation_number'] ?? '') ?></td>
                            <td data-label="Cliente"><?= e($quotation['client_name'] ?? '') ?></td>
                            <td data-label="Productos">
                                <div class="money-stack">
                                    <strong><?= e($quotation['products_summary'] ?? 'Sin detalle') ?></strong>
                                    <small><?= trim((string) ($quotation['notes'] ?? '')) !== '' ? e($quotation['notes']) : 'Sin observaciones registradas.' ?></small>
                                </div>
                            </td>
                            <td data-label="Cantidad"><span class="badge badge-ok"><?= money($quotation['total_quantity'] ?? 0) ?></span></td>
                            <td data-label="Estado"><span class="<?= e($statusBadge) ?>"><?= e($statusLabel) ?></span></td>
                            <td data-label="Moneda"><?= e($quotation['currency_code'] ?? '') ?></td>
                            <td data-label="Total">
                                <div class="money-stack">
                                    <strong><?= money($quotation['total_original'] ?? 0) ?> <?= e($quotation['currency_code'] ?? '') ?></strong>
                                    <small>Total de la cotizacion.</small>
                                </div>
                            </td>
                            <td data-label="Equiv. Bs">
                                <div class="money-stack">
                                    <strong><?= money($quotation['total_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></strong>
                                    <small>Equivalente al cierre.</small>
                                </div>
                            </td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <a class="btn btn-sm btn-pdf" href="/quotations/pdf/<?= (int) $quotation['id'] ?>" target="_blank" rel="noopener noreferrer">PDF</a>
                                <?php if ($canConvert && ($quotation['status'] ?? 'open') !== 'cancelled'): ?>
                                    <?php if ((int) ($quotation['delivery_note_id'] ?? 0) <= 0): ?>
                                        <a
                                            class="btn btn-sm btn-outline"
                                            href="/delivery-notes?from_quotation=<?= (int) $quotation['id'] ?>"
                                            data-swal-navigate
                                            data-swal-title="Generar nota"
                                            data-swal-text="Se llevaran los datos de esta cotizacion al modulo de notas para que la revises y la guardes."
                                            data-swal-confirm="Ir a nota"
                                        >A nota</a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline" href="/delivery-notes/pdf/<?= (int) $quotation['delivery_note_id'] ?>?currency=<?= e(secondary_currency()) ?>" target="_blank" rel="noopener noreferrer">Ver nota</a>
                                    <?php endif; ?>

                                    <?php if ((int) ($quotation['invoice_id'] ?? 0) <= 0): ?>
                                        <a
                                            class="btn btn-sm btn-outline"
                                            href="/invoices?from_quotation=<?= (int) $quotation['id'] ?>"
                                            data-swal-navigate
                                            data-swal-title="Generar factura"
                                            data-swal-text="Se llevaran los datos de esta cotizacion al modulo de facturas para que la revises y la guardes."
                                            data-swal-confirm="Ir a factura"
                                        >A factura</a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline" href="/invoices/pdf/<?= (int) $quotation['invoice_id'] ?>" target="_blank" rel="noopener noreferrer">Ver factura</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="empty-state">Todavia no hay cotizaciones registradas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</details>

<div class="modal-shell" data-modal="client-quotation-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="client-quotation-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Cliente</span>
                <h3 id="client-quotation-title">Agregar cliente</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/quotations/clients" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre <input name="name" required></label>
            <label>Documento <input name="document"></label>
            <label>Telefono <input name="phone"></label>
            <label>Email <input type="email" name="email"></label>
            <label class="col-span-2">Direccion <textarea name="address"></textarea></label>
            <button class="btn col-span-2" type="submit">Guardar cliente</button>
        </form>
    </div>
</div>
