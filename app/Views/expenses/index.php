
<section class="process-layout">
    <article class="card card-feature process-main">
        <header class="section-head">
            <div>
                <h3>Registrar gasto</h3>
                <p>Categoria, referencia, moneda y conversion inmediata en un formulario simple y consistente con el resto de la operacion.</p>
            </div>
            <div class="quick-actions">
                <button type="button" class="btn btn-outline" data-modal-open="expense-category-modal">Nueva categoria</button>
                <a class="btn btn-outline" href="/reports?type=expenses">Ver reporte</a>
            </div>
        </header>

        <form method="post" action="/expenses" class="form two-cols process-form" data-calc="expense" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>">
            <?= csrf_field() ?>
            <label>Categoria
                <select name="category_id">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Referencia
                <input name="reference" required placeholder="Pago de servicio, caja chica o compra menor">
            </label>
            <label>Monto
                <input type="number" step="0.01" name="amount_original" value="0" required data-expense-input>
                <small>Escribe el monto en la moneda seleccionada y el sistema calcula su equivalente segun la tasa activa.</small>
            </label>
            <label>Moneda
                <select name="currency_code" data-expense-currency-select>
                    <option value="<?= e(secondary_currency()) ?>" selected><?= e(secondary_currency()) ?></option>
                    <option value="<?= e(base_currency()) ?>"><?= e(base_currency()) ?></option>
                </select>
            </label>
            <label>Metodo de salida
                <select name="payment_method" data-payment-method-select>
                    <?php foreach (payment_method_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === 'cash' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tasa
                <input type="number" step="0.0001" name="exchange_rate" value="<?= e($rate['rate'] ?? default_exchange_rate()) ?>" data-rate-input readonly>
            </label>
            <label class="col-span-2">Descripcion
                <textarea name="description" placeholder="Detalle del gasto, condicion o justificacion interna"></textarea>
            </label>

            <div class="col-span-2 live-panel process-totals">
                <div><span data-expense-original-label>Monto registrado en <?= e(secondary_currency()) ?></span><strong data-expense-original>0,00</strong></div>
                <div><span data-expense-converted-label>Referencia en <?= e(base_currency()) ?></span><strong data-expense-converted>0,00</strong></div>
                <div><span>Tasa de trabajo</span><strong><?= money($rate['rate'] ?? default_exchange_rate()) ?></strong></div>
            </div>

            <button class="btn col-span-2 process-submit">Guardar gasto</button>
        </form>
    </article>

    <aside class="process-aside">
        <article class="card">
            <header class="section-head">
                <div>
                    <h3>Apoyo al registro</h3>
                    <p>Contexto rapido para mantener el criterio al cargar egresos.</p>
                </div>
            </header>

            <div class="stack-list">
                <div class="stack-row">
                    <div>
                        <strong>Categorias activas</strong>
                        <small>Puedes crear una nueva sin abandonar el registro actual.</small>
                    </div>
                    <span class="badge badge-neutral"><?= count($categories) ?></span>
                </div>
                <div class="stack-row">
                    <div>
                        <strong>Moneda secundaria</strong>
                        <small>La conversion usa la tasa vigente del sistema.</small>
                    </div>
                    <span class="badge badge-neutral"><?= e(secondary_currency()) ?></span>
                </div>
                <div class="stack-row">
                    <div>
                        <strong>Analisis posterior</strong>
                        <small>Todo lo cargado alimenta el centro de reportes y el libro diario.</small>
                    </div>
                    <a class="btn btn-sm btn-outline" href="/reports?type=expenses">Ir al reporte</a>
                </div>
            </div>
        </article>
    </aside>
</section>

<details class="card collapsible-card">
    <summary class="collapse-summary">
        <div>
            <h3>Editar categorias de gasto</h3>
            <p>Abre este bloque solo cuando necesites organizar o depurar categorias.</p>
        </div>
        <span class="btn btn-outline btn-sm collapse-summary-action">Mostrar</span>
    </summary>
    <div class="collapse-body">
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

<article class="card">
    <header class="section-head">
        <div>
            <h3>Historial de gastos</h3>
            <p>Filtra el rango y revisa el monto registrado, su consolidado en bolivares y la referencia en dolares desde la misma tabla.</p>
        </div>
    </header>

    <form method="get" action="/expenses" class="form inline-form">
        <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn">Filtrar</button>
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
</article>

<div class="modal-shell" data-modal="expense-category-modal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="expense-category-title">
        <header class="modal-header">
            <div>
                <span class="eyebrow">Categoria</span>
                <h3 id="expense-category-title">Crear categoria de gasto</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </header>
        <form method="post" action="/expenses/categories" class="form">
            <?= csrf_field() ?>
            <label>Nombre<input name="name" required></label>
            <button class="btn">Guardar categoria</button>
        </form>
    </div>
</div>

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
            <form method="post" action="/expenses/<?= (int) $expense['id'] ?>" class="form two-cols process-form" data-calc="expense" data-rate-sync="1" data-rate-url="<?= e(app_url('/rates/by-date')) ?>" data-reference-currency="<?= e(base_currency()) ?>" data-secondary-currency="<?= e(secondary_currency()) ?>">
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
                    <small>Actualiza el monto en la moneda seleccionada y el sistema recalculara su equivalente.</small>
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
