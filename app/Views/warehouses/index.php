<?php
declare(strict_types=1);
?>
<section class="warehouse-hero">
    <div>
        <span class="eyebrow">FASE 6 · RED LOGÍSTICA</span>
        <h1>Almacenes y P4</h1>
        <p>El Almacén Central concentra la recepción institucional. Cada P4 se habilita explícitamente y se vincula a una unidad vigente de RRHH2029.</p>
    </div>
    <?php if (sindesg_role_action_allowed('warehouses', 'new', 'GET', (array) sindesg_user())
        && sindesg_can('warehouses.manage')): ?>
        <a class="primary-button" href="<?= e(sindesg_url('?module=warehouses&action=new')) ?>">+ Habilitar P4</a>
    <?php endif; ?>
</section>

<section class="warehouse-stats">
    <article><span>Almacén Central</span><strong><?= e($warehouseStats['central_active']) ?></strong><small><?= e(sindesg_units((float) $warehouseStats['central_stock'])) ?> unidades</small></article>
    <article><span>P4 activos</span><strong><?= e($warehouseStats['p4_active']) ?></strong><small>Habilitados para operar</small></article>
    <article><span>P4 inactivos</span><strong><?= e($warehouseStats['p4_inactive']) ?></strong><small>Historial conservado</small></article>
    <article><span>Stock en P4</span><strong><?= e(sindesg_units((float) $warehouseStats['p4_stock'])) ?></strong><small>Separado del Central</small></article>
</section>

<?php if (!$rrhhAvailable): ?>
    <div class="warehouse-alert">RRHH2029 no está disponible. Se muestran los snapshots guardados; no es posible crear nuevos P4 hasta recuperar la conexión.</div>
<?php endif; ?>

<section class="warehouse-grid">
    <?php foreach ($warehouses as $warehouse): ?>
        <?php
        $isCentral = $warehouse['warehouse_type'] === 'CENTRAL';
        $isTransit = $warehouse['warehouse_type'] === 'TRANSIT';
        $isP4 = $warehouse['warehouse_type'] === 'P4';
        $currentUnit = $isP4 && !empty($warehouse['organization_unit_id'])
            ? ($currentUnits[(int) $warehouse['organization_unit_id']] ?? null)
            : null;
        $displayName = $isP4
            ? ($currentUnit['official_name'] ?? $warehouse['organization_unit_name_snapshot'] ?? $warehouse['name'])
            : $warehouse['name'];
        $warehouseBadge = $isCentral ? 'CENTRAL' : ($isTransit ? 'TRÁNSITO' : 'P4');
        ?>
        <a class="warehouse-card <?= $isCentral ? 'central' : ($isTransit ? 'transit' : 'p4') ?> <?= (int) $warehouse['is_active'] === 1 ? 'active' : 'inactive' ?>"
           href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $warehouse['id'])) ?>">
            <div class="warehouse-card-head">
                <span class="warehouse-badge"><?= e($warehouseBadge) ?></span>
                <span class="warehouse-state"><?= (int) $warehouse['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
            </div>
            <h2><?= e($displayName) ?></h2>
            <code><?= e($warehouse['code']) ?></code>
            <?php if ($isP4): ?>
                <p><?= e($currentUnit['unit_type'] ?? $warehouse['organization_unit_type_snapshot'] ?? 'Unidad RRHH') ?></p>
            <?php elseif ($isTransit): ?>
                <p>Inventario temporal entre el Almacén Central y los P4</p>
            <?php else: ?>
                <p>Servicios Generales · recepción y distribución institucional</p>
            <?php endif; ?>
            <div class="warehouse-card-metrics">
                <span><strong><?= e(sindesg_units((float) $warehouse['stock_total'])) ?></strong><small>unidades</small></span>
                <span><strong><?= e((int) $warehouse['variants_with_stock']) ?></strong><small>variantes</small></span>
            </div>
        </a>
    <?php endforeach; ?>
</section>
