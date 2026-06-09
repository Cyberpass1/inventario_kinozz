<div class="auth-card">
    <div class="auth-head">
        <span class="auth-logo-ring">
            <img src="<?= e(asset_url('img/Logo_System.png')) ?>" alt="Logo del sistema" class="auth-logo" width="72" height="72">
        </span>
        <span class="auth-kicker">Acceso seguro</span>
        <h1><?= e(env("APP_NAME", "Sistema de Inventario")) ?></h1>
        <p class="auth-sub">Ingresa tus credenciales para entrar al panel de inventario.</p>
    </div>

    <?php if ($msg = flash("error")): ?>
        <div class="auth-error" role="alert">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="/login" class="auth-form">
        <?= csrf_field() ?>

        <label class="auth-field">
            <span>Usuario</span>
            <input type="text" name="username" placeholder="Tu usuario" autocomplete="username" autofocus required>
        </label>

        <label class="auth-field">
            <span>Clave</span>
            <span class="auth-password">
                <input type="password" name="password" placeholder="Tu clave" autocomplete="current-password" required data-password-input>
                <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar clave" aria-pressed="false">
                    <svg class="auth-eye" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="auth-eye-off" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden>
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </span>
        </label>

        <button class="auth-submit" type="submit">
            Entrar al sistema
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </button>
    </form>

    <p class="auth-note">Acceso interno solo para usuarios autorizados.</p>
</div>

<style>
/* =========================================================
   Login: tarjeta glass unica y centrada sobre la imagen de
   fondo (.guest-body). Estilos scopeados a .auth-card.
   ========================================================= */
.auth-card {
    width: min(440px, 100%);
    display: grid;
    gap: 1.35rem;
    padding: clamp(1.75rem, 4vw, 2.6rem);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 28px;
    background: linear-gradient(165deg, rgba(15, 23, 42, 0.66), rgba(15, 23, 42, 0.5));
    box-shadow: 0 30px 70px rgba(7, 13, 22, 0.45);
    -webkit-backdrop-filter: blur(22px);
    backdrop-filter: blur(22px);
    color: #f8fafc;
    animation: authFade .5s cubic-bezier(.2, .8, .2, 1) both;
}

.auth-head { display: grid; justify-items: center; text-align: center; gap: 0.35rem; }

.auth-logo-ring {
    display: grid;
    place-items: center;
    width: 96px;
    height: 96px;
    margin-bottom: 0.5rem;
    border-radius: 26px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.10);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.auth-logo { display: block; width: 72px; height: auto; object-fit: contain; filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.35)); }

.auth-kicker {
    color: #5eead4;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.auth-card h1 {
    margin: 0.1rem 0 0;
    font-size: clamp(1.5rem, 2.4vw, 1.95rem);
    line-height: 1.12;
    letter-spacing: -0.02em;
    color: #ffffff;
}

.auth-sub {
    max-width: 34ch;
    margin: 0.25rem auto 0;
    color: rgba(226, 232, 240, 0.78);
    font-size: 0.92rem;
    line-height: 1.6;
}

.auth-error {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 0.95rem;
    border: 1px solid rgba(248, 113, 113, 0.4);
    border-radius: 14px;
    background: rgba(220, 38, 38, 0.18);
    color: #fecaca;
    font-size: 0.88rem;
    line-height: 1.4;
}

.auth-error svg { flex-shrink: 0; }

.auth-form { display: grid; gap: 0.95rem; }

.auth-field { display: grid; gap: 0.42rem; }

.auth-field > span {
    color: rgba(226, 232, 240, 0.88);
    font-size: 0.84rem;
    font-weight: 650;
    letter-spacing: 0.01em;
}

.auth-card input[type="text"],
.auth-card input[type="password"] {
    width: 100%;
    height: 52px;
    padding: 0 1rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.94);
    color: #0f172a;
    font-size: 0.95rem;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.auth-card input::placeholder { color: #94a3b8; }

.auth-card input:focus {
    background: #ffffff;
    border-color: rgba(15, 118, 110, 0.55);
    box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.22);
}

.auth-password { position: relative; display: block; }
.auth-password input { padding-right: 3rem; }

.auth-password-toggle {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    padding: 0;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: color 0.18s ease, background 0.18s ease;
}

.auth-password-toggle:hover { color: #0f766e; background: rgba(15, 118, 110, 0.1); }
.auth-password-toggle:focus-visible { outline: 2px solid rgba(15, 118, 110, 0.6); outline-offset: 1px; }
/* El atributo `hidden` no aplica a <svg> (namespace SVG); se oculta por CSS. */
.auth-password-toggle svg { grid-area: 1 / 1; display: block; }
.auth-password-toggle svg[hidden] { display: none; }

.auth-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    height: 54px;
    margin-top: 0.3rem;
    border: 0;
    border-radius: 15px;
    background: linear-gradient(135deg, #0f766e, #115e59);
    color: #ffffff;
    font-size: 0.96rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    cursor: pointer;
    box-shadow: 0 16px 30px rgba(15, 118, 110, 0.32);
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

.auth-submit:hover { transform: translateY(-1px); box-shadow: 0 20px 36px rgba(15, 118, 110, 0.4); filter: brightness(1.04); }
.auth-submit:active { transform: translateY(0); }
.auth-submit svg { transition: transform 0.18s ease; }
.auth-submit:hover svg { transform: translateX(3px); }

.auth-note {
    margin: 0;
    color: rgba(226, 232, 240, 0.62);
    font-size: 0.8rem;
    line-height: 1.5;
    text-align: center;
}

@keyframes authFade { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) { .auth-card { animation: none; } .auth-submit, .auth-submit svg { transition: none; } }

@media (max-width: 420px) {
    .auth-logo-ring { width: 80px; height: 80px; }
    .auth-logo { width: 60px; }
}
</style>

<script>
(function () {
    "use strict";
    var toggle = document.querySelector("[data-password-toggle]");
    var input = document.querySelector("[data-password-input]");
    if (!toggle || !input) { return; }

    var eye = toggle.querySelector(".auth-eye");
    var eyeOff = toggle.querySelector(".auth-eye-off");

    toggle.addEventListener("click", function () {
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        toggle.setAttribute("aria-pressed", show ? "true" : "false");
        toggle.setAttribute("aria-label", show ? "Ocultar clave" : "Mostrar clave");
        if (eye) { eye.hidden = show; }
        if (eyeOff) { eyeOff.hidden = !show; }
        input.focus();
    });
})();
</script>
