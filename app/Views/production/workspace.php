<?php
$blankRecipeRows = 6;
$statusMeta = static function (array $row): array {
    return ($row['status'] ?? 'active') === 'cancelled'
        ? ['badge badge-danger', 'Anulada']
        : ['badge badge-ok', 'Activa'];
};
$productOptionsMarkup = (static function (array $products): string {
    ob_start();
    foreach ($products as $product): ?>
        <option
            value="<?= $product['id'] ?>"
            data-sku="<?= e((string) ($product['sku'] ?? '')) ?>"
            data-stock="<?= e((string) ($product['stock'] ?? 0)) ?>"
            data-price="0"
            data-currency="<?= e((string) ($product['currency_code'] ?? base_currency())) ?>"
            data-unit-label="<?= e(product_unit_label($product)) ?>"
            data-product-type="<?= e((string) ($product['product_type'] ?? 'merchandise')) ?>"
            data-type-label="<?= e(product_type_label($product['product_type'] ?? 'merchandise')) ?>"
            data-track-stock="<?= product_tracks_inventory($product) ? '1' : '0' ?>"
        >
            <?= e(trim(((string) ($product['sku'] ?? '')) . ' ' . ($product['name'] ?? ''))) ?>
        </option>
    <?php endforeach;

    return trim((string) ob_get_clean());
})($products);

$renderProductionLine = static function (string $namePrefix): void { ?>
    <div class="line-item-card" data-line-item>
        <div class="line-item-head">
            <div>
                <strong data-line-label>Renglon 1</strong>
                <small data-line-head-meta>Producto agregado al lote.</small>
            </div>
            <div class="line-item-actions">
                <button type="button" class="btn btn-outline btn-sm" data-line-toggle aria-expanded="false">Detalles</button>
                <button type="button" class="btn btn-outline btn-sm" data-line-remove>Quitar</button>
            </div>
        </div>
        <div class="line-item-grid" hidden>
            <div class="line-item-product">
                <span class="line-item-caption">Producto terminado</span>
                <div class="line-item-identity">
                    <strong data-line-product-name>Sin producto</strong>
                    <small data-line-product-meta>Selecciona un producto fabricable.</small>
                </div>
                <input type="hidden" name="<?= e($namePrefix) ?>[product_id]" value="" data-line-product-id>
            </div>
            <label><span data-line-qty-label>Cantidad a producir</span>
                <input type="number" step="0.01" min="0.01" inputmode="decimal" name="<?= e($namePrefix) ?>[quantity_produced]" value="1" required data-line-qty-input data-manual-decimal-input data-quick-step="1">
            </label>
            <div class="line-item-metrics">
                <div><span>Stock actual</span><strong data-line-stock>0,00</strong></div>
                <div><span>Lote</span><strong data-line-subtotal>1,00</strong></div>
            </div>
        </div>
    </div>
<?php };
?>

