<?php
$accounts = $accounts ?? [];
$accountsWithMovements = $accountsWithMovements ?? [];
$typeLabels = treasury_account_types();

$total = count($accounts);
$activeCount = count(array_filter($accounts, static fn (array $a): bool => (int) ($a['is_active'] ?? 1) === 1));
$cashCount = count(array_filter($accounts, static fn (array $a): bool => ($a['account_type'] ?? '') === 'cash'));
$bankCount = count(array_filter($accounts, static fn (array $a): bool => ($a['account_type'] ?? '') === 'bank'));
$walletCount = count(array_filter($accounts, static fn (array $a): bool => ($a['account_type'] ?? '') === 'wallet'));

$currencyOptions = [base_currency(), secondary_currency()];
?>
<section class="inventory-shell">
    <header class="inventory-topbar">
        <div class="inventory-topbar-title">
            <h3>Cuentas de tesoreria</h3>
            <small>Define donde esta realmente el dinero: cajas de efectivo, bancos y billeteras. Al cobrar o pagar eliges la cuenta y su moneda manda.</small>
        </div>
        <div class="inventory-topbar-actions">
            <button type="button" class="btn btn-outline btn-sm" data-modal-open="treasury-account-create-modal">+ Nueva cuenta</button>
            <a class="btn btn-outline btn-sm" href="<?= e(app_url('/reports?type=treasury')) ?>">Ver tesoreria</a>
        </div>
    </header>

    <div class="inventory-kpis">
        <div class="inventory-kpi"><span>Cuentas</span><strong><?= $total ?></strong></div>
        <div class="inventory-kpi inventory-kpi-in"><span>Activas</span><strong><?= $activeCount ?></strong></div>
        <div class="inventory-kpi"><span>Cajas</span><strong><?= $cashCount ?></strong></div>
        <div class="inventory-kpi"><span>Bancos</span><strong><?= $bankCount ?></strong></div>
        <div class="inventory-kpi"><span>Billeteras</span><strong><?= $walletCount ?></strong></div>
    </div>
</section>

<article class="card inventory-catalog-card">
    <header class="section-head">
        <div>
            <h3>Cuentas registradas</h3>
            <p>Cada cuenta tiene una moneda fija. El saldo inicial es el que tenias al empezar a usar el sistema.</p>
        </div>
    </header>

    <div class="inventory-catalog-toolbar">
        <label class="inventory-filter inventory-filter-search">
            <span>Buscar</span>
            <input
                type="search"
                placeholder="Nombre, tipo, moneda o codigo..."
                autocomplete="off"
                data-table-filter-input
                data-table-filter-target="treasury-accounts"
            >
        </label>
        <div class="inventory-filter-meta">
            <strong data-table-filter-count data-table-filter-target="treasury-accounts" data-table-filter-label="cuentas"><?= $total ?> cuentas</strong>
            <small>registradas</small>
        </div>
    </div>

    <div class="table-wrap table-wrap-mobile-slider">
        <table class="table mobile-cards">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Moneda</th>
                    <th>Saldo inicial</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-table-filter-rows="treasury-accounts" data-table-pagination data-table-pagination-size="20">
                <?php if ($accounts): ?>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $accountId = (int) $account['id'];
                        $type = treasury_account_type_normalize((string) ($account['account_type'] ?? 'bank'));
                        $typeLabel = account_type_label($type);
                        $isActive = (int) ($account['is_active'] ?? 1) === 1;
                        $haystack = strtolower(trim(
                            ((string) ($account['account_code'] ?? '')) . ' '
                            . ((string) ($account['account_name'] ?? '')) . ' '
                            . $typeLabel . ' '
                            . ((string) ($account['currency_code'] ?? '')) . ' '
                            . ($isActive ? 'activa' : 'inactiva')
                        ));
                        ?>
                        <tr data-filter-search="<?= e($haystack) ?>">
                            <td data-label="Codigo"><?= e((string) ($account['account_code'] ?? '')) ?></td>
                            <td data-label="Nombre"><strong><?= e((string) ($account['account_name'] ?? '')) ?></strong></td>
                            <td data-label="Tipo"><span class="badge badge-neutral"><?= e($typeLabel) ?></span></td>
                            <td data-label="Moneda"><?= e((string) ($account['currency_code'] ?? '')) ?></td>
                            <td data-label="Saldo inicial"><?= money($account['opening_balance'] ?? 0) ?> <?= e((string) ($account['currency_code'] ?? '')) ?></td>
                            <td data-label="Estado">
                                <span class="badge <?= $isActive ? 'badge-ok' : 'badge-danger' ?>">
                                    <?= $isActive ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td data-label="Acciones" class="actions-row document-actions">
                                <button type="button" class="btn btn-sm btn-outline" data-modal-open="treasury-account-edit-<?= $accountId ?>">Editar</button>
                                <form method="post" action="<?= e(app_url('/settings/treasury-accounts/' . $accountId . '/status')) ?>" class="document-action-form" onsubmit="return confirm('Se actualizara el estado de la cuenta. Las cuentas inactivas no aparecen al cobrar/pagar, pero su historial se conserva.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= $isActive ? 'btn-danger-soft' : 'btn-outline' ?>">
                                        <?= $isActive ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">Aun no hay cuentas de tesoreria. Crea la primera para empezar a cobrar y pagar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="empty-state" data-table-filter-empty="treasury-accounts" hidden>No hay cuentas que coincidan con la busqueda.</div>
    </div>
