<?php
declare(strict_types=1);
?>
<section class="report-print-head no-print">
    <div>
        <span class="eyebrow">SINDESG · REPORTE</span>
        <h1><?= e($reportMeta['title']) ?></h1>
        <p><?= e($reportMeta['description']) ?></p>
    </div>
    <button class="primary-button" type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
</section>

<section class="report-print-document">
    <header>
        <div>
            <strong>POLICÍA NACIONAL DE PANAMÁ</strong>
            <span>Sistema de Inventario y Despacho de Servicios Generales</span>
        </div>
        <div>
            <strong><?= e($reportMeta['title']) ?></strong>
            <span>Generado <?= e($reportGeneratedAt) ?></span>
        </div>
    </header>

    <div class="report-print-summary">
        <span>Renglones: <?= e(sindesg_units((int) $reportTotals['rows'])) ?></span>
        <span>Total referencial: <?= e(sindesg_units((float) $reportTotals['quantity'])) ?></span>
        <?php if ($reportFilters['date_from'] !== '' || $reportFilters['date_to'] !== ''): ?>
            <span>Período: <?= e($reportFilters['date_from'] ?: 'inicio') ?> → <?= e($reportFilters['date_to'] ?: 'actual') ?></span>
        <?php endif; ?>
    </div>

    <?php if ($reportRows === []): ?>
        <p>No hay resultados para los filtros aplicados.</p>
    <?php else: ?>
        <table class="report-print-table">
            <thead>
            <tr>
                <?php foreach ($reportColumns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reportRows as $row): ?>
                <tr>
                    <?php foreach (array_keys($reportColumns) as $key): ?>
                        <td><?= e((string) ($row[$key] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <footer>
        <span>Documento generado desde SINDESG.</span>
        <span>El stock mostrado se deriva del kardex institucional.</span>
    </footer>
</section>
