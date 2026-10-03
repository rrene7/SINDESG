<?php
declare(strict_types=1);
?>
<section class="warehouse-hero">
    <div>
        <span class="eyebrow">FASE 6 · RED LOGÍSTICA</span>
        <h1>Almacenes y P4</h1>
        <p>El Almacén Central concentra la recepción institucional. Cada P4 se habilita explícitamente y se vincula a una unidad vigente de RRHH2029.</p>
    </div>
    <a class="primary-button" href="<?= e(sindesg_url('?module=warehouses&action=new')) ?>">+ Habilitar P4</a>
</section>

<section class="warehouse-stats">
    <article><span>Almacén Central</span><strong><?= e($warehouseStats['central_active']) ?></strong><small><?= e(number_format((float) $warehouseStats['central_stock'], 0, '.', ',')) ?> unidades</small></article>
    <article><span>P4 activos</span><strong><?= e($warehouseStats['p4_active']) ?></strong><small>Habilitados para operar</small></article>
    <article><span>P4 inactivos</span><strong><?= e($warehouseStats['p4_inactive']) ?></strong><small>Historial conservado</small></article>
    <article><span>Stock en P4</span><strong><?= e(number_format((float) $warehouseStats['p4_stock'], 0, '.', ',')) ?></strong><small>Separado del Central</small></article>
</section>

<?php if (!$rrhhAvailable): ?>
    <div class="warehouse-alert">RRHH2029 no está disponible. Se muestran los snapshots guardados; no es posible crear nuevos P4 hasta recuperar la conexión.</div>
<?php endif; ?>

<section class="warehouse-grid">
    <?php foreach ($warehouses as $warehouse): ?>
        <?php
        $isCentral = $warehouse['warehouse_type'] === 'CENTRAL';
        $currentUnit = !$isCentral && !empty($warehouse['organization_unit_id'])
            ? ($currentUnits[(int) $warehouse['organization_unit_id']] ?? null)
            : null;
        $displayName = $isCentral
            ? $warehouse['name']
            : ($currentUnit['official_name'] ?? $warehouse['organization_unit_name_snapshot'] ?? $warehouse['name']);
        ?>
        <a class="warehouse-card <?= $isCentral ? 'central' : '' ?> <?= (int) $warehouse['is_active'] === 1 ? 'active' : 'inactive' ?>"
           href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $warehouse['id'])) ?>">
            <div class="warehouse-card-head">
                <span class="warehouse-badge"><?= e($isCentral ? 'CENTRAL' : 'P4') ?></span>
                <span class="warehouse-state"><?= (int) $warehouse['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
            </div>
            <h2><?= e($displayName) ?></h2>
            <code><?= e($warehouse['code']) ?></code>
            <?php if (!$isCentral): ?>
                <p><?= e($currentUnit['unit_type'] ?? $warehouse['organization_unit_type_snapshot'] ?? 'Unidad RRHH') ?></p>
            <?php else: ?>
                <p>Servicios Generales · recepción y distribución institucional</p>
            <?php endif; ?>
            <div class="warehouse-card-metrics">
                <span><strong><?= e(number_format((float) $warehouse['stock_total'], 0, '.', ',')) ?></strong><small>unidades</small></span>
                <span><strong><?= e((int) $warehouse['variants_with_stock']) ?></strong><small>variantes</small></span>
            </div>
        </a>
    <?php endforeach; ?>
</section>
