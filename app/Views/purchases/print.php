<div class="card">
    <h2>Compra <?= e($purchase['doc_number'] ?? '') ?></h2>
    <p><strong>Empresa:</strong> <?= e(company()['name']) ?> | <strong>RIF:</strong> <?= e(company()['rif']) ?></p>
    <p><strong>Proveedor:</strong> <?= e($purchase['supplier_name'] ?? '') ?> | <strong>Documento:</strong> <?= e($purchase['supplier_document'] ?? '') ?></p>
    <p><strong>Fecha:</strong> <?= e($purchase['purchase_date'] ?? '') ?> | <strong>Moneda:</strong> <?= e($purchase['currency_code'] ?? '') ?> | <strong>Tasa:</strong> <?= money($purchase['exchange_rate'] ?? 0) ?></p>
    <table class="table">
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Costo</th><th>Total</th></tr></thead>
        <tbody><?php foreach (($purchase['items'] ?? []) as $item): ?><tr><td><?= e($item['product_name']) ?></td><td><?= money($item['quantity']) ?></td><td><?= money($item['cost_original']) ?></td><td><?= money($item['total_original']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <div class="totals"><p><strong>Total original: <?= money($purchase['total_original'] ?? 0) ?> <?= e($purchase['currency_code'] ?? '') ?></strong></p><p><strong>Total convertido (<?= e(base_currency()) ?>): <?= money($purchase['total_converted'] ?? 0) ?></strong></p></div>
</div>
