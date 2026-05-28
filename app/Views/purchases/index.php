<!-- Encabezado de la Página -->
<div class="page-title">
    <h2>Compras</h2>
</div>

<div class="grid two">
    
    <!-- Sección: Nuevo Proveedor -->
    <div class="card">
        <h3>Nuevo proveedor</h3>
        <form method="post" action="/purchases/suppliers" class="form two-cols">
            <?= csrf_field() ?>
            
            <label>
                Nombre
                <input name="name" required>
            </label>
            
            <label>
                Documento
                <input name="document">
            </label>
            
            <label>
                Teléfono
                <input name="phone">
            </label>
            
            <label>
                Email
                <input name="email">
            </label>
            
            <label class="col-span-2">
                Dirección
                <textarea name="address"></textarea>
            </label>
            
            <button class="btn col-span-2">Guardar proveedor</button>
        </form>
    </div>

    <!-- Sección: Registrar Compra -->
    <div class="card">
        <h3>Registrar compra</h3>
        <form method="post" action="/purchases" class="form two-cols">
            <?= csrf_field() ?>

            <label>
                Proveedor
                <select name="supplier_id">
                    <?php foreach($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Almacén
                <select name="warehouse_id">
                    <?php foreach($warehouses as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Nro. documento
                <input name="doc_number" required>
            </label>

            <label>
                Fecha
                <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
            </label>

            <label>
                Producto
                <select name="product_id">
                    <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Cantidad
                <input type="number" step="1" min="1" name="quantity" value="1" required>
            </label>

            <label>
                Costo unitario
                <input type="number" step="0.01" name="cost_original" value="0" required>
            </label>

            <label>
                Moneda
                <select name="currency_code">
                    <option><?= e(base_currency()) ?></option>
                    <option><?= e(secondary_currency()) ?></option>
                </select>
            </label>

            <label>
                Tasa
                <input type="number" step="0.0001" name="exchange_rate" value="<?= e($rate['rate'] ?? env('DEFAULT_EXCHANGE_RATE',1)) ?>">
            </label>

            <label class="col-span-2">
                Notas
                <textarea name="notes"></textarea>
            </label>

            <button class="btn col-span-2">Guardar compra</button>
        </form>
    </div>
</div>

<!-- Tabla de Historial de Compras -->
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Documento</th>
                <th>Proveedor</th>
                <th>Almacén</th>
                <th>Moneda</th>
                <th>Tasa</th>
                <th>Total origen</th>
                <th>Total convertido</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($purchases as $p): ?>
                <tr>
                    <td><?= e($p['purchase_date']) ?></td>
                    <td><?= e($p['doc_number']) ?></td>
                    <td><?= e($p['supplier_name']) ?></td>
                    <td><?= e($p['warehouse_name']) ?></td>
                    <td><?= e($p['currency_code']) ?></td>
                    <td><?= money($p['exchange_rate']) ?></td>
                    <td><?= money($p['total_original']) ?></td>
                    <td><?= money($p['total_converted']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
