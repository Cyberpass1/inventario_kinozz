<section class="page-header">
    <div>
        <span class="eyebrow">Inventario</span>
        <h2>Stock directo por producto</h2>
        <p>Sin almacenes intermedios: el stock vive en el producto y los movimientos quedan en historial.</p>
    </div>
    <div class="header-summary">
        <div><span>Productos</span><strong><?= (int) $summary['products'] ?></strong></div>
        <div><span>Servicios</span><strong><?= (int) ($summary['services'] ?? 0) ?></strong></div>
        <div><span>Criticos</span><strong><?= (int) $summary['low_stock'] ?></strong></div>
        <div>
            <span>Valor USD</span>
            <strong><?= money($summary['inventory_value_usd'] ?? 0) ?></strong>
            <small><?= money($summary['inventory_value_bs'] ?? 0) ?> <?= e(secondary_currency()) ?></small>
        </div>
    </div>
</section>

<datalist id="product-unit-options">
    <?php foreach (product_unit_suggestions() as $unit): ?>
        <option value="<?= e($unit) ?>"></option>
    <?php endforeach; ?>
</datalist>

<article class="card card-feature">
    <header class="section-head">
        <div>
            <h3>Nuevo producto</h3>
            <p>El formulario principal del modulo: simple para productos normales y mas especifico solo cuando hace falta.</p>
        </div>
        <div class="actions-row inventory-header-actions">
            <button type="button" class="btn btn-secondary" data-modal-open="inventory-product-variants" data-product-variants-trigger>Crear variantes</button>
            <button type="button" class="btn btn-outline btn-soft-neutral" data-modal-open="inventory-category-create">Nueva categoria</button>
        </div>
    </header>
    <form method="post" action="/inventory/products" class="form two-cols" data-calc="product" data-product-form>
            <?= csrf_field() ?>
            <label>Categoria<select name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Tipo
                <select name="product_type" required>
                    <?php foreach (product_type_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === 'merchandise' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>SKU<input name="sku" required></label>
            <label>Nombre<input name="name" required></label>
            <label data-product-unit-wrap style="display:none;"><span data-product-unit-label>Unidad base</span><input name="unit_label" value="und" list="product-unit-options" maxlength="20" placeholder="Ej. und, m, kg"></label>
            <label data-product-stock-min-wrap><span data-product-stock-min-label>Stock minimo</span><input type="number" step="1" min="0" name="stock_min" value="0"></label>
            <label><span data-product-cost-label>Costo unitario</span><input type="number" step="0.01" name="cost" value="0" data-cost-input></label>
            <label><span data-product-price-label>Precio unitario</span><input type="number" step="0.01" name="price" value="0" data-price-input></label>
            <label>Moneda<select name="currency_code"><option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option><option value="<?= e(secondary_currency()) ?>"><?= e(secondary_currency()) ?></option></select></label>
            <label data-product-initial-stock-wrap><span data-product-initial-stock-label>Stock inicial</span><input type="number" step="1" min="0" name="initial_stock" value="0" data-stock-input></label>
            <label class="col-span-2">Descripcion<textarea name="description"></textarea></label>
            <div class="col-span-2 empty-state" data-product-type-note>
                Producto normal: flujo simple de compra y venta. Si luego quieres fabricarlo, puedes agregar receta en Produccion sin complicar este formulario.
            </div>
            <div class="col-span-2 live-panel">
                <div><span>Margen</span><strong data-product-margin>0,00</strong></div>
                <div><span>Inversion inicial</span><strong data-product-investment>0,00</strong></div>
            </div>
            <button class="btn col-span-2">Guardar producto</button>
    </form>
</article>

<div class="modal-shell" data-modal="inventory-category-create" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="inventory-category-create-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Categoria</span>
                <h3 id="inventory-category-create-title">Crear categoria</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/inventory/categories" class="form">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <label>Descripcion<input name="description"></label>
            <button class="btn">Guardar categoria</button>
        </form>
    </div>
</div>

<div class="modal-shell" data-modal="inventory-product-duplicate" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="inventory-product-duplicate-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Catalogo</span>
                <h3 id="inventory-product-duplicate-title">Duplicar producto</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/inventory/products/duplicate" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="source_product_id" data-duplicate-source-id>
            <label>Producto base<input value="" data-duplicate-source-name disabled></label>
            <label>SKU sugerido<input name="sku" data-duplicate-sku placeholder="Ej. FRAN-BLAN-S-COPIA"></label>
            <label>Nuevo nombre<input name="name" data-duplicate-name required></label>
            <label>Carga inicial opcional<input type="number" step="1" name="initial_stock" value="0" min="0"></label>
            <div class="empty-state">
                Duplica categoria, tipo, costo, precio y descripcion del producto base. Solo ajustas el nombre, el SKU y, si quieres, una carga inicial.
            </div>
            <button class="btn">Crear copia</button>
        </form>
    </div>
</div>

<div class="modal-shell" data-modal="inventory-product-variants" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="inventory-product-variants-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Catalogo</span>
                <h3 id="inventory-product-variants-title">Generar variantes</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/inventory/products/variants" class="form two-cols" data-product-form>
            <?= csrf_field() ?>
            <label>Categoria<select name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Tipo
                <select name="product_type" required>
                    <?php foreach (product_type_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === 'merchandise' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nombre base<input name="base_name" data-variants-base-name required placeholder="Ej. Franela"></label>
            <label>Prefijo SKU<input name="sku_prefix" data-variants-sku-prefix required placeholder="Ej. FRAN"></label>
            <label class="col-span-2">Colores<textarea name="variant_colors" data-variants-colors placeholder="Blanca, Beige, Negra"></textarea></label>
            <label class="col-span-2">Tallas<textarea name="variant_sizes" data-variants-sizes placeholder="S, M, L, XL"></textarea></label>
            <label data-product-unit-wrap style="display:none;"><span data-product-unit-label>Unidad base</span><input name="unit_label" value="und" list="product-unit-options" maxlength="20" placeholder="Ej. und, m, kg"></label>
            <label data-product-stock-min-wrap><span data-product-stock-min-label>Stock minimo</span><input type="number" step="1" min="0" name="stock_min" value="0"></label>
            <label><span data-product-cost-label>Costo unitario</span><input type="number" step="0.01" name="cost" value="0"></label>
            <label><span data-product-price-label>Precio unitario</span><input type="number" step="0.01" name="price" value="0"></label>
            <label>Moneda<select name="currency_code"><option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option><option value="<?= e(secondary_currency()) ?>"><?= e(secondary_currency()) ?></option></select></label>
            <label data-product-initial-stock-wrap><span data-product-initial-stock-label>Stock inicial por variante</span><input type="number" step="1" min="0" name="initial_stock" value="0"></label>
            <label class="col-span-2">Descripcion<textarea name="description" data-variants-description placeholder="Descripcion comun para todas las variantes"></textarea></label>
            <div class="col-span-2 live-panel">
                <div><span>Combinaciones</span><strong data-variant-preview-count>0</strong></div>
                <div><span>Vista previa</span><strong data-variant-preview-status>Esperando datos</strong></div>
            </div>
            <div class="col-span-2 empty-state" data-variant-preview-empty>
                Escribe un nombre base y agrega colores o tallas para ver aqui mismo como van a salir las variantes antes de crearlas.
            </div>
            <div class="col-span-2 table-wrap table-wrap-mobile-slider" data-variant-preview-wrap hidden>
                <table class="table mobile-cards">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre estimado</th>
                            <th>SKU estimado</th>
                        </tr>
                    </thead>
                    <tbody data-variant-preview-list></tbody>
                </table>
                <div class="empty-state" data-variant-preview-more hidden></div>
            </div>
            <div class="col-span-2 empty-state" data-product-type-note>
                Genera varias combinaciones de una vez. Puedes separar colores y tallas con comas o una linea por valor.
            </div>
            <button class="btn col-span-2">Crear variantes</button>
        </form>
    </div>
</div>

<details class="card collapsible-card">
    <summary class="collapse-summary">
        <div>
            <h3>Categorias</h3>
            <p>Gestiona categorias sin quitarle protagonismo al catalogo de productos.</p>
        </div>
        <span class="btn btn-outline btn-sm collapse-summary-action">Mostrar</span>
    </summary>
    <div class="collapse-body">
        <form method="post" action="/inventory/categories" class="form compact-form inline-form">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <label>Descripcion<input name="description"></label>
            <button class="btn">Guardar categoria</button>
        </form>
        <div class="table-wrap table-wrap-mobile-slider">
            <table class="table mobile-cards">
                <thead><tr><th>Categoria</th><th>Descripcion</th><th>Productos</th><th></th></tr></thead>
                <tbody>
                    <?php if ($categories): ?>
                        <?php foreach ($categories as $category): ?>
                            <?php $usageCount = (int) ($categoryUsage[(int) $category['id']] ?? 0); ?>
                            <tr>
                                <td data-label="Categoria"><?= e($category['name'] ?? '') ?></td>
                                <td data-label="Descripcion"><?= trim((string) ($category['description'] ?? '')) !== '' ? e($category['description']) : 'Sin descripcion' ?></td>
                                <td data-label="Productos"><span class="badge badge-neutral"><?= $usageCount ?></span></td>
                                <td data-label="Acciones" class="actions-row">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        data-modal-open="inventory-category-edit"
                                        data-category-edit-trigger
                                        data-category-edit-action="<?= e(app_url('/inventory/categories/' . (int) $category['id'])) ?>"
                                        data-category-name="<?= e((string) ($category['name'] ?? '')) ?>"
                                        data-category-description="<?= e((string) ($category['description'] ?? '')) ?>"
                                    >Editar</button>
                                    <form method="post" action="/inventory/categories/<?= (int) $category['id'] ?>/delete" onsubmit="return confirm('Se eliminara la categoria y los productos quedaran sin categoria.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-state">Todavia no hay categorias creadas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Catalogo</h3>
            <p>Filtra por nombre o SKU y navega el listado con scroll interno.</p>
        </div>
    </header>
    <div class="catalog-toolbar">
        <label class="catalog-search">
            <span>Buscar producto o SKU</span>
            <input
                type="search"
                placeholder="Ej. tornillo, sku-001..."
                autocomplete="off"
                data-inventory-catalog-search-input
                data-table-filter-input
                data-table-filter-target="inventory-catalog"
            >
        </label>
        <div class="catalog-results" data-inventory-catalog-count data-table-filter-count data-table-filter-target="inventory-catalog" data-table-filter-label="productos">
            <?= count($products) ?> productos
        </div>
    </div>
    <div class="table-wrap table-wrap-scrollable table-wrap-mobile-slider" data-table-filter-container="inventory-catalog">
        <table class="table mobile-cards">
            <thead><tr><th>SKU</th><th>Producto</th><th>Tipo</th><th>Categoria</th><th>Unidad</th><th>Estado</th><th>Minimo</th><th>Existencia</th><th>Costo</th><th>Precio</th><th></th></tr></thead>
            <tbody data-inventory-catalog-rows data-table-filter-rows="inventory-catalog">
                <?php foreach ($products as $row): ?>
                    <tr data-filter-search="<?= e(strtolower(trim((string) ($row['sku'] ?? '') . ' ' . (string) ($row['name'] ?? '')))) ?>">
                        <td data-label="SKU"><?= e($row['sku']) ?></td>
                        <td data-label="Producto">
                            <strong><?= e($row['name']) ?></strong>
                            <?php if (($row['description'] ?? '') !== ''): ?>
                                <small><?= e($row['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Tipo"><span class="badge badge-neutral"><?= e(product_type_label($row['product_type'] ?? 'merchandise')) ?></span></td>
                        <td data-label="Categoria"><?= e($row['category_name'] ?? 'Sin categoria') ?></td>
                        <td data-label="Unidad"><?= e(product_unit_label($row)) ?></td>
                        <td data-label="Estado">
                            <span class="badge <?= ($row['status'] ?? 'active') === 'inactive' ? 'badge-neutral' : 'badge-ok' ?>">
                                <?= ($row['status'] ?? 'active') === 'inactive' ? 'Inactivo' : 'Activo' ?>
                            </span>
                        </td>
                        <td data-label="Minimo"><?= product_tracks_inventory($row) ? money($row['stock_min']) . ' ' . product_unit_label($row) : 'N/A' ?></td>
                        <td data-label="Existencia"><span class="badge <?= product_tracks_inventory($row) && (float) $row['stock'] <= (float) $row['stock_min'] ? 'badge-danger' : 'badge-ok' ?>"><?= product_tracks_inventory($row) ? money($row['stock']) . ' ' . product_unit_label($row) : 'N/A' ?></span></td>
                        <td data-label="Costo"><?= money($row['cost']) ?> / <?= e(product_unit_label($row)) ?></td>
                        <td data-label="Precio"><?= money($row['price']) ?> / <?= e(product_unit_label($row)) ?></td>
                        <td data-label="Acciones" class="inventory-actions-cell">
                            <div class="inventory-action-group inventory-action-group-primary">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline btn-soft-accent"
                                    data-modal-open="inventory-product-edit"
                                    data-product-edit-trigger
                                    data-product-edit-action="<?= e(app_url('/inventory/products/' . (int) $row['id'])) ?>"
                                    data-product-name="<?= e((string) ($row['name'] ?? '')) ?>"
                                    data-product-sku="<?= e((string) ($row['sku'] ?? '')) ?>"
                                    data-product-description="<?= e((string) ($row['description'] ?? '')) ?>"
                                    data-product-type="<?= e((string) ($row['product_type'] ?? 'merchandise')) ?>"
                                    data-product-category-id="<?= e((string) ($row['category_id'] ?? '')) ?>"
                                    data-product-unit-label="<?= e(product_unit_label($row)) ?>"
                                    data-product-stock-min="<?= e((string) ($row['stock_min'] ?? 0)) ?>"
                                    data-product-cost="<?= e((string) ($row['cost'] ?? 0)) ?>"
                                    data-product-price="<?= e((string) ($row['price'] ?? 0)) ?>"
                                    data-product-currency="<?= e((string) ($row['currency_code'] ?? base_currency())) ?>"
                                    data-product-current-stock-display="<?= e(product_tracks_inventory($row) ? money($row['stock']) . ' ' . product_unit_label($row) : 'No aplica') ?>"
                                >Editar</button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline btn-soft-neutral"
                                    data-modal-open="inventory-product-duplicate"
                                    data-product-duplicate-trigger
                                    data-source-id="<?= (int) $row['id'] ?>"
                                    data-source-name="<?= e($row['name']) ?>"
                                    data-source-sku="<?= e($row['sku']) ?>"
                                >
                                    Duplicar
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline btn-soft-neutral"
                                    data-modal-open="inventory-product-variants"
                                    data-product-variants-trigger
                                    data-source-name="<?= e($row['name']) ?>"
                                    data-source-sku="<?= e($row['sku']) ?>"
                                    data-source-category-id="<?= (int) ($row['category_id'] ?? 0) ?>"
                                    data-source-product-type="<?= e($row['product_type'] ?? 'merchandise') ?>"
                                    data-source-unit-label="<?= e(product_unit_label($row)) ?>"
                                    data-source-stock-min="<?= e((string) ($row['stock_min'] ?? 0)) ?>"
                                    data-source-cost="<?= e((string) ($row['cost'] ?? 0)) ?>"
                                    data-source-price="<?= e((string) ($row['price'] ?? 0)) ?>"
                                    data-source-currency="<?= e((string) ($row['currency_code'] ?? base_currency())) ?>"
                                    data-source-description="<?= e((string) ($row['description'] ?? '')) ?>"
                                >
                                    Variantes
                                </button>
                            </div>
                            <div class="inventory-action-group inventory-action-group-secondary">
                                <form method="post" action="/inventory/products/<?= $row['id'] ?>/status">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline btn-soft-neutral">
                                        <?= ($row['status'] ?? 'active') === 'inactive' ? 'Activar' : 'Desactivar' ?>
                                    </button>
                                </form>
                                <form method="post" action="/inventory/products/<?= $row['id'] ?>/delete" onsubmit="return confirm('Se aplicara borrado logico al producto. Solo se permite si el stock actual es cero.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline btn-soft-danger" <?= (float) $row['stock'] > 0 ? 'disabled title="Debes dejar el stock en cero antes de eliminar."' : '' ?>>
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-empty-state" data-inventory-catalog-empty data-table-filter-empty="inventory-catalog" hidden>
                    <td colspan="11">No hay productos que coincidan con la busqueda.</td>
                </tr>
            </tbody>
        </table>
    </div>
</article>

<div class="modal-shell" data-modal="inventory-product-edit" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="inventory-product-edit-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Producto</span>
                <h3 id="inventory-product-edit-title" data-product-edit-title>Editar producto</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>

        <form method="post" action="" class="form two-cols" data-calc="product" data-product-form data-product-edit-form>
            <?= csrf_field() ?>

            <label>Categoria<select name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Tipo
                <select name="product_type" required>
                    <?php foreach (product_type_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>SKU<input name="sku" value="" required></label>
            <label>Nombre<input name="name" value="" required></label>
            <label data-product-unit-wrap style="display:none;"><span data-product-unit-label>Unidad base</span><input name="unit_label" value="und" list="product-unit-options" maxlength="20" placeholder="Ej. und, m, kg"></label>
            <label data-product-stock-min-wrap><span data-product-stock-min-label>Stock minimo</span><input type="number" step="1" min="0" name="stock_min" value="0"></label>
            <label><span data-product-cost-label>Costo unitario</span><input type="number" step="0.01" name="cost" value="0" required></label>
            <label><span data-product-price-label>Precio unitario</span><input type="number" step="0.01" name="price" value="0" required></label>
            <label>Moneda<select name="currency_code"><option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option><option value="<?= e(secondary_currency()) ?>"><?= e(secondary_currency()) ?></option></select></label>
            <label data-product-current-stock-wrap><span data-product-current-stock-label>Stock actual</span><input value="No aplica" disabled data-product-current-stock-input></label>
            <label class="col-span-2">Descripcion<textarea name="description"></textarea></label>
            <div class="col-span-2 empty-state" data-product-type-note>
                Producto normal: flujo simple de compra y venta. Si luego quieres fabricarlo, puedes agregar receta en Produccion sin complicar este formulario.
            </div>

            <button class="btn col-span-2">Guardar cambios</button>
        </form>
    </div>
</div>

<div class="modal-shell" data-modal="inventory-category-edit" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="inventory-category-edit-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Categoria</span>
                <h3 id="inventory-category-edit-title" data-category-edit-title>Editar categoria</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="" class="form" data-category-edit-form>
            <?= csrf_field() ?>
            <label>Nombre<input name="name" value="" required></label>
            <label>Descripcion<textarea name="description"></textarea></label>
            <button class="btn">Guardar cambios</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const syncProductForm = (form) => {
            if (!form) {
                return;
            }

            const typeSelect = form.querySelector("[name='product_type']");
            const unitWrap = form.querySelector("[data-product-unit-wrap]");
            const unitInput = form.querySelector("[name='unit_label']");
            const unitLabel = form.querySelector("[data-product-unit-label]");
            const stockMinWrap = form.querySelector("[data-product-stock-min-wrap]");
            const stockMinInput = form.querySelector("[name='stock_min']");
            const stockMinLabel = form.querySelector("[data-product-stock-min-label]");
            const initialStockWrap = form.querySelector("[data-product-initial-stock-wrap]");
            const initialStockInput = form.querySelector("[name='initial_stock']");
            const initialStockLabel = form.querySelector("[data-product-initial-stock-label]");
            const currentStockWrap = form.querySelector("[data-product-current-stock-wrap]");
            const currentStockLabel = form.querySelector("[data-product-current-stock-label]");
            const costLabel = form.querySelector("[data-product-cost-label]");
            const priceLabel = form.querySelector("[data-product-price-label]");
            const note = form.querySelector("[data-product-type-note]");

            if (!typeSelect) {
                return;
            }

            const type = String(typeSelect.value || "").trim();
            const isRawMaterial = type === "raw_material";
            const isService = type === "service";

            if (unitInput) {
                if (isRawMaterial) {
                    const currentUnit = String(unitInput.value || "").trim().toLowerCase();
                    if (currentUnit === "" || currentUnit === "und" || currentUnit === "serv") {
                        unitInput.value = "m";
                    }
                } else {
                    unitInput.value = isService ? "serv" : "und";
                }
            }

            const activeUnit = String(unitInput?.value || (isService ? "serv" : "und")).trim() || (isService ? "serv" : "und");

            if (unitWrap) {
                unitWrap.style.display = isRawMaterial ? "" : "none";
            }
            if (unitLabel) {
                unitLabel.textContent = "Unidad base";
            }

            if (stockMinWrap) {
                stockMinWrap.style.display = isService ? "none" : "";
            }
            if (initialStockWrap) {
                initialStockWrap.style.display = isService ? "none" : "";
            }
            if (currentStockWrap) {
                currentStockWrap.style.display = isService ? "none" : "";
            }
            if (stockMinInput) {
                stockMinInput.step = isRawMaterial ? "0.01" : "1";
                stockMinInput.min = "0";
            }
            if (initialStockInput) {
                initialStockInput.step = isRawMaterial ? "0.01" : "1";
                initialStockInput.min = "0";
            }

            if (stockMinLabel) {
                stockMinLabel.textContent = isRawMaterial ? `Stock minimo (${activeUnit})` : "Stock minimo";
            }
            if (initialStockLabel) {
                initialStockLabel.textContent = isRawMaterial ? `Stock inicial (${activeUnit})` : "Stock inicial";
            }
            if (currentStockLabel) {
                currentStockLabel.textContent = isRawMaterial ? `Stock actual (${activeUnit})` : "Stock actual";
            }
            if (costLabel) {
                costLabel.textContent = isRawMaterial ? "Costo por unidad base" : "Costo unitario";
            }
            if (priceLabel) {
                priceLabel.textContent = isRawMaterial ? "Precio por unidad base" : "Precio unitario";
            }
            if (note) {
                if (isRawMaterial) {
                    note.textContent = "Materia prima: aqui si aparece unidad base porque compras y recetas trabajan con esa misma medida.";
                } else if (isService) {
                    note.textContent = "Servicio: no maneja stock, por eso el formulario oculta los campos fisicos.";
                } else if (type === "finished_good") {
                    note.textContent = "Producto fabricado: se vende igual que un producto normal, pero puedes usarlo en Produccion con receta si lo necesitas.";
                } else {
                    note.textContent = "Producto: flujo simple. Sin unidad base visible ni pasos extra para no complicarte.";
                }
            }
        };

        const setFieldValue = (field, value, fallback = "") => {
            if (!field) {
                return;
            }

            field.value = value !== undefined && value !== null && String(value).trim() !== ""
                ? String(value)
                : fallback;
        };

        const parseVariantValues = (value) => {
            const raw = String(value || "");
            const chunks = raw.split(/[\r\n,;]+/);
            const items = [];
            const seen = new Set();

            chunks.forEach((chunk) => {
                const normalized = String(chunk || "").trim();
                if (!normalized) {
                    return;
                }

                const key = normalized.toLowerCase();
                if (seen.has(key)) {
                    return;
                }

                seen.add(key);
                items.push(normalized);
            });

            return items;
        };

        const sanitizeSkuSegment = (value) => {
            const normalized = String(value || "").trim().toUpperCase().replace(/[^A-Z0-9]+/g, "-").replace(/^-+|-+$/g, "");
            return normalized.slice(0, 24);
        };

        const buildVariantName = (baseName, color, size) => {
            const parts = [String(baseName || "").trim()];
            if (String(color || "").trim() !== "") {
                parts.push(String(color || "").trim());
            }
            if (String(size || "").trim() !== "") {
                parts.push(`Talla ${String(size || "").trim()}`);
            }

            return parts.filter(Boolean).join(" ").trim();
        };

        const buildVariantSku = (prefix, color, size) => {
            const segments = [sanitizeSkuSegment(prefix)];
            if (String(color || "").trim() !== "") {
                segments.push(sanitizeSkuSegment(color));
            }
            if (String(size || "").trim() !== "") {
                segments.push(sanitizeSkuSegment(size));
            }

            return segments.filter(Boolean).join("-").slice(0, 80) || "SKU";
        };

        const renderVariantPreview = (form) => {
            if (!form) {
                return;
            }

            const baseName = form.querySelector("[name='base_name']")?.value || "";
            const skuPrefix = form.querySelector("[name='sku_prefix']")?.value || "";
            const colors = parseVariantValues(form.querySelector("[name='variant_colors']")?.value || "");
            const sizes = parseVariantValues(form.querySelector("[name='variant_sizes']")?.value || "");
            const countNode = form.querySelector("[data-variant-preview-count]");
            const statusNode = form.querySelector("[data-variant-preview-status]");
            const emptyNode = form.querySelector("[data-variant-preview-empty]");
            const wrapNode = form.querySelector("[data-variant-preview-wrap]");
            const listNode = form.querySelector("[data-variant-preview-list]");
            const moreNode = form.querySelector("[data-variant-preview-more]");

            const colorValues = colors.length ? colors : [""];
            const sizeValues = sizes.length ? sizes : [""];
            const hasBase = String(baseName).trim() !== "";
            const hasSelectors = colors.length > 0 || sizes.length > 0;

            if (!hasBase || !hasSelectors) {
                if (countNode) {
                    countNode.textContent = "0";
                }
                if (statusNode) {
                    statusNode.textContent = hasBase ? "Faltan colores o tallas" : "Esperando datos";
                }
                if (emptyNode) {
                    emptyNode.hidden = false;
                }
                if (wrapNode) {
                    wrapNode.hidden = true;
                }
                if (listNode) {
                    listNode.innerHTML = "";
                }
                if (moreNode) {
                    moreNode.hidden = true;
                    moreNode.textContent = "";
                }
                return;
            }

            const previewRows = [];
            colorValues.forEach((color) => {
                sizeValues.forEach((size) => {
                    previewRows.push({
                        name: buildVariantName(baseName, color, size),
                        sku: buildVariantSku(skuPrefix, color, size),
                    });
                });
            });

            if (countNode) {
                countNode.textContent = String(previewRows.length);
            }
            if (statusNode) {
                statusNode.textContent = previewRows.length === 1 ? "1 variante lista" : `${previewRows.length} variantes listas`;
            }
            if (emptyNode) {
                emptyNode.hidden = true;
            }
            if (wrapNode) {
                wrapNode.hidden = false;
            }
            if (listNode) {
                listNode.innerHTML = previewRows.slice(0, 12).map((row, index) => `
                    <tr>
                        <td data-label="#">${index + 1}</td>
                        <td data-label="Nombre estimado"><strong>${row.name}</strong></td>
                        <td data-label="SKU estimado">${row.sku}</td>
                    </tr>
                `).join("");
            }
            if (moreNode) {
                if (previewRows.length > 12) {
                    moreNode.hidden = false;
                    moreNode.textContent = `Se muestran 12 de ${previewRows.length} variantes. Al guardar se crearan todas.`;
                } else {
                    moreNode.hidden = true;
                    moreNode.textContent = "";
                }
            }
        };

        const bindInventoryCatalogFilter = () => {
            const input = document.querySelector("[data-inventory-catalog-search-input]");
            const rows = Array.from(document.querySelectorAll("[data-inventory-catalog-rows] tr[data-filter-search]"));
            const emptyState = document.querySelector("[data-inventory-catalog-empty]");
            const countNode = document.querySelector("[data-inventory-catalog-count]");

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

        document.querySelectorAll("[data-product-form]").forEach((form) => {
            const typeSelect = form.querySelector("[name='product_type']");
            const unitInput = form.querySelector("[name='unit_label']");

            const refresh = () => syncProductForm(form);

            if (typeSelect) {
                typeSelect.addEventListener("change", refresh);
                typeSelect.addEventListener("input", refresh);
            }
            if (unitInput) {
                unitInput.addEventListener("change", refresh);
                unitInput.addEventListener("input", refresh);
            }

            refresh();
        });

        const duplicateModal = document.querySelector("[data-modal='inventory-product-duplicate']");
        const duplicateSourceId = duplicateModal?.querySelector("[data-duplicate-source-id]");
        const duplicateSourceName = duplicateModal?.querySelector("[data-duplicate-source-name]");
        const duplicateName = duplicateModal?.querySelector("[data-duplicate-name]");
        const duplicateSku = duplicateModal?.querySelector("[data-duplicate-sku]");
        const duplicateInitialStock = duplicateModal?.querySelector("[name='initial_stock']");
        const productEditModal = document.querySelector("[data-modal='inventory-product-edit']");
        const productEditForm = productEditModal?.querySelector("[data-product-edit-form]");
        const productEditTitle = productEditModal?.querySelector("[data-product-edit-title]");
        const categoryEditModal = document.querySelector("[data-modal='inventory-category-edit']");
        const categoryEditForm = categoryEditModal?.querySelector("[data-category-edit-form]");
        const categoryEditTitle = categoryEditModal?.querySelector("[data-category-edit-title]");

        document.querySelectorAll("[data-product-edit-trigger]").forEach((button) => {
            button.addEventListener("click", () => {
                if (!productEditForm) {
                    return;
                }

                const categorySelect = productEditForm.querySelector("[name='category_id']");
                const typeSelect = productEditForm.querySelector("[name='product_type']");
                const skuInput = productEditForm.querySelector("[name='sku']");
                const nameInput = productEditForm.querySelector("[name='name']");
                const unitInput = productEditForm.querySelector("[name='unit_label']");
                const stockMinInput = productEditForm.querySelector("[name='stock_min']");
                const costInput = productEditForm.querySelector("[name='cost']");
                const priceInput = productEditForm.querySelector("[name='price']");
                const currencySelect = productEditForm.querySelector("[name='currency_code']");
                const stockDisplayInput = productEditForm.querySelector("[data-product-current-stock-input]");
                const descriptionInput = productEditForm.querySelector("[name='description']");

                productEditForm.action = button.dataset.productEditAction || "";

                if (productEditTitle) {
                    productEditTitle.textContent = `Editar ${button.dataset.productName || "producto"}`;
                }

                setFieldValue(categorySelect, button.dataset.productCategoryId, "");
                setFieldValue(typeSelect, button.dataset.productType || "merchandise", "merchandise");
                setFieldValue(skuInput, button.dataset.productSku || "");
                setFieldValue(nameInput, button.dataset.productName || "");
                setFieldValue(unitInput, button.dataset.productUnitLabel || "und", "und");
                setFieldValue(stockMinInput, button.dataset.productStockMin || "0", "0");
                setFieldValue(costInput, button.dataset.productCost || "0", "0");
                setFieldValue(priceInput, button.dataset.productPrice || "0", "0");
                setFieldValue(currencySelect, button.dataset.productCurrency || "USD", "USD");
                setFieldValue(stockDisplayInput, button.dataset.productCurrentStockDisplay || "No aplica", "No aplica");
                setFieldValue(descriptionInput, button.dataset.productDescription || "");

                syncProductForm(productEditForm);
            });
        });

        document.querySelectorAll("[data-category-edit-trigger]").forEach((button) => {
            button.addEventListener("click", () => {
                if (!categoryEditForm) {
                    return;
                }

                categoryEditForm.action = button.dataset.categoryEditAction || "";

                if (categoryEditTitle) {
                    categoryEditTitle.textContent = `Editar ${button.dataset.categoryName || "categoria"}`;
                }

                setFieldValue(categoryEditForm.querySelector("[name='name']"), button.dataset.categoryName || "");
                setFieldValue(categoryEditForm.querySelector("[name='description']"), button.dataset.categoryDescription || "");
            });
        });

        document.querySelectorAll("[data-product-duplicate-trigger]").forEach((button) => {
            button.addEventListener("click", () => {
                const { sourceId = "", sourceName = "", sourceSku = "" } = button.dataset;

                setFieldValue(duplicateSourceId, sourceId);
                setFieldValue(duplicateSourceName, sourceName, "Producto base");
                setFieldValue(duplicateName, sourceName ? `${sourceName} Copia` : "");
                setFieldValue(duplicateSku, sourceSku ? `${sourceSku}-COPIA` : "");
                setFieldValue(duplicateInitialStock, "0", "0");
            });
        });

        const variantsModal = document.querySelector("[data-modal='inventory-product-variants']");
        const variantsForm = variantsModal?.querySelector("form");

        if (variantsForm) {
            ["base_name", "sku_prefix", "variant_colors", "variant_sizes"].forEach((fieldName) => {
                const field = variantsForm.querySelector(`[name='${fieldName}']`);
                if (!field) {
                    return;
                }

                field.addEventListener("input", () => renderVariantPreview(variantsForm));
                field.addEventListener("change", () => renderVariantPreview(variantsForm));
            });

            renderVariantPreview(variantsForm);
        }

        document.querySelectorAll("[data-product-variants-trigger]").forEach((button) => {
            button.addEventListener("click", () => {
                if (!variantsForm) {
                    return;
                }

                variantsForm.reset();

                const categorySelect = variantsForm.querySelector("[name='category_id']");
                const typeSelect = variantsForm.querySelector("[name='product_type']");
                const currencySelect = variantsForm.querySelector("[name='currency_code']");
                const unitInput = variantsForm.querySelector("[name='unit_label']");
                const baseNameInput = variantsForm.querySelector("[name='base_name']");
                const skuPrefixInput = variantsForm.querySelector("[name='sku_prefix']");
                const colorsInput = variantsForm.querySelector("[name='variant_colors']");
                const sizesInput = variantsForm.querySelector("[name='variant_sizes']");
                const stockMinInput = variantsForm.querySelector("[name='stock_min']");
                const costInput = variantsForm.querySelector("[name='cost']");
                const priceInput = variantsForm.querySelector("[name='price']");
                const initialStockInput = variantsForm.querySelector("[name='initial_stock']");
                const descriptionInput = variantsForm.querySelector("[name='description']");

                const {
                    sourceName = "",
                    sourceSku = "",
                    sourceCategoryId = "",
                    sourceProductType = "merchandise",
                    sourceUnitLabel = "und",
                    sourceStockMin = "0",
                    sourceCost = "0",
                    sourcePrice = "0",
                    sourceCurrency = "",
                    sourceDescription = "",
                } = button.dataset;

                if (categorySelect && sourceCategoryId !== "") {
                    categorySelect.value = sourceCategoryId;
                }
                if (typeSelect) {
                    typeSelect.value = sourceProductType || "merchandise";
                }
                if (currencySelect && sourceCurrency !== "") {
                    currencySelect.value = sourceCurrency;
                }

                setFieldValue(unitInput, sourceUnitLabel || "und", "und");
                setFieldValue(baseNameInput, sourceName);
                setFieldValue(skuPrefixInput, sourceSku);
                setFieldValue(colorsInput, "");
                setFieldValue(sizesInput, "");
                setFieldValue(stockMinInput, sourceStockMin || "0", "0");
                setFieldValue(costInput, sourceCost || "0", "0");
                setFieldValue(priceInput, sourcePrice || "0", "0");
                setFieldValue(initialStockInput, "0", "0");
                setFieldValue(descriptionInput, sourceDescription);

                syncProductForm(variantsForm);
                renderVariantPreview(variantsForm);
            });
        });

        bindInventoryCatalogFilter();
    })();
</script>
