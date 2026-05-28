<?php
$endingBalance = $rows ? (float) end($rows)['balance'] : 0.0;
?>

<section class="page-header">
    <div>
        <span class="eyebrow">Finanzas</span>
        <h2>Libro mayor</h2>
        <p>Seguimiento del saldo acumulado a medida que entran ventas, compras y gastos.</p>
    </div>
    <div class="header-summary">
        <div><span>Asientos</span><strong><?= count($rows) ?></strong></div>
        <div><span>Saldo final</span><strong><?= money($endingBalance) ?></strong></div>
        <div><span>Hasta</span><strong><?= e($to) ?></strong></div>
    </div>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Filtro</h3>
            <p>Genera el mayor por fechas y exportalo como PDF cuando lo necesites.</p>
        </div>
        <div class="report-tools">
            <a class="btn btn-secondary" href="/reports/ledger/pdf?from=<?= e($from) ?>&to=<?= e($to) ?>" target="_blank" rel="noopener noreferrer">PDF</a>
            <a class="btn btn-outline" href="/reports/balance-sheet?from=<?= e($from) ?>&to=<?= e($to) ?>">Balance</a>
        </div>
    </header>

    <form method="get" action="/reports/ledger" class="form inline-form">
        <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn">Consultar</button>
    </form>
</article>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Detalle del mayor</h3>
            <p>Saldo acumulado luego de cada movimiento.</p>
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
                    <th>Saldo</th>
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
                            <td class="<?= (float) $row['balance'] < 0 ? 'ledger-negative' : 'ledger-positive' ?>"><?= money($row['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">No hay datos disponibles para ese rango.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
