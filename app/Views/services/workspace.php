<section class="page-header">
    <div>
        <span class="eyebrow">Servicios</span>
        <h2>Catalogo comercial sin inventario</h2>
        <p>Gestiona conceptos como estampado, patronaje, corte o cualquier servicio que quieras vender sin mover stock.</p>
    </div>
    <div class="header-summary">
        <div><span>Servicios</span><strong><?= (int) ($summary['services'] ?? 0) ?></strong></div>
        <div><span>Activos</span><strong><?= (int) ($summary['active'] ?? 0) ?></strong></div>
    </div>
</section>

<section class="workspace-grid">
    <article class="card card-feature">
        <header class="section-head"><h3>Nuevo servicio</h3></header>
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
            <div class="col-span-2 live-panel">
                <div><span>Margen estimado</span><strong data-product-margin>0,00</strong></div>
                <div><span>Inventario</span><strong>No aplica</strong></div>
            </div>
            <button class="btn col-span-2">Guardar servicio</button>
        </form>
    </article>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Servicios registrados</h3>
            <p>Quedan disponibles en facturas y notas de entrega igual que un producto vendible, pero sin afectar inventario.</p>
        </div>
    </header>
    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead><tr><th>SKU</th><th>Servicio</th><th>Categoria</th><th>Estado</th><th>Costo</th><th>Precio</th><th>Moneda</th><th></th></tr></thead>
            <tbody>
                <?php if ($services): ?>
                    <?php foreach ($services as $service): ?>
                        <tr>
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
