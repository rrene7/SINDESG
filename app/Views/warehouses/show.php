<?php
declare(strict_types=1);

$isCentral = $warehouse['warehouse_type'] === 'CENTRAL';
$isTransit = $warehouse['warehouse_type'] === 'TRANSIT';
$isP4 = $warehouse['warehouse_type'] === 'P4';
$currentName = $isP4
    ? ($currentUnit['official_name'] ?? $warehouse['organization_unit_name_snapshot'] ?? $warehouse['name'])
    : $warehouse['name'];
$heroLabel = $isCentral ? 'ALMACÉN CENTRAL' : ($isTransit ? 'INVENTARIO EN TRÁNSITO' : 'P4 · RED LOGÍSTICA');
?>
<section class="warehouse-detail-hero">
    <div>
        <span class="eyebrow"><?= e($heroLabel) ?></span>
        <h1><?= e($currentName) ?></h1>
        <p><?= (int) $warehouse['is_active'] === 1 ? 'Almacén activo' : 'Almacén inactivo' ?></p>
    </div>
    <div class="warehouse-detail-stock">
        <strong><?= e(sindesg_units((float) $warehouse['stock_total'])) ?></strong>
        <small>unidades en este almacén</small>
    </div>
</section>

<section class="warehouse-detail-grid">
    <article class="warehouse-panel">
        <span class="eyebrow">IDENTIFICACIÓN</span>
        <h2><?= e($isP4 ? 'Vínculo institucional' : 'Datos del almacén') ?></h2>
        <dl class="warehouse-dl">
            <div><dt>Referencia para auditoría</dt><dd><details><summary>Ver código interno</summary><?= e($warehouse['code']) ?></details></dd></div>
            <div><dt>Tipo</dt><dd><?= e($warehouse['warehouse_type']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= (int) $warehouse['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></dd></div>
            <div><dt>Variantes con stock</dt><dd><?= e((int) $warehouse['variants_with_stock']) ?></dd></div>

            <?php if ($isP4): ?>
                
                <div><dt>Unidad actual</dt><dd><?= e($currentUnit['official_name'] ?? 'RRHH no disponible') ?></dd></div>
                <div><dt>Tipo actual</dt><dd><?= e($currentUnit['unit_type'] ?? '—') ?></dd></div>
                <div><dt>Código actual</dt><dd><?= e($currentUnit['system_code'] ?? $currentUnit['institutional_code'] ?? '—') ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if ($isP4): ?>
            <div class="warehouse-snapshot">
                <strong>Snapshot al crear el P4</strong>
                <span><?= e($warehouse['organization_unit_name_snapshot'] ?: '—') ?></span>
                <small><?= e($warehouse['organization_unit_code_snapshot'] ?: 'Sin código') ?> · <?= e($warehouse['organization_unit_type_snapshot'] ?: 'Sin tipo') ?></small>
            </div>
        <?php endif; ?>
    </article>

    <aside class="warehouse-panel">
        <?php if ($isCentral): ?>
            <span class="eyebrow">ROL INSTITUCIONAL</span>
            <h2>Centro de recepción y distribución</h2>
            <p class="warehouse-muted">Las recepciones y la producción interna ingresan aquí. Las transferencias mueven inventario desde este almacén hacia los P4.</p>
        <?php elseif ($isTransit): ?>
            <span class="eyebrow">ROL LOGÍSTICO</span>
            <h2>Inventario temporal en tránsito</h2>
            <p class="warehouse-muted">Este almacén técnico conserva las unidades desde que salen del Almacén Central hasta que el P4 confirma la recepción. No es un P4 y no se vincula a RRHH.</p>
        <?php else: ?>
            <span class="eyebrow">RESPONSABLE</span>
            <h2><?= e($warehouse['responsible_label_snapshot'] ?: 'Sin responsable') ?></h2>
            <dl class="warehouse-dl single">
                
                <div><dt>Cédula al crear</dt><dd><?= e($warehouse['responsible_document_snapshot'] ?: '—') ?></dd></div>
                <div><dt>Posición al crear</dt><dd><?= e($warehouse['responsible_position_snapshot'] ?: '—') ?></dd></div>
                <div><dt>Rango al crear</dt><dd><?= e($warehouse['responsible_rank_snapshot'] ?: '—') ?></dd></div>
            </dl>

            <?php if ($currentResponsible): ?>
                <div class="warehouse-current-responsible">
                    <strong>Dato actual en RRHH</strong>
                    <span><?= e(trim((string) $currentResponsible['first_name'] . ' ' . (string) $currentResponsible['last_name'])) ?></span>
                    <small><?= e($currentResponsible['rank_name'] ?: 'Sin rango') ?> · <?= e($currentResponsible['organization_unit_name'] ?: 'Sin unidad vigente') ?></small>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(sindesg_url('?module=warehouses&action=toggle')) ?>" class="warehouse-toggle-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="warehouse_id" value="<?= e($warehouse['id']) ?>">
                <input type="hidden" name="active" value="<?= (int) $warehouse['is_active'] === 1 ? '0' : '1' ?>">
                <button class="<?= (int) $warehouse['is_active'] === 1 ? 'warehouse-danger-button' : 'primary-button' ?>" type="submit">
                    <?= (int) $warehouse['is_active'] === 1 ? 'Inactivar P4' : 'Reactivar P4' ?>
                </button>
            </form>
        <?php endif; ?>
    </aside>
</section>

<?php if (!$isCentral && !empty($warehouse['notes'])): ?>
    <section class="warehouse-note"><strong>Observaciones</strong><p><?= e($warehouse['notes']) ?></p></section>
<?php endif; ?>

<section class="warehouse-panel">
    <div class="warehouse-section-head">
        <div>
            <span class="eyebrow">EXISTENCIA POR ALMACÉN</span>
            <h2>Stock de <?= e($currentName) ?></h2>
        </div>
        <span class="warehouse-stock-rule">Derivado exclusivamente del kardex.</span>
    </div>

    <?php if ($warehouseStock === []): ?>
        <div class="warehouse-empty compact"><p>Este almacén todavía no tiene existencias registradas.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Categoría</th><th>Artículo</th><th>Variante</th><th>Talla</th><th>Marca</th><th>Existencia</th><th>Último movimiento</th></tr>
                </thead>
                <tbody>
                <?php foreach ($warehouseStock as $row): ?>
                    <tr>
                        <td><?= e($row['category_name']) ?></td>
                        <td><a class="warehouse-link" href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int) $row['product_id'])) ?>"><?= e($row['product_name']) ?></a></td>
                        <td><?= e(($row['size_name'] ?? '') ? 'Talla ' . $row['size_name'] : 'Presentación estándar') ?></td>
                        <td><?= e($row['size_name'] ?: '—') ?></td>
                        <td><?= e($row['brand_name'] ?: '—') ?></td>
                        <td class="warehouse-qty"><?= e(sindesg_units((float) $row['quantity_on_hand'])) ?></td>
                        <td><?= e($row['last_movement_at'] ? date('d/m/Y H:i', strtotime((string) $row['last_movement_at'])) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($isP4): ?>
<section class="warehouse-panel" id="solicitudes-p4">
    <div class="warehouse-section-head">
        <div>
            <span class="eyebrow">SEGUIMIENTO DEL P4</span>
            <h2>¿En qué etapa están nuestras solicitudes?</h2>
            <p>Estado basado en los registros reales del Central, transferencias y entregas. Sin ubicación GPS.</p>
        </div>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">Ver todas las solicitudes</a>
    </div>
    <?php if (empty($p4RequestTracking)): ?>
        <div class="warehouse-empty compact">
            <p>Este P4 todavía no registra solicitudes. Cuando las envíe al Central aparecerán aquí.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table class="p4-tracking-table">
                <thead><tr>
                    <th>Solicitud</th><th>Fecha</th><th>Nominales</th>
                    <th>Solicitado</th><th>Aprobado</th><th>Recibido P4</th>
                    <th>Entregado</th><th>Etapa actual</th><th>Detalle</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p4RequestTracking as $requestRow): ?>
                    <?php
                    $stage = match (true) {
                        (string) $requestRow['status'] === 'draft' => 'Borrador del P4',
                        (string) $requestRow['status'] === 'submitted' => 'Enviada al Central',
                        (string) $requestRow['status'] === 'in_review' => 'En revisión del Central',
                        (string) $requestRow['status'] === 'rejected' => 'Rechazada por Central',
                        (float) $requestRow['delivered_total'] > 0 => 'Entregas individuales registradas',
                        (float) $requestRow['received_total'] > 0 => 'Recepción confirmada en P4',
                        (float) $requestRow['dispatched_total'] > 0 => 'Despachada / en tránsito',
                        (string) ($requestRow['last_transfer_status'] ?? '') === 'authorized'
                            => 'Transferencia autorizada',
                        (string) ($requestRow['last_transfer_status'] ?? '') === 'prepared'
                            => 'En preparación del despacho',
                        (float) $requestRow['approved_total'] > 0 => 'Aprobada por Central',
                        default => 'Pendiente de gestión del Central',
                    };
                    ?>
                    <tr>
                        <td><strong>Solicitud de dotación</strong></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $requestRow['requested_at']))) ?></td>
                        <td><?= (int) $requestRow['nominal_count'] ?></td>
                        <td><?= e(sindesg_units((float) $requestRow['requested_total'])) ?></td>
                        <td><?= e(sindesg_units((float) $requestRow['approved_total'])) ?></td>
                        <td><?= e(sindesg_units((float) $requestRow['received_total'])) ?></td>
                        <td><?= e(sindesg_units((float) $requestRow['delivered_total'])) ?></td>
                        <td><strong><?= e($stage) ?></strong></td>
                        <td><a class="warehouse-link" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $requestRow['id'] . '#seguimiento-p4')) ?>">Ver seguimiento →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="warehouse-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses')) ?>">← Almacenes y P4</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Inventario</a>
</div>
