<?php
declare(strict_types=1);

$typeLabels = [
    'return' => 'Devolución',
    'exchange' => 'Cambio',
    'replacement' => 'Reposición',
    'deterioration' => 'Deterioro',
    'writeoff' => 'Baja autorizada',
    'loss' => 'Pérdida',
    'shortage' => 'Faltante',
    'surplus' => 'Sobrante',
    'authorized_adjustment' => 'Ajuste autorizado',
    'cycle_count' => 'Conteo cíclico',
];
$statusLabels = ['draft' => 'Borrador', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado'];
?>
<section class="adjustment-hero">
    <div>
        <span class="eyebrow">FASE 10 · MOVIMIENTOS COMPENSATORIOS</span>
        <h1>Cambios, devoluciones, bajas y ajustes</h1>
        <p>Registre incidencias y prepare borradores con documento de soporte. Solo otro usuario ADMIN podrá confirmar movimientos compensatorios en el kardex.</p>
    </div>
    <div class="adjustment-section-head">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=incidents')) ?>">Incidencias reportadas por P4 →</a>
        <a class="primary-button" href="<?= e(sindesg_url('?module=adjustments&action=new')) ?>">+ Nuevo ajuste</a>
    </div>
</section>

<section class="adjustment-stats">
    <article><span>Borradores</span><strong><?= e($adjustmentStats['drafts']) ?></strong><small>Sin afectar inventario</small></article>
    <article><span>Confirmados</span><strong><?= e($adjustmentStats['confirmed']) ?></strong><small>Movimientos auditables</small></article>
    <article><span>Devoluciones / cambios</span><strong><?= e($adjustmentStats['returns_count'] + $adjustmentStats['exchanges_count']) ?></strong><small>Ligados a entregas</small></article>
    <article><span>Salidas por incidencia</span><strong><?= e($adjustmentStats['outgoing_adjustments']) ?></strong><small>Deterioro, baja, pérdida o faltante</small></article>
</section>

<section class="adjustment-panel">
    <div class="adjustment-section-head">
        <div>
            <span class="eyebrow">ACCESOS RÁPIDOS</span>
            <h2>Operaciones de almacén</h2>
            <p>Las devoluciones, cambios y reposiciones se inician desde una entrega individual confirmada para conservar el vínculo con el funcionario.</p>
        </div>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">Ir a despachos</a>
    </div>
    <div class="adjustment-actions-grid">
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=deterioration')) ?>"><strong>Deterioro</strong><span>Salida por artículo inutilizable en almacén.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=writeoff')) ?>"><strong>Baja autorizada</strong><span>Salida respaldada por documento.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=loss')) ?>"><strong>Pérdida</strong><span>Salida por pérdida constatada.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=shortage')) ?>"><strong>Faltante</strong><span>Diferencia negativa detectada.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=surplus')) ?>"><strong>Sobrante</strong><span>Diferencia positiva identificada.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=authorized_adjustment')) ?>"><strong>Ajuste autorizado</strong><span>Entrada o salida según documento.</span></a>
        <a href="<?= e(sindesg_url('?module=adjustments&action=new&type=cycle_count')) ?>"><strong>Conteo cíclico</strong><span>Compara sistema contra conteo físico.</span></a>
    </div>
</section>

<section class="adjustment-panel">
    <div class="adjustment-section-head">
        <div><span class="eyebrow">HISTORIAL</span><h2>Operaciones recientes</h2></div>
    </div>

    <?php if ($adjustments === []): ?>
        <div class="adjustment-empty"><p>Aún no existen operaciones de ajuste.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Código</th><th>Tipo</th><th>Almacén</th><th>Artículo</th><th>Funcionario</th><th>Fecha</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($adjustments as $row): ?>
                    <tr>
                        <td><a class="adjustment-link" href="<?= e(sindesg_url('?module=adjustments&action=show&id=' . (int) $row['id'])) ?>"><?= e($row['adjustment_code']) ?></a></td>
                        <td><?= e($typeLabels[$row['adjustment_type']] ?? $row['adjustment_type']) ?></td>
                        <td><?= e($row['warehouse_name'] ?? 'Almacén institucional') ?></td>
                        <td><strong><?= e($row['product_name']) ?></strong><small class="adjustment-cell-sub">Artículo de dotación</small></td>
                        <td><?= e($row['affected_employee_label_snapshot'] ?: '—') ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['occurred_at']))) ?></td>
                        <td><span class="adjustment-status <?= e($row['status']) ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
