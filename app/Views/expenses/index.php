<?php
$rateValue = (float) ($rate['rate'] ?? default_exchange_rate());
?>

<section class="pos-workspace" data-pos-workspace>
    <form method="post" action="/expenses" class="pos-form" data-calc="expense" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>" data-ajax-form="1">
        <?= csrf_field() ?>

        <header class="pos-topbar">
            <div class="pos-topbar-title">
                <h3>Registrar gasto</h3>
                <small>Categoria, monto y metodo de salida en una sola mesa &middot; <kbd>F2</kbd> guardar &middot; <kbd>/</kbd> enfoca monto</small>
            </div>
            <div class="pos-topbar-actions">
                <a class="btn btn-outline btn-sm" href="/reports?type=expenses" title="Ver reporte">Reporte</a>
            </div>
        </header>

        <div class="pos-grid pos-grid-expense">
            <!-- Columna 1: Categoria + Documento -->
            <aside class="pos-col pos-col-left">
                <section class="pos-card">
                    <div class="pos-card-head">
                        <strong>Categoria</strong>
                        <span class="pos-hint"><?= count($categories) ?> activas</span>
                    </div>
                    <label class="pos-supplier-label">
                        <select name="category_id" class="pos-supplier-select" data-expense-category-select>
                            <option value="">Selecciona o crea una categoria</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="btn btn-outline btn-sm pos-custom-toggle-inline" data-expense-custom-toggle title="Crear categoria nueva">+ Nueva categoria</button>

                    <div class="pos-custom-product" data-expense-custom-panel hidden>
                        <div class="pos-custom-product-head">
                            <strong>Crear categoria nueva</strong>
                            <button type="button" class="pos-custom-close" data-expense-custom-close aria-label="Cerrar">&times;</button>
                        </div>
                        <small>Se creara al guardar el gasto y quedara disponible para futuros registros.</small>
                        <div class="pos-custom-grid">
                            <label class="pos-custom-span">Nombre de la categoria
                                <input type="text" name="custom_category_name" data-expense-custom-name placeholder="Ej. Suministros de oficina" autocomplete="off">
                            </label>
                        </div>
                    </div>
                </section>

                <section class="pos-card">
                    <div class="pos-card-head"><strong>Documento</strong></div>
                    <div class="pos-meta-grid">
                        <label class="pos-meta-span">Fecha
                            <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                        </label>
                        <label class="pos-meta-span">Referencia
                            <input name="reference" required placeholder="Pago de servicio, caja chica o compra menor">
                        </label>
                        <label>Metodo de salida
                            <select name="payment_method" data-payment-method-select>
                                <?php foreach (payment_method_options() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $value === 'cash' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Tasa
                            <input type="number" step="0.0001" name="exchange_rate" value="<?= e((string) $rateValue) ?>" data-rate-input readonly>
                        </label>
                    </div>
                </section>
            </aside>

            <!-- Columna 2: Monto + descripcion -->
            <main class="pos-col pos-col-center">
                <section class="pos-card pos-expense-input-card">
                    <div class="pos-card-head">
                        <strong>Monto del gasto</strong>
                        <span class="pos-hint">Conversion automatica</span>
                    </div>
                    <div class="pos-expense-amount-row">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="amount_original"
                            value="0"
                            required
                            data-expense-input
                            class="pos-expense-amount"
                            autocomplete="off"
                            data-pos-expense-amount
                        >
                        <select name="currency_code" data-expense-currency-select class="pos-expense-currency">
                            <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                            <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                        </select>
                    </div>
                    <small class="pos-expense-hint">Escribe el monto en la moneda seleccionada. El sistema calcula su equivalente segun la tasa del dia.</small>
                </section>

                <section class="pos-card">
                    <div class="pos-card-head"><strong>Descripcion</strong></div>
                    <textarea name="description" class="pos-expense-description" placeholder="Detalle del gasto, condicion o justificacion interna"></textarea>
                </section>
            </main>

            <!-- Columna 3: Totales + Submit (sticky) -->
            <aside class="pos-col pos-col-right">
                <section class="pos-card pos-total-card">
                    <div class="pos-total-hero">
                        <span>Monto registrado</span>
                        <strong data-expense-original>0,00</strong>
                        <em data-expense-original-currency><?= e(secondary_currency()) ?></em>
                    </div>
                    <div class="pos-total-grid">
                        <div class="pos-total-grid-wide"><span data-expense-original-label>Monto registrado en <?= e(secondary_currency()) ?></span><strong data-expense-original-mirror>0,00</strong></div>
                        <div class="pos-total-grid-wide"><span data-expense-converted-label>Referencia en <?= e(base_currency()) ?></span><strong data-expense-converted>0,00</strong></div>
                        <div class="pos-total-grid-wide"><span>Tasa de trabajo</span><strong data-expense-rate-display><?= money($rateValue) ?></strong></div>
                    </div>
                </section>

                <button class="btn pos-submit" type="submit" title="F2">
                    <span>Guardar gasto</span>
                    <kbd>F2</kbd>
                </button>
            </aside>
        </div>
    </form>
</section>

<details class="card pos-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3>Historial de gastos <span class="pos-history-count">(<?= count($expenses) ?>)</span></h3>
            <p>Filtra el rango y revisa monto, consolidado en bolivares y referencia en dolares.</p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>

    <div class="pos-history-body">
        <form method="get" action="/expenses" class="form history-filters-form">
            <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
            <div class="actions-row history-filters-actions">
                <button class="btn btn-outline">Filtrar</button>
                <a class="btn btn-outline" href="/expenses">Limpiar</a>
            </div>
        </form>

        <div class="table-wrap table-wrap-mobile-slider">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Categoria</th>
                        <th>Referencia</th>
                        <th>Descripcion</th>
                        <th>Estado</th>
                        <th>Moneda</th>
                        <th>Metodo</th>
                        <th>Cuenta</th>
                        <th>Tasa</th>
                        <th>Monto registrado</th>
                        <th>Consolidado <?= e(secondary_currency()) ?></th>
                        <th>Referencia <?= e(base_currency()) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-table-pagination data-table-pagination-size="15">
                    <?php if ($expenses): ?>
                        <?php foreach ($expenses as $expense): ?>
                            <?php
                                $expenseCurrency = normalize_currency_code((string) ($expense['currency_code'] ?? secondary_currency()));
                                $expenseRate = (float) ($expense['exchange_rate'] ?? 0);
                                if ($expenseRate <= 0) {
                                    $expenseRate = system_exchange_rate((string) ($expense['expense_date'] ?? date('Y-m-d')));
                                }
                                $expenseAmounts = expense_currency_breakdown(
                                    (float) ($expense['amount_original'] ?? 0),
                                    $expenseCurrency,
                                    $expenseRate
                                );
                            ?>
                            <tr>
                                <td data-label="Fecha"><?= e($expense['expense_date']) ?></td>
                                <td data-label="Categoria"><?= e($expense['category_name']) ?></td>
                                <td data-label="Referencia"><?= e($expense['reference']) ?></td>
                                <td data-label="Descripcion"><?= e($expense['description']) ?></td>
                                <td data-label="Estado">
                                    <span class="badge <?= ($expense['status'] ?? 'active') === 'cancelled' ? 'badge-danger' : 'badge-ok' ?>">
                                        <?= ($expense['status'] ?? 'active') === 'cancelled' ? 'Anulado' : 'Activo' ?>
                                    </span>
                                </td>
                                <td data-label="Moneda"><?= e($expenseCurrency) ?></td>
                                <td data-label="Metodo"><?= e(payment_method_label($expense['payment_method'] ?? 'cash')) ?></td>
                                <td data-label="Cuenta"><?= e($expense['treasury_account_name'] ?? treasury_account_label($expense['payment_method'] ?? 'cash', $expenseCurrency)) ?></td>
                                <td data-label="Tasa"><?= money($expenseRate) ?></td>
                                <td data-label="Monto registrado"><?= money($expenseAmounts['amount_original']) ?> <?= e($expenseCurrency) ?></td>
                                <td data-label="Consolidado <?= e(secondary_currency()) ?>"><?= money($expenseAmounts['amount_consolidated']) ?> <?= e(secondary_currency()) ?></td>
                                <td data-label="Referencia <?= e(base_currency()) ?>"><?= money($expenseAmounts['amount_reference']) ?> <?= e(base_currency()) ?></td>
                                <td data-label="Acciones" class="actions-row document-actions">
                                    <?php if (($expense['status'] ?? 'active') !== 'cancelled'): ?>
                                        <button type="button" class="btn btn-sm btn-outline" data-modal-open="expense-edit-<?= (int) $expense['id'] ?>">Editar</button>
                                        <form method="post" action="/expenses/cancel/<?= $expense['id'] ?>" class="document-action-form" onsubmit="return confirm('Se anulara el gasto y dejara de afectar los reportes.');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-danger-soft">Anular</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" class="empty-state">No hay gastos para el rango seleccionado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

<details class="card pos-history">
    <summary class="pos-history-summary">
        <div class="pos-history-summary-copy">
            <h3>Categorias de gasto <span class="pos-history-count">(<?= count($categories) ?>)</span></h3>
            <p>Crea, edita o depura las categorias. Solo se pueden eliminar las que no tienen gastos asociados.</p>
        </div>
        <span class="pos-history-toggle">
            <span class="pos-history-toggle-show">Mostrar</span>
            <span class="pos-history-toggle-hide">Ocultar</span>
            <span class="pos-history-chevron" aria-hidden="true">&rsaquo;</span>
        </span>
    </summary>
    <div class="pos-history-body">
        <div class="table-wrap table-wrap-mobile-slider">
            <table class="table mobile-cards">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Gastos asociados</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories): ?>
                        <?php foreach ($categories as $category): ?>
                            <?php $usageCount = (int) ($categoryUsage[(int) $category['id']] ?? 0); ?>
                            <tr>
                                <td data-label="Categoria"><?= e($category['name'] ?? '') ?></td>
                                <td data-label="Gastos asociados"><span class="badge badge-neutral"><?= $usageCount ?></span></td>
                                <td data-label="Acciones" class="actions-row">
                                    <button type="button" class="btn btn-sm btn-outline" data-modal-open="expense-category-edit-<?= (int) $category['id'] ?>">Editar</button>
                                    <form method="post" action="/expenses/categories/<?= (int) $category['id'] ?>/delete" onsubmit="return confirm('Solo se eliminara si no tiene gastos asociados.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline" <?= $usageCount > 0 ? 'disabled title="Tiene gastos asociados y no se puede eliminar."' : '' ?>>Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty-state">Todavia no hay categorias de gasto creadas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

