<div class="page-title">
    <h2>Notas de entrega</h2>
</div>

<div class="grid two">
    <div class="card">
        <h3>Registrar nota de entrega</h3>

        <form method="post" action="/delivery-notes" class="form two-cols">
            <?= csrf_field() ?>

            <label>
                Cliente
                <select name="client_id">
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c["id"] ?>"><?= e($c["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Numero
                <input name="note_number" value="<?= e($nextNumber) ?>" required>
            </label>

            <label>
                Fecha
                <input type="date" name="note_date" value="<?= date("Y-m-d") ?>" required>
            </label>

            <label>
                Producto
                <select name="product_id">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p["id"] ?>"><?= e($p["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Almacen
                <select name="warehouse_id">
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w["id"] ?>"><?= e($w["name"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Cantidad
                <input type="number" step="1" min="1" name="quantity" value="1" required>
            </label>

            <label class="col-span-2">
                Notas
                <textarea name="notes"></textarea>
            </label>

            <button class="btn col-span-2">Guardar nota</button>
        </form>
    </div>

    <div class="card">
        <h3>Listado</h3>

        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $n): ?>
                    <tr>
                        <td><?= e($n["note_date"]) ?></td>
                        <td><?= e($n["note_number"]) ?></td>
                        <td><?= e($n["client_name"]) ?></td>
                        <td>
                            <a class="btn btn-sm" href="/delivery-notes/print/<?= $n["id"] ?>">
                                Imprimir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