<section class="pos-workspace" data-pos-workspace>
    <form method="post" action="/production" class="pos-form" data-ajax-form="0">
        <?= csrf_field() ?>

        <header class="pos-topbar">
            <div class="pos-topbar-title">
                <h3>Registrar produccion</h3>
                <small>Busca el producto fabricado, agrega la cantidad y guarda &middot; <kbd>F2</kbd> guardar &middot; <kbd>/</kbd> buscar item</small>
            </div>
            <div class="pos-topbar-actions">
                <span class="pos-topbar-stat"><strong><?= (int) ($summary['products'] ?? 0) ?></strong> fabricables</span>
                <span class="pos-topbar-stat"><strong><?= (int) ($summary['recipes'] ?? 0) ?></strong> con receta</span>
                <span class="pos-topbar-stat"><strong><?= (int) ($summary['orders'] ?? 0) ?></strong> ordenes</span>
            </div>
        </header>

        <div class="pos-grid pos-grid-production">
            <!-- Columna principal: buscador + lista de items -->
            <main class="pos-col pos-col-center">
                <section class="pos-card pos-items" data-line-items data-line-value-key="none" data-line-value-label="Produccion">
                    <div class="pos-card-head pos-items-head">
                        <strong>Productos del lote</strong>
                        <span class="pos-hint"><kbd>/</kbd> enfoca buscador &middot; <kbd>Enter</kbd> agrega</span>
                    </div>

                    <div class="pos-search line-catalog-shell" data-line-catalog>
                        <input
                            type="text"
                            class="pos-search-input"
                            value=""
                            placeholder="Buscar producto fabricable por SKU o nombre..."
                            autocomplete="off"
                            data-line-catalog-search
                        >
                        <button type="button" class="btn btn-outline btn-sm pos-search-add" data-line-item-add title="Agregar renglon vacio">+ Renglon</button>
                        <small class="line-catalog-status pos-search-status" data-line-catalog-status>Escribe al menos 2 caracteres para buscar.</small>
                        <div class="line-catalog-results pos-search-results" data-line-catalog-results></div>
                        <select data-line-product-catalog hidden>
                            <option value="" data-sku="" data-stock="0" data-price="0" data-unit-label="und" data-type-label="" data-track-stock="1" selected></option>
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
                        <strong>Sin productos en el lote</strong>
                        <span>Usa el buscador o pulsa <kbd>/</kbd> para empezar.</span>
                    </div>
                    <template data-line-item-template>
                        <?php $renderProductionLine('items[__INDEX__]'); ?>
                    </template>
                </section>
            </main>

            <!-- Columna derecha (sticky): meta + submit -->
            <aside class="pos-col pos-col-right">
                <section class="pos-card pos-total-card pos-production-summary">
                    <div class="pos-total-hero">
                        <span>Lote de produccion</span>
                        <strong data-production-line-count>0</strong>
                        <em>productos</em>
                    </div>
                    <div class="pos-total-grid">
                        <div class="pos-total-grid-wide"><span>Cada producto consume su receta y suma al inventario terminado.</span></div>
                    </div>
                </section>

                <section class="pos-card">
                    <div class="pos-card-head"><strong>Datos del envio</strong></div>
                    <div class="pos-meta-grid">
                        <label class="pos-meta-span">Fecha
                            <input type="date" name="production_date" value="<?= e(date('Y-m-d')) ?>" required>
                        </label>
                        <label class="pos-meta-span">Referencia
                            <input name="reference" placeholder="Opcional, se autogenera si la dejas vacia">
                        </label>
                        <label class="pos-meta-span">Notas
                            <textarea name="notes" placeholder="Lote, observaciones, merma o detalle operativo"></textarea>
                        </label>
                    </div>
                </section>

                <button class="btn pos-submit" type="submit" title="F2">
                    <span>Registrar produccion</span>
                    <kbd>F2</kbd>
                </button>
            </aside>
        </div>
    </form>
</section>

<details class="card pos-history" open>
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3>Recetas activas <span class="pos-history-count">(<?= count($recipesSummary) ?>)</span></h3>
            <p>Productos manufacturables con buscador por SKU y nombre. Edita la receta para definir insumos por unidad.</p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>
    <div class="pos-history-body">
        <div class="catalog-toolbar">
            <label class="catalog-search">
                <span>Buscar producto</span>
                <input
                    type="search"
                    placeholder="SKU o nombre..."
                    autocomplete="off"
                    data-production-recipes-search-input
                    data-table-filter-input
                    data-table-filter-target="production-recipes"
                >
            </label>
            <div class="catalog-results" data-production-recipes-count data-table-filter-count data-table-filter-target="production-recipes" data-table-filter-label="productos">
                <?= count($recipesSummary) ?> productos
            </div>
        </div>
        <div class="table-wrap table-wrap-scrollable table-wrap-mobile-slider production-recipes-scroll" data-table-filter-container="production-recipes">
            <table class="table mobile-cards">
                <thead><tr><th>SKU</th><th>Producto</th><th>Categoria</th><th>Receta</th><th></th></tr></thead>
                <tbody data-production-recipes-rows data-table-filter-rows="production-recipes">
                    <?php if ($recipesSummary): ?>
                        <?php foreach ($recipesSummary as $recipe): ?>
                            <tr data-filter-search="<?= e(strtolower(trim(((string) ($recipe['product_sku'] ?? '')) . ' ' . ((string) ($recipe['product_name'] ?? ''))))) ?>">
                                <td data-label="SKU"><?= e((string) ($recipe['product_sku'] ?? '')) ?></td>
                                <td data-label="Producto">
                                    <strong><?= e((string) ($recipe['product_name'] ?? '')) ?></strong>
                                    <small><?= e(product_unit_label((string) ($recipe['product_unit_label'] ?? ''), 'finished_good')) ?></small>
                                </td>
                                <td data-label="Categoria"><?= e((string) ($recipe['category_name'] ?? 'Sin categoria')) ?></td>
                                <td data-label="Receta">
                                    <?php if ((int) ($recipe['components_count'] ?? 0) > 0): ?>
                                        <div class="money-stack">
                                            <strong><?= (int) ($recipe['components_count'] ?? 0) ?> insumos</strong>
                                            <small><?= e((string) ($recipe['recipe_summary'] ?? '')) ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Sin receta</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Acciones" class="actions-row document-actions">
                                    <button type="button" class="btn btn-sm btn-outline" data-modal-open="recipe-edit-<?= (int) $recipe['product_id'] ?>">Editar receta</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-state">No hay productos fabricados activos todavia.</td></tr>
                    <?php endif; ?>
                    <tr class="table-empty-state" data-production-recipes-empty data-table-filter-empty="production-recipes" hidden>
                        <td colspan="5">No hay productos que coincidan con la busqueda.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</details>