<?php foreach ($expenses as $expense): ?>
    <?php if (($expense['status'] ?? 'active') === 'cancelled') {
        continue;
    } ?>
    <div class="modal-shell" data-modal="expense-edit-<?= (int) $expense['id'] ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="expense-edit-title-<?= (int) $expense['id'] ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Gasto</span>
                    <h3 id="expense-edit-title-<?= (int) $expense['id'] ?>">Editar <?= e($expense['reference'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>
            <form method="post" action="/expenses/<?= (int) $expense['id'] ?>" class="form two-cols process-form" data-calc="expense" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>" data-ajax-form="1">
                <?= csrf_field() ?>
                <label>Categoria
                    <select name="category_id">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($expense['category_id'] ?? 0) === (int) ($category['id'] ?? 0) ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha
                    <input type="date" name="expense_date" value="<?= e((string) ($expense['expense_date'] ?? date('Y-m-d'))) ?>" required>
                </label>
                <label>Referencia
                    <input name="reference" required value="<?= e((string) ($expense['reference'] ?? '')) ?>">
                </label>
                <label>Monto
                    <input type="number" step="0.01" name="amount_original" value="<?= e((string) ($expense['amount_original'] ?? 0)) ?>" required data-expense-input>
                </label>
                <label>Moneda
                    <select name="currency_code" data-expense-currency-select>
                        <option value="<?= e(secondary_currency()) ?>" <?= normalize_currency_code((string) ($expense['currency_code'] ?? '')) === normalize_currency_code(secondary_currency()) ? 'selected' : '' ?>><?= e(secondary_currency()) ?></option>
                        <option value="<?= e(base_currency()) ?>" <?= normalize_currency_code((string) ($expense['currency_code'] ?? '')) === normalize_currency_code(base_currency()) ? 'selected' : '' ?>><?= e(base_currency()) ?></option>
                    </select>
                </label>
                <label>Metodo de salida
                    <select name="payment_method" data-payment-method-select>
                        <?php foreach (payment_method_options() as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($expense['payment_method'] ?? 'cash') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tasa
                    <input type="number" step="0.0001" name="exchange_rate" value="<?= e((string) ($expense['exchange_rate'] ?? ($rate['rate'] ?? default_exchange_rate()))) ?>" data-rate-input readonly>
                </label>
                <label class="col-span-2">Descripcion
                    <textarea name="description" placeholder="Detalle del gasto, condicion o justificacion interna"><?= e((string) ($expense['description'] ?? '')) ?></textarea>
                </label>
                <div class="col-span-2 live-panel process-totals">
                    <?php
                        $expenseEditCurrency = normalize_currency_code((string) ($expense['currency_code'] ?? secondary_currency()));
                        $expenseEditAmounts = expense_currency_breakdown(
                            (float) ($expense['amount_original'] ?? 0),
                            $expenseEditCurrency,
                            (float) ($expense['exchange_rate'] ?? ($rate['rate'] ?? default_exchange_rate()))
                        );
                        $expenseEditConvertedLabel = is_bolivar_currency($expenseEditCurrency)
                            ? 'Referencia en ' . base_currency()
                            : 'Consolidado en ' . secondary_currency();
                        $expenseEditConvertedValue = is_bolivar_currency($expenseEditCurrency)
                            ? $expenseEditAmounts['amount_reference']
                            : $expenseEditAmounts['amount_consolidated'];
                    ?>
                    <div><span data-expense-original-label>Monto registrado en <?= e($expenseEditCurrency) ?></span><strong data-expense-original><?= money($expenseEditAmounts['amount_original']) ?></strong></div>
                    <div><span data-expense-converted-label><?= e($expenseEditConvertedLabel) ?></span><strong data-expense-converted><?= money($expenseEditConvertedValue) ?></strong></div>
                    <div><span>Tasa de trabajo</span><strong><?= money((float) ($expense['exchange_rate'] ?? ($rate['rate'] ?? default_exchange_rate()))) ?></strong></div>
                </div>
                <button class="btn col-span-2">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($categories as $category): ?>
    <div class="modal-shell" data-modal="expense-category-edit-<?= (int) $category['id'] ?>" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="expense-category-edit-title-<?= (int) $category['id'] ?>">
            <header class="modal-header">
                <div>
                    <span class="eyebrow">Categoria</span>
                    <h3 id="expense-category-edit-title-<?= (int) $category['id'] ?>">Editar <?= e($category['name'] ?? '') ?></h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </header>
            <form method="post" action="/expenses/categories/<?= (int) $category['id'] ?>" class="form">
                <?= csrf_field() ?>
                <label>Nombre<input name="name" value="<?= e($category['name'] ?? '') ?>" required></label>
                <button class="btn">Guardar cambios</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    "use strict";

    const workspace = document.querySelector("[data-pos-workspace]");
    if (!workspace) return;

    // Panel inline de "Nueva categoria"
    const panel = workspace.querySelector("[data-expense-custom-panel]");
    const toggleBtn = workspace.querySelector("[data-expense-custom-toggle]");
    const closeButtons = workspace.querySelectorAll("[data-expense-custom-close]");
    const nameInput = workspace.querySelector("[data-expense-custom-name]");
    const select = workspace.querySelector("[data-expense-category-select]");

    if (panel && toggleBtn && nameInput && select) {
        const showPanel = () => {
            panel.hidden = false;
            select.value = "";
            window.setTimeout(() => nameInput.focus(), 50);
        };

        const hidePanel = () => {
            panel.hidden = true;
            nameInput.value = "";
        };

        toggleBtn.addEventListener("click", showPanel);
        closeButtons.forEach((btn) => btn.addEventListener("click", hidePanel));

        // Si el usuario vuelve a elegir una categoria del select, oculta el panel y limpia el nombre custom
        select.addEventListener("change", () => {
            if (select.value !== "") {
                hidePanel();
            }
        });

        // Si el usuario escribe en el nombre custom, limpia la seleccion del select
        nameInput.addEventListener("input", () => {
            if (nameInput.value.trim() !== "") {
                select.value = "";
            }
        });
    }

    // Espejar el monto principal en el panel de totales (data-expense-original)
    // El JS global ya actualiza data-expense-original. Tambien actualizamos data-expense-original-mirror y data-expense-original-currency.
    const amountInput = workspace.querySelector("[data-pos-expense-amount]");
    const currencySelect = workspace.querySelector("[data-expense-currency-select]");
    const mirror = workspace.querySelector("[data-expense-original-mirror]");
    const currencyDisplay = workspace.querySelector("[data-expense-original-currency]");
    const originalLabel = workspace.querySelector("[data-expense-original-label]");
    const convertedLabel = workspace.querySelector("[data-expense-converted-label]");
    const originalNode = workspace.querySelector("[data-expense-original]");

    const REF_CURRENCY = "<?= e(base_currency()) ?>";
    const SECONDARY = "<?= e(secondary_currency()) ?>";

    const isBolivar = (code) => {
        const c = String(code || "").toUpperCase();
        return c === "VES" || c === "BS" || c === "VEB" || c === "BSS";
    };

    const formatMoney = (n) => {
        const v = Number(n) || 0;
        return v.toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const syncExpenseDisplays = () => {
        if (!amountInput || !currencySelect) return;
        const amount = parseFloat(amountInput.value) || 0;
        const cur = currencySelect.value || SECONDARY;
        if (currencyDisplay) currencyDisplay.textContent = cur;
        if (mirror && originalNode) mirror.textContent = originalNode.textContent;
        if (originalLabel) originalLabel.textContent = "Monto registrado en " + cur;
        if (convertedLabel) {
            convertedLabel.textContent = isBolivar(cur)
                ? "Referencia en " + REF_CURRENCY
                : "Consolidado en " + SECONDARY;
        }
    };

    if (amountInput) {
        amountInput.addEventListener("input", syncExpenseDisplays);
    }
    if (currencySelect) {
        currencySelect.addEventListener("change", syncExpenseDisplays);
    }
    syncExpenseDisplays();

    // Mantener mirror sincronizado cuando el JS global actualiza data-expense-original
    if (originalNode && mirror) {
        new MutationObserver(() => {
            mirror.textContent = originalNode.textContent;
        }).observe(originalNode, { childList: true, characterData: true, subtree: true });
    }

    // Atajo: "/" enfoca el monto
    document.addEventListener("keydown", (event) => {
        if (event.key !== "/") return;
        const t = event.target;
        if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.tagName === "SELECT" || t.isContentEditable)) return;
        event.preventDefault();
        if (amountInput) {
            amountInput.focus();
            amountInput.select?.();
        }
    });
})();
</script>
