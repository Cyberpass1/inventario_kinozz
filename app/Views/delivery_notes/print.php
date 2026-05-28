<div class="card">
    <h2>Nota de entrega <?= e($note["note_number"] ?? "") ?></h2>

    <p>
        <strong>Empresa:</strong> <?= e(company()["name"]) ?> |
        <strong>RIF:</strong> <?= e(company()["rif"]) ?>
    </p>

    <p>
        <strong>Cliente:</strong> <?= e($note["client_name"] ?? "") ?> |
        <strong>Documento:</strong> <?= e($note["client_document"] ?? "") ?>
    </p>

    <p>
        <strong>Fecha:</strong> <?= e($note["note_date"] ?? "") ?> |
        <strong>Moneda:</strong> <?= e($note["currency_code"] ?? base_currency()) ?> |
        <strong>Tasa:</strong> <?= money($note["exchange_rate"] ?? 0) ?>
    </p>

    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($note["items"] ?? []) as $item): ?>
                <tr>
                    <td><?= e($item["product_name"]) ?></td>
                    <td><?= money($item["quantity"]) ?></td>
                    <td><?= money($item["price_original"] ?? 0) ?></td>
                    <td><?= money($item["total_original"] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Total documento: <?= money($note["total_original"] ?? 0) ?> <?= e($note["currency_code"] ?? base_currency()) ?></strong></p>
        <p><strong>Total convertido (<?= e(base_currency()) ?>): <?= money($note["total_converted"] ?? 0) ?></strong></p>
    </div>
</div>
