<div class="login-wrapper">
    <div class="login-card">
        <div class="login-side">
            <div class="login-side-inner">
                <span class="login-badge">Sistema de Inventario</span>


              
                <h1><?= e(env("APP_NAME", "Sistema Administrativo")) ?></h1>

                <p class="login-lead">
              
                </p>

                <div class="login-feature-list">
                    <div class="feature-item">
                        <span class="feature-dot"></span>
                        <span>Gestión integral de inventario y movimientos</span>
                    </div>

                    <div class="feature-item">
                        <span class="feature-dot"></span>
                        <span>Control de usuarios y niveles de acceso</span>
                    </div>

                    <div class="feature-item">
                        <span class="feature-dot"></span>
                        <span>Operación multimoneda USD / VES</span>
                    </div>

                    <div class="feature-item">
                        <span class="feature-dot"></span>
                        <span>Reportes operativos y financieros</span>
                    </div>
                </div>

             
            </div>
        </div>

        <div class="login-form">
            <div class="login-form-header">
                <div class="login-brand">
                    <img src="<?= e(asset_url('img/Logo_System.png')) ?>" alt="Logo del Sistema" width="96" height="96" class="login-logo">
                </div>
                <span class="form-kicker">Acceso seguro</span>
                <h2>Iniciar sesión</h2>
                <p>
                    Ingresa tus credenciales para acceder al panel administrativo.
                </p>
            </div>

            <?php if ($msg = flash("error")): ?>
                <div class="alert danger"><?= e($msg) ?></div>
            <?php endif; ?>

            <form method="post" action="/login" class="form login-form-fields">
                <?= csrf_field() ?>

                <label class="field">
                    <span>Usuario</span>
                    <input type="text" name="username" placeholder="Ingresa tu usuario" autocomplete="username" required>
                </label>

                <label class="field">
                    <span>Clave</span>
                    <input type="password" name="password" placeholder="Ingresa tu clave" autocomplete="current-password" required>
                </label>

                <button class="btn btn-block login-btn" type="submit">
                    Entrar al sistema
                </button>
            </form>
            <p class="login-form-note">Acceso interno solo para usuarios autorizados.</p>
        </div>
    </div>
</div>
