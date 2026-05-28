<section class="page-header">
    <div>
        <span class="eyebrow">Clientes</span>
        <h2>Consulta y mantenimiento de clientes</h2>
        <p>Visualiza todos los clientes registrados y actualiza su informacion cuando haga falta.</p>
    </div>
    <div class="header-summary">
        <div><span>Total</span><strong><?= (int) $summary['clients'] ?></strong></div>
        <div><span>Con facturas</span><strong><?= (int) $summary['with_invoices'] ?></strong></div>
        <div><span>Edicion</span><strong><?= $currentClient ? 'Activa' : 'Nueva' ?></strong></div>
    </div>
</section>

<section class="workspace-grid">
    <article class="card card-feature">
        <header class="section-head">
            <div>
                <h3><?= $currentClient ? 'Editar cliente' : 'Nuevo cliente' ?></h3>
                <p><?= $currentClient ? 'Actualiza la ficha seleccionada.' : 'Crea un cliente listo para facturar o despachar.' ?></p>
            </div>
            <?php if ($currentClient): ?>
                <a class="btn btn-outline btn-sm" href="/clients">Cancelar</a>
            <?php endif; ?>
        </header>

        <form method="post" action="<?= e($currentClient ? '/clients/' . $currentClient['id'] : '/clients') ?>" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required value="<?= e($currentClient['name'] ?? '') ?>"></label>
            <label>Documento<input name="document" value="<?= e($currentClient['document'] ?? '') ?>"></label>
            <label>Telefono<input name="phone" value="<?= e($currentClient['phone'] ?? '') ?>"></label>
            <label>Email<input name="email" value="<?= e($currentClient['email'] ?? '') ?>"></label>
            <label class="col-span-2">Direccion<textarea name="address"><?= e($currentClient['address'] ?? '') ?></textarea></label>
            <button class="btn col-span-2"><?= $currentClient ? 'Guardar cambios' : 'Crear cliente' ?></button>
        </form>
    </article>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Acciones rapidas</h3>
                <p>Atajos para continuar el flujo comercial.</p>
            </div>
        </header>

        <div class="stack-list">
            <div class="stack-row">
                <div>
                    <strong>Facturacion</strong>
                    <small>Usa esta base de clientes en las facturas.</small>
                </div>
                <a href="/invoices" class="btn btn-sm btn-outline">Ir a facturas</a>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Notas de entrega</strong>
                    <small>Los mismos clientes quedan disponibles para despachos.</small>
                </div>
                <a href="/delivery-notes" class="btn btn-sm btn-outline">Ir a notas</a>
            </div>
        </div>
    </article>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Directorio</h3>
            <p>Listado general con referencias de uso comercial.</p>
        </div>
    </header>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Telefono</th>
                    <th>Email</th>
                    <th>Facturas</th>
                    <th>Ultima factura</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($clients): ?>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= e($client['name']) ?></td>
                            <td><?= e($client['document']) ?></td>
                            <td><?= e($client['phone']) ?></td>
                            <td><?= e($client['email']) ?></td>
                            <td><span class="badge badge-neutral"><?= (int) $client['invoices_count'] ?></span></td>
                            <td><?= e($client['last_invoice_date'] ?? '-') ?></td>
                            <td class="actions-row">
                                <a class="btn btn-sm btn-outline" href="/clients?edit=<?= $client['id'] ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">Todavia no hay clientes registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
