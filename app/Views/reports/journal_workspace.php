<?php
$totalDebit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['debit'], 0.0);
$totalCredit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['credit'], 0.0);
?>

<section class="page-header">
    <div>
        <span class="eyebrow">Finanzas</span>
        <h2>Libro diario</h2>
        <p>Movimientos consolidados de compras, gastos y ventas para el periodo consultado.</p>
    </div>
    <div class="header-summary">
        <div><span>Asientos</span><strong><?= count($rows) ?></strong></div>
        <div><span>Debe</span><strong><?= money($totalDebit) ?></strong></div>
        <div><span>Haber</span><strong><?= money($totalCredit) ?></strong></div>
    </div>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Filtro</h3>
            <p>Consulta el rango y exporta el libro diario a PDF.</p>
        </div>
        <div class="report-tools">
            <a class="btn btn-secondary" href="/reports/journal/pdf?from=<?= e($from) ?>&to=<?= e($to) ?>" target="_blank" rel="noopener noreferrer">PDF</a>
            <a class="btn btn-outline" href="/reports/ledger?from=<?= e($from) ?>&to=<?= e($to) ?>">Libro mayor</a>
        </div>
    </header>

    <form method="get" action="/reports/journal" class="form inline-form">
        <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn">Consultar</button>
    </form>
</article>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Detalle del libro diario</h3>
            <p>Referencia cruzada para auditoria rapida.</p>
        </div>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th>Referencia</th>
                    <th>Debe</th>
                    <th>Haber</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['trans_date']) ?></td>
                            <td><?= e($row['source']) ?></td>
                            <td><?= e($row['reference']) ?></td>
                            <td><?= money($row['debit']) ?></td>
                            <td><?= money($row['credit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">No hay movimientos para el periodo seleccionado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
