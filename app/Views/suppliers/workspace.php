<?php
$totalSuppliers = (int) ($summary['suppliers'] ?? count($suppliers));
$activeSuppliers = (int) ($summary['active'] ?? 0);
$withPurchases = (int) ($summary['with_purchases'] ?? 0);
$editId = $currentSupplier ? (int) $currentSupplier['id'] : 0;
?>

<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Directorio de proveedores</h3>
            <small>Estado operativo, actividad de compras y datos de contacto en una sola tabla.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-modal-open="supplier-create-modal">+ Nuevo proveedor</button>
            <a class="btn btn-outline btn-sm" href="/purchases">Ir a compras</a>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi"><span>Proveedores</span><strong><?= $totalSuppliers ?></strong></div>
        <div class="inventory-kpi inventory-kpi-in"><span>Activos</span><strong><?= $activeSuppliers ?></strong></div>
        <div class="inventory-kpi"><span>Con compras</span><strong><?= $withPurchases ?></strong></div>
    </div>
</section>

<!-- Directorio protagonista -->
<article class="card inventory-catalog-card">
    <div class="inventory-catalog-toolbar">
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Proveedor, documento, telefono o email..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="suppliers-directory"
            >
        </label>
        <div class="inventory-filter-meta">
            <strong data-table-filter-count data-table-filter-target="suppliers-directory" data-table-filter-label="proveedores"><?= $totalSuppliers ?> proveedores</strong>
            <small>en el directorio</small>
        </div>
    </div>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Documento</th>
                    <th>Telefono</th>
                    <th>Email</th>
                    <th>Compras</th>
                    <th>Ultima compra</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-table-filter-rows="suppliers-directory" data-table-pagination data-table-pagination-size="20">
                <?php if ($suppliers): ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php
                        $supplierId = (int) $supplier['id'];
                        $isActive = (int) ($supplier['is_active'] ?? 1) === 1;
                        $haystack = strtolower(trim(
                            ((string) ($supplier['name'] ?? '')) . ' '
                            . ((string) ($supplier['document'] ?? '')) . ' '
                            . ((string) ($supplier['phone'] ?? '')) . ' '
                            . ((string) ($supplier['email'] ?? '')) . ' '
                            . ($isActive ? 'activo' : 'inactivo')
                        ));
                        ?>
                        <tr data-filter-search="<?= e($haystack) ?>">
                            <td data-label="Proveedor">
                                <div class="money-stack">
                                    <strong><?= e($supplier['name'] ?? '') ?></strong>
                                    <small><?= trim((string) ($supplier['address'] ?? '')) !== '' ? e($supplier['address']) : 'Sin direccion registrada.' ?></small>
                                </div>
                            </td>
                            <td data-label="Documento"><?= e($supplier['document'] ?? '-') ?></td>
                            <td data-label="Telefono"><?= e($supplier['phone'] ?? '-') ?></td>
                            <td data-label="Email"><?= e($supplier['email'] ?? '-') ?></td>
                            <td data-label="Compras"><span class="badge badge-neutral"><?= (int) ($supplier['purchases_count'] ?? 0) ?></span></td>
                            <td data-label="Ultima compra"><?= e($supplier['last_purchase_date'] ?? '-') ?></td>
                            <td data-label="Estado">
                                <span class="badge <?= $isActive ? 'badge-ok' : 'badge-danger' ?>">
                                    <?= $isActive ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <button type="button" class="btn btn-sm btn-outline" data-modal-open="supplier-edit-<?= $supplierId ?>">Editar</button>
                                <form method="post" action="/suppliers/<?= $supplierId ?>/status" class="document-action-form" onsubmit="return confirm('Se actualizara el estado operativo del proveedor.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= $isActive ? 'btn-danger-soft' : 'btn-outline' ?>">
                                        <?= $isActive ? 'Inactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">Todavia no hay proveedores registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="empty-state" data-table-filter-empty="suppliers-directory" hidden>No hay proveedores que coincidan con la busqueda.</div>
    </div>
</article>

<!-- Modal: nuevo proveedor -->
<div class="modal-shell" data-modal="supplier-create-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="supplier-create-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Proveedores</span>
                <h3 id="supplier-create-title">Nuevo proveedor</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>

        <form method="post" action="/suppliers" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <label>Documento<input name="document"></label>
            <label>Telefono<input name="phone"></label>
            <label>Email<input type="email" name="email"></label>
            <label class="col-span-2">Direccion<textarea name="address"></textarea></label>
            <button class="btn col-span-2">Crear proveedor</button>
        </form>
    </div>
</div>

<!-- Modales: editar proveedor -->
<?php foreach ($suppliers as $supplier): ?>
    <?php $supplierId = (int) $supplier['id']; ?>
    <div class="modal-shell" data-modal="supplier-edit-<?= $supplierId ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="supplier-edit-title-<?= $supplierId ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Proveedor</span>
                    <h3 id="supplier-edit-title-<?= $supplierId ?>">Editar <?= e($supplier['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="/suppliers/<?= $supplierId ?>" class="form two-cols">
                <?= csrf_field() ?>
                <label>Nombre<input name="name" required value="<?= e($supplier['name'] ?? '') ?>"></label>
                <label>Documento<input name="document" value="<?= e($supplier['document'] ?? '') ?>"></label>
                <label>Telefono<input name="phone" value="<?= e($supplier['phone'] ?? '') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= e($supplier['email'] ?? '') ?>"></label>
                <label class="col-span-2">Direccion<textarea name="address"><?= e($supplier['address'] ?? '') ?></textarea></label>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($editId > 0): ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelector('[data-modal-open="supplier-edit-<?= $editId ?>"]')?.click();
    });
</script>
<?php endif; ?>
