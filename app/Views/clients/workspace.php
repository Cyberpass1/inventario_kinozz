<?php
$totalClients = (int) ($summary['clients'] ?? count($clients));
$withInvoices = (int) ($summary['with_invoices'] ?? 0);
$withoutInvoices = max(0, $totalClients - $withInvoices);
$editId = $currentClient ? (int) $currentClient['id'] : 0;
?>

<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Directorio de clientes</h3>
            <small>Consulta, edita y mantiene al dia la base comercial lista para facturar y despachar.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-modal-open="client-create-modal">+ Nuevo cliente</button>
            <a class="btn btn-outline btn-sm" href="/invoices">Ir a facturas</a>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi"><span>Clientes</span><strong><?= $totalClients ?></strong></div>
        <div class="inventory-kpi inventory-kpi-in"><span>Con facturas</span><strong><?= $withInvoices ?></strong></div>
        <div class="inventory-kpi"><span>Sin facturar</span><strong><?= $withoutInvoices ?></strong></div>
    </div>
</section>

<!-- Directorio protagonista -->
<article class="card inventory-catalog-card">
    <div class="inventory-catalog-toolbar">
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Nombre, documento, telefono o email..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="clients-directory"
            >
        </label>
        <div class="inventory-filter-meta">
            <strong data-table-filter-count data-table-filter-target="clients-directory" data-table-filter-label="clientes"><?= $totalClients ?> clientes</strong>
            <small>en el directorio</small>
        </div>
    </div>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
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
            <tbody data-table-filter-rows="clients-directory" data-table-pagination data-table-pagination-size="20">
                <?php if ($clients): ?>
                    <?php foreach ($clients as $client): ?>
                        <?php
                        $clientId = (int) $client['id'];
                        $haystack = strtolower(trim(
                            ((string) ($client['name'] ?? '')) . ' '
                            . ((string) ($client['document'] ?? '')) . ' '
                            . ((string) ($client['phone'] ?? '')) . ' '
                            . ((string) ($client['email'] ?? ''))
                        ));
                        ?>
                        <tr data-filter-search="<?= e($haystack) ?>">
                            <td data-label="Nombre">
                                <div class="money-stack">
                                    <strong><?= e($client['name']) ?></strong>
                                    <small><?= trim((string) ($client['address'] ?? '')) !== '' ? e($client['address']) : 'Sin direccion registrada.' ?></small>
                                </div>
                            </td>
                            <td data-label="Documento"><?= e($client['document']) !== '' ? e($client['document']) : '-' ?></td>
                            <td data-label="Telefono"><?= e($client['phone']) !== '' ? e($client['phone']) : '-' ?></td>
                            <td data-label="Email"><?= e($client['email']) !== '' ? e($client['email']) : '-' ?></td>
                            <td data-label="Facturas"><span class="badge badge-neutral"><?= (int) $client['invoices_count'] ?></span></td>
                            <td data-label="Ultima factura"><?= e($client['last_invoice_date'] ?? '-') ?></td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <button type="button" class="btn btn-sm btn-outline" data-modal-open="client-edit-<?= $clientId ?>">Editar</button>
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
        <div class="empty-state" data-table-filter-empty="clients-directory" hidden>No hay clientes que coincidan con la busqueda.</div>
    </div>
</article>

<!-- Modal: nuevo cliente -->
<div class="modal-shell" data-modal="client-create-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="client-create-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Clientes</span>
                <h3 id="client-create-title">Nuevo cliente</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>

        <form method="post" action="/clients" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <label>Documento<input name="document"></label>
            <label>Telefono<input name="phone"></label>
            <label>Email<input type="email" name="email"></label>
            <label class="col-span-2">Direccion<textarea name="address"></textarea></label>
            <button class="btn col-span-2">Crear cliente</button>
        </form>
    </div>
</div>

<!-- Modales: editar cliente -->
<?php foreach ($clients as $client): ?>
    <?php $clientId = (int) $client['id']; ?>
    <div class="modal-shell" data-modal="client-edit-<?= $clientId ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="client-edit-title-<?= $clientId ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Cliente</span>
                    <h3 id="client-edit-title-<?= $clientId ?>">Editar <?= e($client['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="/clients/<?= $clientId ?>" class="form two-cols">
                <?= csrf_field() ?>
                <label>Nombre<input name="name" required value="<?= e($client['name'] ?? '') ?>"></label>
                <label>Documento<input name="document" value="<?= e($client['document'] ?? '') ?>"></label>
                <label>Telefono<input name="phone" value="<?= e($client['phone'] ?? '') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= e($client['email'] ?? '') ?>"></label>
                <label class="col-span-2">Direccion<textarea name="address"><?= e($client['address'] ?? '') ?></textarea></label>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($editId > 0): ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelector('[data-modal-open="client-edit-<?= $editId ?>"]')?.click();
    });
</script>
<?php endif; ?>
