<?php
$roleLabels = [
    'administrator' => 'Administrador',
    'vendor' => 'Ventas',
    'general_consultant' => 'Consulta general',
];
?>
<?php
$totalUsers = count($users ?? []);
$activeUsers = count(array_filter($users ?? [], static fn (array $user): bool => (int) ($user['is_active'] ?? 1) === 1));
$vendorUsers = count(array_filter($users ?? [], static fn (array $user): bool => ($user['role'] ?? '') === 'vendor'));
$consultUsers = count(array_filter($users ?? [], static fn (array $user): bool => ($user['role'] ?? '') === 'general_consultant'));
?>
<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Usuarios del sistema</h3>
            <small>Solo administracion crea, edita, activa o desactiva cuentas operativas internas.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-modal-open="user-create-modal">+ Nuevo usuario</button>
            <a class="btn btn-outline btn-sm" href="<?= e(app_url('/settings')) ?>">Configuracion</a>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi"><span>Total usuarios</span><strong><?= $totalUsers ?></strong></div>
        <div class="inventory-kpi inventory-kpi-in"><span>Activos</span><strong><?= $activeUsers ?></strong></div>
        <div class="inventory-kpi"><span>Ventas</span><strong><?= $vendorUsers ?></strong></div>
        <div class="inventory-kpi"><span>Consulta</span><strong><?= $consultUsers ?></strong></div>
    </div>
</section>

<article class="card inventory-catalog-card">
    <header class="section-head">
        <div>
            <h3>Cuentas registradas</h3>
            <p>Administra accesos, roles y estado operativo de cada cuenta interna.</p>
        </div>
    </header>

    <div class="inventory-catalog-toolbar">
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Nombre, usuario, correo o rol..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="users-directory"
            >
        </label>
        <div class="inventory-filter-meta">
            <strong data-table-filter-count data-table-filter-target="users-directory" data-table-filter-label="usuarios"><?= $totalUsers ?> usuarios</strong>
            <small>registrados</small>
        </div>
    </div>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-table-filter-rows="users-directory" data-table-pagination data-table-pagination-size="20">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $managedUser): ?>
                        <?php
                        $userId = (int) $managedUser['id'];
                        $isActive = (int) ($managedUser['is_active'] ?? 1) === 1;
                        $isAdminRow = ($managedUser['role'] ?? '') === 'administrator';
                        $roleLabel = $roleLabels[$managedUser['role'] ?? ''] ?? ($managedUser['role'] ?? '');
                        $haystack = strtolower(trim(
                            ((string) ($managedUser['name'] ?? '')) . ' '
                            . ((string) ($managedUser['username'] ?? '')) . ' '
                            . ((string) ($managedUser['email'] ?? '')) . ' '
                            . $roleLabel . ' '
                            . ($isActive ? 'activo' : 'desactivado')
                        ));
                        ?>
                        <tr data-filter-search="<?= e($haystack) ?>">
                            <td data-label="Nombre"><?= e($managedUser['name'] ?? '') ?></td>
                            <td data-label="Usuario"><?= e($managedUser['username'] ?? '') ?></td>
                            <td data-label="Correo"><?= e($managedUser['email'] ?? 'Sin correo') ?></td>
                            <td data-label="Rol"><?= e($roleLabels[$managedUser['role'] ?? ''] ?? ($managedUser['role'] ?? '')) ?></td>
                            <td data-label="Estado">
                                <span class="badge <?= $isActive ? 'badge-ok' : 'badge-danger' ?>">
                                    <?= $isActive ? 'Activo' : 'Desactivado' ?>
                                </span>
                            </td>
                            <td data-label="Creado"><?= e(substr((string) ($managedUser['created_at'] ?? ''), 0, 10)) ?></td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <button type="button" class="btn btn-sm btn-outline" data-modal-open="user-edit-<?= $userId ?>">Editar</button>
                                <?php if (!$isAdminRow): ?>
                                    <form method="post" action="<?= e(app_url('/settings/users/' . $userId . '/status')) ?>" class="document-action-form">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm <?= $isActive ? 'btn-danger-soft' : 'btn-outline' ?>">
                                            <?= $isActive ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">Aun no hay usuarios registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="empty-state" data-table-filter-empty="users-directory" hidden>No hay usuarios que coincidan con la busqueda.</div>
    </div>
</article>

<!-- Modal: crear usuario -->
<div class="modal-shell" data-modal="user-create-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="user-create-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Usuario</span>
                <h3 id="user-create-title">Crear usuario</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>

        <form method="post" action="<?= e(app_url('/settings/users')) ?>" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre
                <input name="name" required placeholder="Nombre visible del usuario">
            </label>
            <label>Usuario
                <input name="username" required placeholder="usuario.sistema">
            </label>
            <label>Correo
                <input type="email" name="email" placeholder="correo@empresa.com">
            </label>
            <label>Rol
                <select name="role" required>
                    <option value="vendor">Ventas</option>
                    <option value="general_consultant">Consulta general</option>
                </select>
            </label>
            <label class="col-span-2">Contrasena inicial
                <input type="password" name="password" required minlength="6" placeholder="Minimo 6 caracteres">
            </label>
            <small class="col-span-2 pos-meta-hint">Solo perfiles de ventas y consulta. Los administradores no se crean ni se desactivan desde este modulo.</small>
            <button class="btn col-span-2">Crear usuario</button>
        </form>
    </div>
</div>

<?php foreach ($users ?? [] as $managedUser): ?>
    <?php
    $userId = (int) $managedUser['id'];
    $isAdminRow = ($managedUser['role'] ?? '') === 'administrator';
    ?>
    <div class="modal-shell" data-modal="user-edit-<?= $userId ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="user-edit-title-<?= $userId ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Usuario</span>
                    <h3 id="user-edit-title-<?= $userId ?>">Editar <?= e($managedUser['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="<?= e(app_url('/settings/users/' . $userId)) ?>" class="form two-cols">
                <?= csrf_field() ?>
                <label>Nombre
                    <input name="name" value="<?= e($managedUser['name'] ?? '') ?>" required>
                </label>
                <label>Usuario
                    <input name="username" value="<?= e($managedUser['username'] ?? '') ?>" required>
                </label>
                <label>Correo
                    <input type="email" name="email" value="<?= e($managedUser['email'] ?? '') ?>">
                </label>
                <label>Rol
                    <select name="role" <?= $isAdminRow ? 'disabled' : '' ?>>
                        <option value="vendor" <?= ($managedUser['role'] ?? '') === 'vendor' ? 'selected' : '' ?>>Ventas</option>
                        <option value="general_consultant" <?= ($managedUser['role'] ?? '') === 'general_consultant' ? 'selected' : '' ?>>Consulta general</option>
                        <?php if ($isAdminRow): ?>
                            <option value="administrator" selected>Administrador</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($isAdminRow): ?>
                        <small>El rol de administrador no se cambia desde este modulo.</small>
                    <?php endif; ?>
                </label>
                <label class="col-span-2">Nueva contrasena
                    <input type="password" name="password" minlength="6" placeholder="Deja vacio para mantener la actual">
                </label>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
