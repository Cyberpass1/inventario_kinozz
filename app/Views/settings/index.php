<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Motor de tasa, IVA y vencimientos</h3>
            <small>Define tasa operativa, IVA y cuantos dias tendran facturas y compras antes de vencer, sin pedir esa fecha en cada documento.</small>
        </div>
        <div class="inventory-topbar-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(app_url('/settings/users')) ?>">Usuarios</a>
            <a class="btn btn-outline btn-sm" href="<?= e(app_url('/profile')) ?>">Mi perfil</a>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi inventory-kpi-money"><span>Tasa vigente</span><strong><?= money($rateMeta['rate'] ?? default_exchange_rate()) ?></strong><em>VES / USD</em></div>
        <div class="inventory-kpi"><span>IVA por defecto</span><strong><?= e((string) ($settings['tax_percent'] ?? tax_percent())) ?>%</strong></div>
        <div class="inventory-kpi"><span>Factura vence en</span><strong><?= (int) ($settings['invoice_due_days'] ?? invoice_due_days()) ?> dias</strong></div>
        <div class="inventory-kpi"><span>Compra vence en</span><strong><?= (int) ($settings['purchase_due_days'] ?? purchase_due_days()) ?> dias</strong></div>
    </div>
</section>

<section class="grid two">
    <article class="card card-feature">
        <header class="section-head">
            <div>
                <h3>Fuente de tasa</h3>
                <p>Al guardar, el sistema sincroniza la tasa del dia y actualiza el anclaje operativo sin requerir formularios duplicados.</p>
            </div>
        </header>

        <form
            method="post"
            action="<?= e(app_url('/settings')) ?>"
            class="form"
            data-settings-rate-sync="1"
            data-rate-url="<?= e(app_url('/rates/by-date')) ?>"
        >
            <?= csrf_field() ?>
            <label>Modo de tasa
                <select name="exchange_rate_mode" data-settings-mode>
                    <option value="bcv_usd" <?= ($settings['exchange_rate_mode'] ?? 'bcv_usd') === 'bcv_usd' ? 'selected' : '' ?>>BCV dolar</option>
                    <option value="bcv_eur" <?= ($settings['exchange_rate_mode'] ?? '') === 'bcv_eur' ? 'selected' : '' ?>>BCV euro</option>
                    <option value="custom" <?= ($settings['exchange_rate_mode'] ?? '') === 'custom' ? 'selected' : '' ?>>Tasa personalizada</option>
                </select>
            </label>
            <label data-settings-custom-currency-wrap>Moneda de referencia personalizada
                <select name="exchange_rate_custom_currency" data-settings-custom-currency>
                    <option value="USD" <?= ($settings['exchange_rate_custom_currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                    <option value="EUR" <?= ($settings['exchange_rate_custom_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                </select>
            </label>
            <label>
                <span data-settings-rate-label>Tasa</span>
                <input
                    type="number"
                    step="0.0001"
                    name="exchange_rate_custom"
                    value="<?= e((string) ($settings['exchange_rate_custom'] ?? $settings['default_exchange_rate'] ?? default_exchange_rate())) ?>"
                    data-settings-rate-input
                >
            </label>
            <small data-settings-rate-status>La tasa se actualiza segun el modo de tasa que selecciones.</small>
            <label>IVA por defecto (%)
                <input type="number" step="0.01" name="tax_percent" value="<?= e((string) ($settings['tax_percent'] ?? tax_percent())) ?>">
            </label>
            <label>Dias de vencimiento para facturas
                <input type="number" min="0" step="1" name="invoice_due_days" value="<?= e((string) ($settings['invoice_due_days'] ?? invoice_due_days())) ?>">
            </label>
            <label>Dias de vencimiento para compras
                <input type="number" min="0" step="1" name="purchase_due_days" value="<?= e((string) ($settings['purchase_due_days'] ?? purchase_due_days())) ?>">
            </label>
            <label class="settings-toggle">
                <input type="checkbox" name="production_enabled" value="1" <?= production_enabled() ? 'checked' : '' ?>>
                <span>
                    <strong>Modulo de produccion</strong>
                    <small>Activa este modulo solo si fabricas productos. Cuando esta desactivado, el menu lateral oculta "Produccion" y la ruta queda cerrada.</small>
                </span>
            </label>
            <button class="btn">Guardar configuracion</button>
        </form>
    </article>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Estado de sincronizacion</h3>
                <p>Vista rapida de la tasa activa y su origen real.</p>
            </div>
        </header>

        <div class="stack-list">
            <div class="stack-row">
                <div>
                    <strong>Fuente</strong>
                    <small><?= e($rateMeta['source'] ?? 'Sistema') ?></small>
                </div>
                <span class="badge badge-neutral"><?= e(strtoupper((string) ($rateMeta['currency_from'] ?? 'USD'))) ?></span>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Fecha activa</strong>
                    <small>Ultimo valor aplicado al sistema.</small>
                </div>
                <span class="badge badge-ok"><?= e($rateMeta['date'] ?? date('Y-m-d')) ?></span>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Tasa actual</strong>
                    <small>VES por USD aplicado por el sistema</small>
                </div>
                <span class="badge badge-ok"><?= money($rateMeta['rate'] ?? default_exchange_rate()) ?></span>
            </div>
            <?php if (!empty($rateMeta['errors'])): ?>
                <div class="stack-row">
                    <div>
                        <strong>Observacion</strong>
                        <small><?= e((string) $rateMeta['errors'][array_key_last($rateMeta['errors'])]) ?></small>
                    </div>
                    <span class="badge badge-warn">Fallback</span>
                </div>
            <?php endif; ?>
        </div>

        <p style="margin-top:1rem;color:#64748b;">Guardar configuracion tambien sincroniza la tasa activa del sistema. No hace falta un segundo formulario.</p>
    </article>
</section>

<article class="card inventory-catalog-card">
    <header class="section-head">
        <div>
            <h3>Historial de tasas</h3>
            <p>Registro historico utilizado por el sistema para mantener consistencia por fecha.</p>
        </div>
    </header>

    <div class="inventory-catalog-toolbar">
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Fecha, moneda origen o destino..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="rates-history"
            >
        </label>
        <div class="inventory-filter-meta">
            <strong data-table-filter-count data-table-filter-target="rates-history" data-table-filter-label="tasas"><?= count($rates) ?> tasas</strong>
            <small>registradas</small>
        </div>
    </div>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Desde</th>
                    <th>Hacia</th>
                    <th>Tasa</th>
                </tr>
            </thead>
            <tbody data-table-filter-rows="rates-history" data-table-pagination data-table-pagination-size="15">
                <?php if ($rates !== []): ?>
                    <?php foreach ($rates as $rate): ?>
                        <?php
                        $haystack = strtolower(trim(
                            ((string) ($rate['rate_date'] ?? '')) . ' '
                            . ((string) ($rate['currency_from'] ?? '')) . ' '
                            . ((string) ($rate['currency_to'] ?? ''))
                        ));
                        ?>
                        <tr data-filter-search="<?= e($haystack) ?>">
                            <td data-label="Fecha"><?= e($rate['rate_date']) ?></td>
                            <td data-label="Desde"><?= e($rate['currency_from']) ?></td>
                            <td data-label="Hacia"><?= e($rate['currency_to']) ?></td>
                            <td data-label="Tasa"><?= money($rate['rate']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty-state">Aun no hay tasas registradas en el historial.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="empty-state" data-table-filter-empty="rates-history" hidden>No hay tasas que coincidan con la busqueda.</div>
    </div>
</article>

