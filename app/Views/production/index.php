<?php
declare(strict_types=1);
?>
<section class="production-hero">
    <div>
        <span class="eyebrow">FASE 4 · CONFECCIÓN INTERNA</span>
        <h1>Producción interna</h1>
        <p>Controle lo confeccionado dentro de Servicios Generales y, cuando corresponda, los insumos utilizados para producirlo.</p>
    </div>
    <a class="primary-button" href="<?= e(sindesg_url('?module=production&action=new')) ?>">+ Nueva orden</a>
</section>

<section class="production-stats">
    <article><span>Borradores</span><strong><?= e($productionStats['drafts']) ?></strong><small>No afectan inventario</small></article>
    <article><span>Órdenes confirmadas</span><strong><?= e($productionStats['confirmed']) ?></strong><small>Movimientos contabilizados</small></article>
    <article><span>Producto terminado</span><strong><?= e(number_format((float) $productionStats['output_quantity'], 0, '.', ',')) ?></strong><small>Cantidad producida confirmada</small></article>
</section>

<section class="production-panel">
    <div class="production-section-head">
        <div>
            <span class="eyebrow">TRAZABILIDAD</span>
            <h2>Órdenes recientes</h2>
        </div>
    </div>

    <?php if ($productionOrders === []): ?>
        <div class="production-empty">
            <div class="production-empty-mark">PI</div>
            <h3>Aún no hay producción registrada</h3>
            <p>Cree una orden para registrar prendas u otros artículos confeccionados dentro de Servicios Generales.</p>
            <a class="primary-button" href="<?= e(sindesg_url('?module=production&action=new')) ?>">Registrar primera orden</a>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr>
                    <th>Orden</th>
                    <th>Lote interno</th>
                    <th>Fecha</th>
                    <th>Responsable</th>
                    <th>Productos</th>
                    <th>Producido</th>
                    <th>Insumos</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($productionOrders as $row): ?>
                    <tr>
                        <td><a class="production-link" href="<?= e(sindesg_url('?module=production&action=show&id=' . (int) $row['id'])) ?>"><?= e($row['production_code']) ?></a></td>
                        <td><code><?= e($row['internal_lot_code']) ?></code></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['produced_at']))) ?></td>
                        <td><?= e($row['responsible_label']) ?></td>
                        <td><?= e($row['output_lines']) ?></td>
                        <td><?= e(number_format((float) $row['output_quantity'], 0, '.', ',')) ?></td>
                        <td><?= e($row['input_lines']) ?></td>
                        <td><span class="production-status <?= e($row['status']) ?>"><?= e($row['status'] === 'confirmed' ? 'Confirmada' : 'Borrador') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
