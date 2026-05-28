<?php
$roleLabels = [
    'administrator' => 'Administrador',
    'vendor' => 'Ventas',
    'general_consultant' => 'Consulta general',
];
?>
<section class="page-header">
    <div>
        <span class="eyebrow">Usuarios</span>
        <h2>Gestion interna del sistema</h2>
        <p>Solo administracion puede crear, editar, activar o desactivar usuarios operativos del sistema.</p>
    </div>
    <div class="header-summary">
        <div><span>Total usuarios</span><strong><?= count($users ?? []) ?></strong></div>
        <div><span>Activos</span><strong><?= count(array_filter($users ?? [], static fn (array $user): bool => (int) ($user['is_active'] ?? 1) === 1)) ?></strong></div>
        <div><span>Ventas</span><strong><?= count(array_filter($users ?? [], static fn (array $user): bool => ($user['role'] ?? '') === 'vendor')) ?></strong></div>
        <div><span>Consulta</span><strong><?= count(array_filter($users ?? [], static fn (array $user): bool => ($user['role'] ?? '') === 'general_consultant')) ?></strong></div>
    </div>
</section>

<section class="grid two">
    <article class="card card-feature">
        <header class="section-head">
            <div>
                <h3>Crear usuario</h3>
                <p>Puedes crear vendedores y usuarios de consulta. Desde aqui no se crean administradores.</p>
            </div>
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
            <button class="btn col-span-2">Crear usuario</button>
        </form>
    </article>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Politicas del modulo</h3>
                <p>El administrador puede mantener usuarios, pero con protecciones basicas para evitar bloqueos operativos.</p>
            </div>
        </header>

        <div class="stack-list">
            <div class="stack-row">
                <div>
                    <strong>Creacion limitada</strong>
                    <small>Solo se pueden crear perfiles de ventas y consulta general.</small>
                </div>
                <span class="badge badge-ok">Activo</span>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Edicion completa</strong>
                    <small>Puedes cambiar nombre, usuario, correo, rol y contrasena.</small>
                </div>
                <span class="badge badge-neutral">Gestion</span>
            </div>
            <div class="stack-row">
                <div>
                    <strong>Desactivacion segura</strong>
                    <small>No se permiten desactivaciones de administradores desde este modulo.</small>
                </div>
                <span class="badge badge-neutral">Protegido</span>
            </div>
        </div>
    </article>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Usuarios del sistema</h3>
            <p>Administra accesos, roles y estado operativo de cada cuenta interna.</p>
        </div>
    </header>

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
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $managedUser): ?>
                        <?php
                        $userId = (int) $managedUser['id'];
                        $isActive = (int) ($managedUser['is_active'] ?? 1) === 1;
                        $isAdminRow = ($managedUser['role'] ?? '') === 'administrator';
                        ?>
                        <tr>
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
    </div>
</article>

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