<details class="card pos-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3>Historial de produccion <span class="pos-history-count">(<?= count($history) ?>)</span></h3>
            <p>Cantidades fabricadas, costo del lote, componentes consumidos y opcion para anular un pedido.</p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>
    <div class="pos-history-body">
        <div class="table-wrap table-wrap-mobile-slider">
            <table class="table mobile-cards">
                <thead><tr><th>Fecha</th><th>Referencia</th><th>Producto</th><th>Cantidad</th><th>Estado</th><th>Costo/und</th><th>Costo total</th><th>Componentes</th><th>Notas</th><th></th></tr></thead>
                <tbody data-table-pagination data-table-pagination-size="15">
                    <?php if ($history): ?>
                        <?php foreach ($history as $row): ?>
                            <?php [$statusClass, $statusLabel] = $statusMeta($row); ?>
                            <tr>
                                <td data-label="Fecha"><?= e($row['production_date'] ?? '') ?></td>
                                <td data-label="Referencia"><?= e($row['reference'] ?? '') ?></td>
                                <td data-label="Producto"><?= e(trim(((string) ($row['product_sku'] ?? '')) . ' ' . ($row['product_name'] ?? ''))) ?></td>
                                <td data-label="Cantidad"><?= money($row['quantity_produced'] ?? 0) ?> <?= e(product_unit_label($row['product_unit_label'] ?? '', 'finished_good')) ?></td>
                                <td data-label="Estado"><span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                <td data-label="Costo/und"><?= money($row['unit_cost'] ?? 0) ?></td>
                                <td data-label="Costo total"><?= money($row['total_cost'] ?? 0) ?></td>
                                <td data-label="Componentes"><?= e($row['components_summary'] ?? 'Sin detalle') ?></td>
                                <td data-label="Notas">
                                    <div class="money-stack">
                                        <strong><?= trim((string) ($row['notes'] ?? '')) !== '' ? e($row['notes']) : 'Sin notas' ?></strong>
                                        <?php if (($row['status'] ?? 'active') === 'cancelled' && trim((string) ($row['cancellation_reason'] ?? '')) !== ''): ?>
                                            <small>Motivo: <?= e((string) $row['cancellation_reason']) ?></small>
                                        <?php elseif (($row['status'] ?? 'active') === 'cancelled'): ?>
                                            <small>Pedido anulado.</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Acciones" class="actions-row document-actions">
                                    <?php if (($row['status'] ?? 'active') !== 'cancelled'): ?>
                                        <button type="button" class="btn btn-sm btn-danger-soft" data-modal-open="production-cancel-<?= (int) $row['id'] ?>">Anular</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="empty-state">Todavia no hay ordenes de produccion registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

