<?php
$roleLabels = [
    'administrator' => 'Administrador',
    'vendor' => 'Ventas',
    'general_consultant' => 'Consulta general',
];
$roleLabel = $roleLabels[$user['role'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string) ($user['role'] ?? '')));
$createdAt = (string) ($user['created_at'] ?? '');
$updatedAt = (string) ($user['updated_at'] ?? '');
$initials = (static function (string $value): string {
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') { continue; }
        $out .= strtoupper(substr($part, 0, 1));
        if (strlen($out) >= 2) { break; }
    }
    return $out !== '' ? $out : 'US';
})((string) ($user['name'] ?? $user['username'] ?? ''));
?>
<section class="page-header">
    <div>
        <span class="eyebrow">Mi cuenta</span>
        <h2>Mi perfil</h2>
        <p>Actualiza tus datos personales, tu acceso al sistema y tu contrasena.</p>
    </div>
    <div class="profile-identity-card">
        <span class="profile-avatar-lg"><?= e($initials) ?></span>
        <div>
            <strong><?= e((string) ($user['name'] ?? '')) ?></strong>
            <small><?= e($roleLabel) ?> &middot; @<?= e((string) ($user['username'] ?? '')) ?></small>
            <?php if ($createdAt !== ''): ?>
                <small class="profile-meta">Miembro desde <?= e($createdAt) ?></small>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="grid two">
    <article class="card card-feature">
        <header class="section-head">
            <div>
                <h3>Datos personales</h3>
                <p>Estos datos identifican tu cuenta dentro del sistema.</p>
            </div>
        </header>

        <form method="post" action="<?= e(app_url('/profile')) ?>" class="form two-cols">
            <?= csrf_field() ?>
            <label>Nombre
                <input name="name" required value="<?= e((string) ($user['name'] ?? '')) ?>" placeholder="Tu nombre completo">
            </label>
            <label>Usuario
                <input name="username" required value="<?= e((string) ($user['username'] ?? '')) ?>" placeholder="usuario.sistema">
            </label>
            <label class="col-span-2">Correo
                <input type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="correo@empresa.com">
            </label>

            <div class="col-span-2 profile-section-divider">
                <strong>Cambiar contrasena</strong>
                <small>Deja los campos vacios si no quieres cambiarla.</small>
            </div>

            <label class="col-span-2">Contrasena actual
                <input type="password" name="current_password" autocomplete="current-password" placeholder="Requerida solo si vas a cambiar la clave">
            </label>
            <label>Nueva contrasena
                <input type="password" name="new_password" autocomplete="new-password" minlength="6" placeholder="Minimo 6 caracteres">
            </label>
            <label>Repetir nueva
                <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="6" placeholder="Vuelve a escribirla">
            </label>

            <div class="col-span-2 actions-row">
                <button class="btn">Guardar cambios</button>
                <a class="btn btn-outline" href="<?= e(app_url('/dashboard')) ?>">Volver</a>
            </div>
        </form>
    </article>

    <article class="card">
        <header class="section-head">
            <div>
                <h3>Resumen de la cuenta</h3>
                <p>Datos generales asociados a tu sesion.</p>
            </div>
        </header>
        <div class="stack-list">
            <div class="stack-row">
                <span>Rol</span>
                <strong><?= e($roleLabel) ?></strong>
            </div>
            <div class="stack-row">
                <span>Estado</span>
                <strong><?= ((int) ($user['is_active'] ?? 1) === 1) ? 'Activo' : 'Inactivo' ?></strong>
            </div>
            <?php if ($createdAt !== ''): ?>
                <div class="stack-row">
                    <span>Creada</span>
                    <strong><?= e($createdAt) ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($updatedAt !== ''): ?>
                <div class="stack-row">
                    <span>Ultima actualizacion</span>
                    <strong><?= e($updatedAt) ?></strong>
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-tips">
            <strong>Recomendaciones</strong>
            <ul>
                <li>Usa una contrasena distinta a la de otros sistemas.</li>
                <li>Mantenla con al menos 6 caracteres, mezclando letras y numeros.</li>
                <li>Si ves accesos sospechosos, cambia la clave de inmediato.</li>
            </ul>
        </div>
    </article>
</section>
