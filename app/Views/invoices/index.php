<section class="page-header">
    <div class="page-title">
        <h2>Facturación</h2>
    </div>
</section>

<section class="grid two">
    <article class="card">
        <header class="card-header">
            <h3>Nuevo cliente</h3>
        </header>

        <form method="post" action="/invoices/clients" class="form two-cols">
            <?= csrf_field() ?>

            <label>
                <span>Nombre</span>
                <input type="text" name="name" required>
            </label>

            <label>
                <span>Documento</span>
                <input type="text" name="document">
            </label>

            <label>
                <span>Teléfono</span>
                <input type="text" name="phone">
            </label>

            <label>
                <span>Email</span>
                <input type="email" name="email">
            </label>

            <label class="col-span-2">
                <span>Dirección</span>
                <textarea name="address"></textarea>
            </label>

            <button class="btn col-span-2" type="submit">
                Guardar cliente
            </button>
        </form>
    </article>

    <article class="card">
        <header class="card-header">
            <h3>Registrar factura</h3>
        </header>

        <form method="post" action="/invoices" class="form two-cols">
            <?= csrf_field() ?>

            <label>
                <span>Cliente</span>
                <select name="client_id">
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Número</span>
                <input type="text" name="invoice_number" value="<?= e($nextNumber) ?>" required>
            </label>

            <label>
                <span>Fecha</span>
                <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
            </label>

            <label>
                <span>Producto</span>
                <select name="product_id">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Almacén</span>
                <select name="warehouse_id">
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w['id'] ?>">
                            <?= e($w['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Cantidad</span>
                <input type="number" step="1" min="1" name="quantity" value="1" required>
            </label>

            <label>
                <span>Precio unitario</span>
                <input type="number" step="0.01" name="price_original" value="0" required>
            </label>

            <label>
                <span>Moneda</span>
                <select name="currency_code">
                    <option><?= e(base_currency()) ?></option>
                    <option><?= e(secondary_currency()) ?></option>
                </select>
            </label>

            <label>
                <span>Tasa</span>
                <input
                    type="number"
                    step="0.0001"
                    name="exchange_rate"
                    value="<?= e($rate['rate'] ?? env('DEFAULT_EXCHANGE_RATE', 1)) ?>"
                >
            </label>

            <label class="col-span-2">
                <span>Notas</span>
                <textarea name="notes"></textarea>
            </label>

            <button class="btn col-span-2" type="submit">
                Guardar factura
            </button>
        </form>
    </article>
</section>

<section class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Moneda</th>
                    <th>Tasa</th>
                    <th>Total original</th>
                    <th>Total convertido</th>
                    <th>Acción</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><?= e($i['invoice_date']) ?></td>
                        <td><?= e($i['invoice_number']) ?></td>
                        <td><?= e($i['client_name']) ?></td>
                        <td><?= e($i['currency_code']) ?></td>
                        <td><?= money($i['exchange_rate']) ?></td>
                        <td><?= money($i['total_original']) ?></td>
                        <td><?= money($i['total_converted']) ?></td>
                        <td>
                            <a class="btn btn-sm" href="/invoices/print/<?= $i['id'] ?>">
                                Imprimir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
