<?php
declare(strict_types=1);

$statusLabels = [
    'draft' => 'Borrador',
    'confirmed' => 'Confirmado',
    'cancelled' => 'Cancelado',
];
?>
<section class="delivery-hero">
    <div>
        <span class="eyebrow">FASE 9 · CIERRE DE LA CADENA LOGÍSTICA</span>
        <h1>Despacho individual P4 → funcionario</h1>
        <p>Controle la entrega final, descuente el inventario del P4 y conserve el historial de dotación del funcionario.</p>
    </div>
    <a class="primary-button" href="<?= e(sindesg_url('?module=deliveries&action=new')) ?>">+ Nuevo despacho</a>
</section>

<section class="delivery-stats">
    <article><span>Pendientes nominales</span><strong><?= e($deliveryStats['pending_nominal']) ?></strong><small>Recibidos en P4</small></article>
    <article><span>Borradores</span><strong><?= e($deliveryStats['drafts']) ?></strong><small>Sin afectar inventario</small></article>
    <article><span>Confirmados</span><strong><?= e($deliveryStats['confirmed']) ?></strong><small>Entregas finales</small></article>
    <article><span>Unidades entregadas</span><strong><?= e(number_format((float) $deliveryStats['delivered_quantity'], 0, '.', ',')) ?></strong><small>Histórico confirmado</small></article>
</section>

<section class="delivery-panel">
    <div class="delivery-section-head">
        <div>
            <span class="eyebrow">PENDIENTES NOMINALES</span>
            <h2>Prendas ya recibidas para funcionarios específicos</h2>
            <p>Estas unidades están reservadas dentro del P4 y no se ofrecen como stock libre para entregas manuales.</p>
        </div>
    </div>

    <?php if ($pendingNominalDeliveries === []): ?>
        <div class="delivery-empty compact"><p>No hay entregas nominales pendientes.</p></div>
    <?php else: ?>
        <div class="nominal-grid">
            <?php foreach ($pendingNominalDeliveries as $line): ?>
                <a href="<?= e(sindesg_url('?module=deliveries&action=new&request_item_id=' . (int) $line['request_item_id'])) ?>">
                    <div class="nominal-person">
                        <strong><?= e($line['beneficiary_label_snapshot']) ?></strong>
                        <small><?= e($line['beneficiary_document_snapshot'] ?: 'Sin cédula histórica') ?></small>
                    </div>
                    <div>
                        <strong><?= e($line['product_name']) ?></strong>
                        <small><?= e($line['variant_code'] . ($line['size_name'] ? ' · Talla ' . $line['size_name'] : '')) ?></small>
                    </div>
                    <div>
                        <strong><?= e(number_format((float) $line['pending_quantity'], 3, '.', ',')) ?></strong>
                        <small>pendientes</small>
                    </div>
                    <div>
                        <strong><?= e($line['p4_code']) ?></strong>
                        <small><?= e($line['request_code'] . ($line['transfer_code'] ? ' · ' . $line['transfer_code'] : '')) ?></small>
                    </div>
                    <b>Entregar →</b>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="delivery-panel">
    <div class="delivery-section-head">
        <div><span class="eyebrow">HISTORIAL OPERATIVO</span><h2>Despachos recientes</h2></div>
    </div>

    <?php if ($deliveries === []): ?>
        <div class="delivery-empty"><p>Aún no existen despachos individuales.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Despacho</th><th>Funcionario</th><th>P4</th><th>Fecha</th><th>Renglones</th><th>Cantidad</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deliveries as $row): ?>
                    <tr>
                        <td><a class="delivery-link" href="<?= e(sindesg_url('?module=deliveries&action=show&id=' . (int) $row['id'])) ?>"><?= e($row['delivery_code']) ?></a></td>
                        <td><strong><?= e($row['recipient_label_snapshot']) ?></strong><small class="delivery-cell-sub"><?= e($row['recipient_document_snapshot'] ?: '—') ?></small></td>
                        <td><?= e($row['p4_code'] . ' · ' . ($row['p4_name'] ?: 'P4')) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['delivered_at']))) ?></td>
                        <td><?= e((int) $row['item_count']) ?></td>
                        <td><?= e(number_format((float) $row['total_quantity'], 3, '.', ',')) ?></td>
                        <td><span class="delivery-status <?= e($row['status']) ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
