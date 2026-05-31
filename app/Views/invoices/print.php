<div class="card invoice-card">
    <div class="invoice-header">
        <div>
            <h2>Factura #<?= e($invoice['invoice_number'] ?? '') ?></h2>
            <small>
                <?= e($invoice['invoice_date'] ?? '') ?> ·
                <?= e(secondary_currency()) ?>
                <?php if (!empty($invoice['exchange_rate'])): ?>
                    · Tasa: <?= money($invoice['exchange_rate']) ?>
                <?php endif; ?>
            </small>
        </div>

        <div class="invoice-client">
            <strong><?= e($invoice['client_name'] ?? '') ?></strong>
            <?php if (!empty($invoice['client_document'])): ?>
                <small><?= e($invoice['client_document']) ?></small>
            <?php endif; ?>
        </div>
    </div>

    <table class="table invoice-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($invoice['items'] ?? []) as $item): ?>
                <tr>
                    <td><?= e($item['product_name'] ?? '') ?></td>
                    <td class="text-right"><?= money($item['quantity'] ?? 0) ?></td>
                    <td class="text-right"><?= money($item['price_converted'] ?? 0) ?></td>
                    <td class="text-right"><?= money($item['total_converted'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="invoice-totals">
        <p>
            <span>Subtotal</span>
            <strong><?= money($invoice['subtotal_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></strong>
        </p>
        <p>
            <span>Impuesto</span>
            <strong><?= money($invoice['tax_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></strong>
        </p>
        <p class="total-main">
            <span>Total</span>
            <strong><?= money($invoice['total_converted'] ?? 0) ?> <?= e(secondary_currency()) ?></strong>
        </p>
    </div>
</div>