<?php foreach ($history as $row): ?>
    <?php if (($row['status'] ?? 'active') === 'cancelled') { continue; } ?>
    <div class="modal-shell" data-modal="production-cancel-<?= (int) $row['id'] ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="production-cancel-title-<?= (int) $row['id'] ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Anulacion</span>
                    <h3 id="production-cancel-title-<?= (int) $row['id'] ?>">Anular pedido <?= e((string) ($row['reference'] ?? '')) ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>
            <form method="post" action="/production/cancel/<?= (int) $row['id'] ?>" class="form">
                <?= csrf_field() ?>
                <div class="empty-state">
                    Se sacara del inventario el producto terminado y se devolveran los insumos consumidos por este pedido. Si ya no queda stock suficiente del terminado, la anulacion no se completara.
                </div>
                <label>Motivo
                    <textarea name="reason" placeholder="Opcional, por ejemplo error de registro o pedido duplicado"></textarea>
                </label>
                <button class="btn">Confirmar anulacion</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($products as $product): ?>
    <?php
    $productId = (int) ($product['id'] ?? 0);
    $recipeItems = array_values(array_filter($recipes[$productId] ?? [], 'is_array'));
    $rowsToRender = max($blankRecipeRows, count($recipeItems) + 2);
    ?>
    <div class="modal-shell" data-modal="recipe-edit-<?= $productId ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="recipe-edit-title-<?= $productId ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Receta</span>
                    <h3 id="recipe-edit-title-<?= $productId ?>">Editar receta de <?= e($product['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="/production/recipes/<?= $productId ?>" class="form">
                <?= csrf_field() ?>
                <div class="table-wrap table-wrap-mobile-slider">
                    <table class="table mobile-cards">
                        <thead><tr><th>Insumo</th><th>Cantidad por unidad</th><th>Notas</th></tr></thead>
                        <tbody>
                            <?php for ($index = 0; $index < $rowsToRender; $index++): ?>
                                <?php $item = $recipeItems[$index] ?? []; ?>
                                <tr>
                                    <td data-label="Insumo">
                                        <select name="items[<?= $index ?>][component_product_id]">
                                            <option value="">Selecciona</option>
                                            <?php foreach ($components as $component): ?>
                                                <?php if ((int) ($component['id'] ?? 0) === $productId) { continue; } ?>
                                                <option value="<?= (int) $component['id'] ?>" <?= (int) ($item['component_product_id'] ?? 0) === (int) ($component['id'] ?? 0) ? 'selected' : '' ?>>
                                                    <?= e(trim(((string) ($component['sku'] ?? '')) . ' ' . ($component['name'] ?? ''))) ?> - <?= e(product_type_label($component['product_type'] ?? 'merchandise')) ?> - <?= e(product_unit_label($component)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td data-label="Cantidad">
                                        <input type="number" step="0.0001" min="0" inputmode="decimal" name="items[<?= $index ?>][quantity]" value="<?= e((string) ($item['quantity'] ?? '')) ?>" placeholder="Ej. 1.20" data-manual-decimal-input>
                                    </td>
                                    <td data-label="Notas">
                                        <input name="items[<?= $index ?>][notes]" value="<?= e((string) ($item['notes'] ?? '')) ?>" placeholder="Opcional">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="empty-state">
                    La cantidad debe estar en la misma unidad base del insumo. Ejemplo: si la tela esta en `m`, una franela puede llevar `1.20 m`; si el hilo esta en `kg`, puede llevar `0.05 kg`.
                </div>
                <button class="btn">Guardar receta</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script>
    (function () {
        const bindProductionRecipesFilter = () => {
            const input = document.querySelector("[data-production-recipes-search-input]");
            const rows = Array.from(document.querySelectorAll("[data-production-recipes-rows] tr[data-filter-search]"));
            const emptyState = document.querySelector("[data-production-recipes-empty]");
            const countNode = document.querySelector("[data-production-recipes-count]");

            if (!input || rows.length === 0) {
                return;
            }

            const total = rows.length;

            const sync = () => {
                const term = String(input.value || "").trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const haystack = String(row.dataset.filterSearch || "").toLowerCase();
                    const matches = term === "" || haystack.includes(term);
                    row.classList.toggle("is-filter-hidden", !matches);

                    if (matches) {
                        visible += 1;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = visible !== 0;
                }

                if (countNode) {
                    countNode.textContent = term === ""
                        ? `${total} productos`
                        : `${visible} de ${total} productos`;
                }
            };

            input.addEventListener("input", sync);
            input.addEventListener("change", sync);
            sync();
        };

        const bindProductionCounter = () => {
            const list = document.querySelector("[data-pos-workspace] [data-line-items-list]");
            const counter = document.querySelector("[data-production-line-count]");
            if (!list || !counter) return;
            const sync = () => {
                counter.textContent = String(list.querySelectorAll("[data-line-item]").length);
            };
            sync();
            new MutationObserver(sync).observe(list, { childList: true });
        };

        bindProductionRecipesFilter();
        bindProductionCounter();
    })();
</script>
