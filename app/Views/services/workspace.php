<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Catalogo de servicios</h3>
            <small>Conceptos comerciales sin inventario: estampado, patronaje, asesorias, etc. <?= count($services) ?> registros.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-services-create-toggle>+ Servicio</button>
        </div>
    </header>

    <div class="inventory-kpis inventory-kpis-services">
        <div class="inventory-kpi"><span>Servicios</span><strong><?= (int) ($summary['services'] ?? 0) ?></strong></div>
        <div class="inventory-kpi"><span>Activos</span><strong><?= (int) ($summary['active'] ?? 0) ?></strong></div>
        <div class="inventory-kpi"><span>Inactivos</span><strong><?= (int) (($summary['services'] ?? 0) - ($summary['active'] ?? 0)) ?></strong></div>
    </div>

    <!-- Panel inline: Nuevo servicio -->
    <article class="card card-feature inventory-create-panel" data-services-create-panel hidden>
        <header class="section-head">
            <div>
                <h3>Nuevo servicio</h3>
                <p>Sin inventario: solo concepto, costo estimado y precio de venta.</p>
            </div>
            <button type="button" class="pos-custom-close" data-services-create-toggle aria-label="Cerrar">&times;</button>
        </header>
        <form method="post" action="/services" class="form two-cols" data-calc="product">
            <?= csrf_field() ?>
            <label>Categoria
                <select name="category_id">
                    <option value="">Sin categoria</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>SKU<input name="sku" required></label>
            <label>Nombre<input name="name" required></label>
            <label>Costo estimado<input type="number" step="0.01" name="cost" value="0" data-cost-input></label>
            <label>Precio de venta<input type="number" step="0.01" name="price" value="0" data-price-input></label>
            <label>Moneda
                <select name="currency_code">
                    <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                    <option value="<?= e(secondary_currency()) ?>"><?= e(secondary_currency()) ?></option>
                </select>
            </label>
            <label class="col-span-2">Descripcion<textarea name="description"></textarea></label>
            <div class="col-span-2 live-panel live-panel-compact">
                <div><span>Margen estimado</span><strong data-product-margin>0,00</strong></div>
                <div><span>Inventario</span><strong>No aplica</strong></div>
            </div>
            <button class="btn col-span-2">Guardar servicio</button>
        </form>
    </article>
</section>

<article class="card inventory-catalog-card">
    <div class="inventory-catalog-toolbar" data-services-filters>
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="SKU, nombre o descripcion..."
                autocomplete="off"
                data-services-filter-text
            >
        </label>
        <label class="inventory-filter">
            <span>Categoria</span>
            <select data-services-filter-category>
                <option value="">Todas</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="inventory-filter">
            <span>Estado</span>
            <select data-services-filter-status>
                <option value="">Todos</option>
                <option value="active">Activos</option>
                <option value="inactive">Inactivos</option>
            </select>
        </label>
        <div class="inventory-filter-meta">
            <strong data-services-filter-count><?= count($services) ?></strong>
            <small>de <?= count($services) ?></small>
        </div>
    </div>
    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead><tr><th>SKU</th><th>Servicio</th><th>Categoria</th><th>Estado</th><th>Costo</th><th>Precio</th><th>Moneda</th><th></th></tr></thead>
            <tbody data-services-rows>
                <?php if ($services): ?>
                    <?php foreach ($services as $service): ?>
                        <tr
                            data-filter-search="<?= e(strtolower(trim((string) ($service['sku'] ?? '') . ' ' . (string) ($service['name'] ?? '') . ' ' . (string) ($service['description'] ?? '')))) ?>"
                            data-row-category="<?= e((string) ($service['category_id'] ?? '')) ?>"
                            data-row-status="<?= e((string) ($service['status'] ?? 'active')) ?>"
                        >
                            <td data-label="SKU"><?= e($service['sku'] ?? '') ?></td>
                            <td data-label="Servicio">
                                <div class="money-stack">
                                    <strong><?= e($service['name'] ?? '') ?></strong>
                                    <small><?= trim((string) ($service['description'] ?? '')) !== '' ? e($service['description']) : 'Sin descripcion.' ?></small>
                                </div>
                            </td>
                            <td data-label="Categoria"><?= e($service['category_name'] ?? 'Sin categoria') ?></td>
                            <td data-label="Estado">
                                <span class="badge <?= ($service['status'] ?? 'active') === 'inactive' ? 'badge-neutral' : 'badge-ok' ?>">
                                    <?= ($service['status'] ?? 'active') === 'inactive' ? 'Inactivo' : 'Activo' ?>
                                </span>
                            </td>
                            <td data-label="Costo"><?= money($service['cost'] ?? 0) ?></td>
                            <td data-label="Precio"><?= money($service['price'] ?? 0) ?></td>
                            <td data-label="Moneda"><?= e($service['currency_code'] ?? '') ?></td>
                            <td data-label="Acciones" class="actions-row">
                                <button type="button" class="btn btn-sm btn-outline" data-modal-open="service-edit-<?= (int) $service['id'] ?>">Editar</button>
                                <form method="post" action="/services/<?= (int) $service['id'] ?>/status">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline">
                                        <?= ($service['status'] ?? 'active') === 'inactive' ? 'Activar' : 'Desactivar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">Todavia no hay servicios creados.</td></tr>
                <?php endif; ?>
                <tr class="table-empty-state" data-services-empty hidden>
                    <td colspan="8">No hay servicios que coincidan con los filtros.</td>
                </tr>
            </tbody>
        </table>
    </div>
