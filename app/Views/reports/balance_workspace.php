<?php
$sumSection = static fn (array $rows): float => array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) ($row['amount'] ?? 0), 0.0);
$assetsTotal = $sumSection($balance['assets'] ?? []);
$liabilitiesTotal = $sumSection($balance['liabilities'] ?? []);
$equityTotal = $sumSection($balance['equity'] ?? []);
?>

<section class="page-header">
    <div>
        <span class="eyebrow">Finanzas</span>
        <h2>Balance general</h2>
        <p>Vista de activos, pasivos y patrimonio calculada con el comportamiento del periodo.</p>
    </div>
    <div class="header-summary">
        <div><span>Activos</span><strong><?= money($assetsTotal) ?></strong></div>
        <div><span>Pasivos</span><strong><?= money($liabilitiesTotal) ?></strong></div>
        <div><span>Patrimonio</span><strong><?= money($equityTotal) ?></strong></div>
    </div>
</section>

<article class="card">
    <header class="section-head">
        <div>
            <h3>Filtro</h3>
            <p>Ajusta el periodo y genera el balance en PDF cuando lo necesites.</p>
        </div>
        <div class="report-tools">
            <a class="btn btn-secondary" href="/reports/balance-sheet/pdf?from=<?= e($from) ?>&to=<?= e($to) ?>" target="_blank" rel="noopener noreferrer">PDF</a>
            <a class="btn btn-outline" href="/reports">Reportes</a>
        </div>
    </header>

    <form method="get" action="/reports/balance-sheet" class="form inline-form">
        <label>Desde<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn">Consultar</button>
    </form>
</article>

<section class="balance-grid">
    <article class="balance-card">
        <h3>Activos</h3>
        <?php if (!empty($balance['assets'])): ?>
            <?php foreach ($balance['assets'] as $row): ?>
                <div class="balance-row">
                    <span><?= e($row['name']) ?></span>
                    <strong><?= money($row['amount']) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Sin datos de activos.</p>
        <?php endif; ?>
        <div class="balance-total">
            <span>Total activos</span>
            <strong><?= money($assetsTotal) ?></strong>
        </div>
    </article>

    <article class="balance-card">
        <h3>Pasivos</h3>
        <?php if (!empty($balance['liabilities'])): ?>
            <?php foreach ($balance['liabilities'] as $row): ?>
                <div class="balance-row">
                    <span><?= e($row['name']) ?></span>
                    <strong><?= money($row['amount']) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Sin datos de pasivos.</p>
        <?php endif; ?>
        <div class="balance-total">
            <span>Total pasivos</span>
            <strong><?= money($liabilitiesTotal) ?></strong>
        </div>
    </article>

    <article class="balance-card">
        <h3>Patrimonio</h3>
        <?php if (!empty($balance['equity'])): ?>
            <?php foreach ($balance['equity'] as $row): ?>
                <div class="balance-row">
                    <span><?= e($row['name']) ?></span>
                    <strong><?= money($row['amount']) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Sin datos de patrimonio.</p>
        <?php endif; ?>
        <div class="balance-total">
            <span>Total patrimonio</span>
            <strong><?= money($equityTotal) ?></strong>
        </div>
    </article>
</section>
