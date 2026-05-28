<section class="page-header">
    <div>
        <span class="eyebrow">Proveedores</span>
        <h2>Consulta y mantenimiento de proveedores</h2>
        <p>Administra la base de proveedores desde finanzas y controla su estado operativo.</p>
    </div>
    <div class="header-summary">
        <div><span>Total</span><strong><?= (int) $summary['suppliers'] ?></strong></div>
        <div><span>Activos</span><strong><?= (int) $summary['active'] ?></strong></div>
        <div><span>Con compras</span><strong><?= (int) $summary['with_purchases'] ?></strong></div>
    </div>
</section>

<section class="workspace-grid">
    <article class="card card-feature">
        <header class="section-head">
            <div>
                <h3><?= $currentSupplier ? 'Editar proveedor' : 'Nuevo proveedor' ?></h3>
                <p><?= $currentSupplier ? 'Actualiza la ficha seleccionada o corrige sus datos comerciales.' : 'Crea un proveedor listo para usar en compras y cuentas por pagar.' ?></p>
            </div>
            <?php if ($currentSupplier): ?>
                <a class="btn btn-outline btn-sm" href="/suppliers">Cancelar</a>
            <?php endif; ?>
        </header>

        <form method="post" action="<?= e($currentSupplier ? '/suppliers/' . $currentSupplier['id'] : '/suppliers') ?>" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required value="<?= e($currentSupplier['name'] ?? '') ?>"></label>
            <label>Documento<input name="document" value="<?= e($currentSupplier['document'] ?? '') ?>"></label>
            <label>Telefono<input name="phone" value="<?= e($currentSupplier['phone'] ?? '') ?>"></label>
            <label>Email<input name="email" value="<?= e($currentSupplier['email'] ?? '') ?>"></label>
            <label class="col-span-2">Direccion<textarea name="address"><?= e($currentSupplier['address'] ?? '') ?></textarea></label>
            <button class="btn col-span-2"><?= $currentSupplier ? 'Guardar cambios' : 'Crear proveedor' ?></button>
        </form>
    </article>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Acciones rapidas</h3>
                <p>Atajos del flujo financiero relacionado.</p>
            </div>
        </header>

        <div class="stack-list">
            <div class="stack-row">
                <div>
                    <strong>Compras</strong>
                    <small>Los proveedores activos quedan disponibles al registrar nuevas compras.</small>
                </div>
                <a href="/purchases" class="btn btn-sm btn-outline">Ir a compras</a>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Reportes</strong>
                    <small>Consulta el impacto de compras y cuentas por pagar.</small>
                </div>
                <a href="/reports?type=payables" class="btn btn-sm btn-outline">Ver CxP</a>
            </div>
        </div>
    </article>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Directorio de proveedores</h3>
            <p>Visualiza estado, actividad de compras y datos de contacto desde una sola tabla.</p>
        </div>
    </header>

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
            <tbody>
                <?php if ($suppliers): ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php $isActive = (int) ($supplier['is_active'] ?? 1) === 1; ?>
                        <tr>
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
                            <td data-label="Acciones" class="actions-row">
                                <a class="btn btn-sm btn-outline" href="/suppliers?edit=<?= (int) $supplier['id'] ?>">Editar</a>
                                <form method="post" action="/suppliers/<?= (int) $supplier['id'] ?>/status" class="document-action-form" onsubmit="return confirm('Se actualizara el estado operativo del proveedor.');">
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
    </div>
</article>