</article>

<?php foreach ($services as $service): ?>
    <div class="modal-shell" data-modal="service-edit-<?= (int) $service['id'] ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="service-edit-title-<?= (int) $service['id'] ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Servicio</span>
                    <h3 id="service-edit-title-<?= (int) $service['id'] ?>">Editar <?= e($service['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="/services/<?= (int) $service['id'] ?>" class="form two-cols">
                <?= csrf_field() ?>
                <label>Categoria
                    <select name="category_id">
                        <option value="">Sin categoria</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($service['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>SKU<input name="sku" value="<?= e($service['sku'] ?? '') ?>" required></label>
                <label>Nombre<input name="name" value="<?= e($service['name'] ?? '') ?>" required></label>
                <label>Costo estimado<input type="number" step="0.01" name="cost" value="<?= e((string) ($service['cost'] ?? 0)) ?>"></label>
                <label>Precio de venta<input type="number" step="0.01" name="price" value="<?= e((string) ($service['price'] ?? 0)) ?>"></label>
                <label>Moneda
                    <select name="currency_code">
                        <option value="<?= e(base_currency()) ?>" <?= ($service['currency_code'] ?? '') === base_currency() ? 'selected' : '' ?>><?= e(base_currency()) ?></option>
                        <option value="<?= e(secondary_currency()) ?>" <?= ($service['currency_code'] ?? '') === secondary_currency() ? 'selected' : '' ?>><?= e(secondary_currency()) ?></option>
                    </select>
                </label>
                <label class="col-span-2">Descripcion<textarea name="description"><?= e($service['description'] ?? '') ?></textarea></label>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    "use strict";

    // Toggle del panel "Nuevo servicio"
    const panel = document.querySelector("[data-services-create-panel]");
    const toggles = document.querySelectorAll("[data-services-create-toggle]");
    if (panel && toggles.length > 0) {
        toggles.forEach((btn) => {
            btn.addEventListener("click", () => {
                panel.hidden = !panel.hidden;
                if (!panel.hidden) {
                    const first = panel.querySelector("input[name='sku']");
                    window.setTimeout(() => first?.focus(), 80);
                    panel.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        });
    }

    // Filtros combinados
    const textInput = document.querySelector("[data-services-filter-text]");
    const categorySelect = document.querySelector("[data-services-filter-category]");
    const statusSelect = document.querySelector("[data-services-filter-status]");
    const rows = Array.from(document.querySelectorAll("[data-services-rows] tr[data-filter-search]"));
    const emptyState = document.querySelector("[data-services-empty]");
    const countNode = document.querySelector("[data-services-filter-count]");

    if (rows.length === 0) return;

    const sync = () => {
        const term = String(textInput?.value || "").trim().toLowerCase();
        const cat = String(categorySelect?.value || "").trim();
        const status = String(statusSelect?.value || "").trim();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = String(row.dataset.filterSearch || "").toLowerCase();
            const matchText = term === "" || haystack.includes(term);
            const matchCat = cat === "" || String(row.dataset.rowCategory || "") === cat;
            const matchStatus = status === "" || String(row.dataset.rowStatus || "") === status;
            const matches = matchText && matchCat && matchStatus;
            row.classList.toggle("is-filter-hidden", !matches);
            if (matches) visible += 1;
        });

        if (emptyState) emptyState.hidden = visible !== 0;
        if (countNode) countNode.textContent = String(visible);
    };

    [textInput, categorySelect, statusSelect].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", sync);
        el.addEventListener("change", sync);
    });
    sync();
})();
</script>
