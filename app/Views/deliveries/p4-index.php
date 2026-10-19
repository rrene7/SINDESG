<?php
declare(strict_types=1);
?>
<section class="delivery-hero compact">
    <div>
        <span class="eyebrow">P4 · ENTREGA NOMINAL</span>
        <h1>Destinatarios pendientes de mi almacén</h1>
        <p>Solo aparecen renglones recibidos y pendientes, vinculados a tus almacenes P4 activos.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=dashboard')) ?>">← Mi panel P4</a>
</section>
<section class="delivery-panel">
    <div class="delivery-section-head"><div>
        <span class="eyebrow">TRAZABILIDAD POR FUNCIONARIO</span>
        <h2>Entregas pendientes</h2>
    </div></div>
    <?php if (empty($nominalQueue)): ?>
        <p>No hay entregas nominales pendientes para este P4.</p>
    <?php else: ?>
        <div class="p4-home-grid">
            <?php foreach ($nominalQueue as $line): ?>
                <article class="p4-step-card">
                    <span class="p4-step-number"><?= e($line['request_code']) ?></span>
                    <h3><?= e($line['beneficiary_label_snapshot'] ?: 'Funcionario') ?></h3>
                    <p><?= e($line['beneficiary_document_snapshot'] ?: 'Sin documento') ?> ·
                       <?= e($line['product_name']) ?> ·
                       <?= e($line['variant_code']) ?>
                       <?= $line['size_name'] ? ' · Talla ' . e($line['size_name']) : '' ?></p>
                    <p>Pendiente: <?= e(number_format((float)$line['pending_quantity'],3,'.',',')) ?></p>
                    <a href="<?= e(sindesg_url('?module=deliveries&action=new&request_item_id=' . (int)$line['request_item_id'])) ?>">Preparar entrega →</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p>Esta lista muestra hasta 100 renglones pendientes, ordenados por identificador. Cada confirmación verifica existencias y cantidades pendientes en el servidor.</p>
</section>
