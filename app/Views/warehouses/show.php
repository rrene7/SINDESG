<?php
declare(strict_types=1);

$isCentral = $warehouse['warehouse_type'] === 'CENTRAL';
$currentName = $isCentral
    ? $warehouse['name']
    : ($currentUnit['official_name'] ?? $warehouse['organization_unit_name_snapshot'] ?? $warehouse['name']);
?>
<section class="warehouse-detail-hero">
    <div>
        <span class="eyebrow"><?= e($isCentral ? 'ALMACÉN CENTRAL' : 'P4 · RED LOGÍSTICA') ?></span>
        <h1><?= e($currentName) ?></h1>
        <p><?= e($warehouse['code']) ?> · <?= (int) $warehouse['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></p>
    </div>
    <div class="warehouse-detail-stock">
        <strong><?= e(number_format((float) $warehouse['stock_total'], 0, '.', ',')) ?></strong>
        <small>unidades en este almacén</small>
    </div>
</section>

<section class="warehouse-detail-grid">
    <article class="warehouse-panel">
        <span class="eyebrow">IDENTIFICACIÓN</span>
        <h2><?= e($isCentral ? 'Datos del almacén' : 'Vínculo institucional') ?></h2>
        <dl class="warehouse-dl">
            <div><dt>Código SINDESG</dt><dd><?= e($warehouse['code']) ?></dd></div>
            <div><dt>Tipo</dt><dd><?= e($warehouse['warehouse_type']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= (int) $warehouse['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></dd></div>
            <div><dt>Variantes con stock</dt><dd><?= e((int) $warehouse['variants_with_stock']) ?></dd></div>

            <?php if (!$isCentral): ?>
                <div><dt>Unidad RRHH ID</dt><dd><?= e($warehouse['organization_unit_id'] ?: '—') ?></dd></div>
                <div><dt>Unidad actual</dt><dd><?= e($currentUnit['official_name'] ?? 'RRHH no disponible') ?></dd></div>
                <div><dt>Tipo actual</dt><dd><?= e($currentUnit['unit_type'] ?? '—') ?></dd></div>
                <div><dt>Código actual</dt><dd><?= e($currentUnit['system_code'] ?? $currentUnit['institutional_code'] ?? '—') ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if (!$isCentral): ?>
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
            <p class="warehouse-muted">Las recepciones y la producción interna ingresan aquí. Las futuras transferencias moverán inventario desde este almacén hacia los P4.</p>
        <?php else: ?>
            <span class="eyebrow">RESPONSABLE</span>
            <h2><?= e($warehouse['responsible_label_snapshot'] ?: 'Sin responsable') ?></h2>
            <dl class="warehouse-dl single">
                <div><dt>Employee ID</dt><dd><?= e($warehouse['responsible_employee_id'] ?: '—') ?></dd></div>
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
                        <td><code><?= e($row['variant_code']) ?></code></td>
                        <td><?= e($row['size_name'] ?: '—') ?></td>
                        <td><?= e($row['brand_name'] ?: '—') ?></td>
                        <td class="warehouse-qty"><?= e(number_format((float) $row['quantity_on_hand'], 3, '.', ',')) ?></td>
                        <td><?= e($row['last_movement_at'] ? date('d/m/Y H:i', strtotime((string) $row['last_movement_at'])) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="warehouse-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses')) ?>">← Almacenes y P4</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Inventario</a>
</div>
