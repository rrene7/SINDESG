<?php
declare(strict_types=1);

$statusLabels = [
    'prepared' => 'Preparada',
    'authorized' => 'Lista para retirar',
    'dispatched' => 'En tránsito',
    'received' => 'Recibida',
    'cancelled' => 'Cancelada',
];
?>
<section class="distribution-hero">
    <div>
        <span class="eyebrow">FASE 8 · TRANSFERENCIA INSTITUCIONAL</span>
        <h1>Distribución Almacén Central → P4</h1>
        <p>Prepare, autorice, despache y reciba transferencias sin perder la trazabilidad del inventario durante el tránsito.</p>
    </div>
</section>

<section class="distribution-stats">
    <article><span>Preparadas</span><strong><?= e($distributionStats['prepared']) ?></strong></article>
    <article><span>Listas para retirar</span><strong><?= e($distributionStats['authorized']) ?></strong></article>
    <article><span>En tránsito</span><strong><?= e($distributionStats['in_transit']) ?></strong><small><?= e(number_format((float) $distributionStats['transit_stock'], 0, '.', ',')) ?> unidades</small></article>
    <article><span>Recibidas</span><strong><?= e($distributionStats['received']) ?></strong></article>
</section>

<section class="distribution-panel">
    <div class="distribution-section-head">
        <div>
            <span class="eyebrow">LISTAS PARA PREPARAR</span>
            <h2>Solicitudes aprobadas</h2>
        </div>
    </div>

    <?php if ($readyRequests === []): ?>
        <div class="distribution-empty compact"><p>No hay solicitudes aprobadas pendientes de distribución.</p></div>
    <?php else: ?>
        <div class="distribution-ready-grid">
            <?php foreach ($readyRequests as $request): ?>
                <a href="<?= e(sindesg_url('?module=distribution&action=new&request_id=' . (int) $request['id'])) ?>">
                    <span><strong><?= e($request['request_code']) ?></strong><small><?= e($request['p4_code'] . ' · ' . ($request['p4_name'] ?: 'P4')) ?></small></span>
                    <span><strong><?= e(number_format((float) $request['remaining_quantity'], 3, '.', ',')) ?></strong><small><?= e((int) $request['remaining_lines']) ?> renglones pendientes</small></span>
                    <b>Preparar →</b>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="distribution-panel">
    <div class="distribution-section-head">
        <div><span class="eyebrow">TRAZABILIDAD</span><h2>Transferencias</h2></div>
    </div>

    <?php if ($distributions === []): ?>
        <div class="distribution-empty"><p>Aún no se han preparado transferencias.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead><tr><th>Transferencia</th><th>Solicitud</th><th>Destino</th><th>Renglones</th><th>Cantidad</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($distributions as $row): ?>
                    <tr>
                        <td><a class="distribution-link" href="<?= e(sindesg_url('?module=distribution&action=show&id=' . (int) $row['id'])) ?>"><?= e($row['transfer_code']) ?></a></td>
                        <td><?= e($row['request_code']) ?></td>
                        <td><?= e($row['p4_code'] . ' · ' . ($row['p4_name'] ?: 'P4')) ?></td>
                        <td><?= e((int) $row['item_count']) ?></td>
                        <td><?= e(number_format((float) $row['total_quantity'], 3, '.', ',')) ?></td>
                        <td><span class="distribution-status <?= e($row['status']) ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