</article>

<!-- Modal: nueva cuenta -->
<div class="modal-shell" data-modal="treasury-account-create-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="treasury-account-create-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Tesoreria</span>
                <h3 id="treasury-account-create-title">Nueva cuenta</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>

        <form method="post" action="<?= e(app_url('/settings/treasury-accounts')) ?>" class="form two-cols">
            <?= csrf_field() ?>
            <label class="col-span-2">Nombre
                <input name="account_name" required placeholder="Ej: BNC - Banco Nacional de Credito (Bs)">
            </label>
            <label>Tipo
                <select name="account_type" required>
                    <?php foreach ($typeLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === 'bank' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Moneda
                <select name="currency_code" required>
                    <?php foreach ($currencyOptions as $cur): ?>
                        <option value="<?= e($cur) ?>"><?= e($cur) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="col-span-2">Saldo inicial (opcional)
                <input type="number" step="0.01" name="opening_balance" value="0">
                <small>Lo que tenias en esta cuenta al empezar a usar el sistema, en su moneda.</small>
            </label>
            <button class="btn col-span-2">Crear cuenta</button>
        </form>
    </div>
</div>

<!-- Modales: editar cuenta -->
<?php foreach ($accounts as $account): ?>
    <?php
    $accountId = (int) $account['id'];
    $accType = treasury_account_type_normalize((string) ($account['account_type'] ?? 'bank'));
    $accCurrency = (string) ($account['currency_code'] ?? base_currency());
    $locked = in_array($accountId, $accountsWithMovements, true);
    ?>
    <div class="modal-shell" data-modal="treasury-account-edit-<?= $accountId ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="treasury-account-edit-title-<?= $accountId ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Cuenta</span>
                    <h3 id="treasury-account-edit-title-<?= $accountId ?>">Editar <?= e((string) ($account['account_name'] ?? '')) ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>

            <form method="post" action="<?= e(app_url('/settings/treasury-accounts/' . $accountId)) ?>" class="form two-cols">
                <?= csrf_field() ?>
                <label class="col-span-2">Nombre
                    <input name="account_name" value="<?= e((string) ($account['account_name'] ?? '')) ?>" required>
                </label>
                <label>Tipo
                    <select name="account_type" required>
                        <?php foreach ($typeLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $value === $accType ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Moneda
                    <select name="currency_code" <?= $locked ? 'disabled' : '' ?>>
                        <?php foreach ($currencyOptions as $cur): ?>
                            <option value="<?= e($cur) ?>" <?= $cur === $accCurrency ? 'selected' : '' ?>><?= e($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($locked): ?>
                        <small>La moneda no se puede cambiar: la cuenta ya tiene movimientos registrados.</small>
                    <?php endif; ?>
                </label>
                <label class="col-span-2">Saldo inicial
                    <input type="number" step="0.01" name="opening_balance" value="<?= e((string) ($account['opening_balance'] ?? 0)) ?>">
                    <small>Tambien puedes ajustarlo desde el reporte de tesoreria (saldos iniciales / conciliacion).</small>
                </label>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
