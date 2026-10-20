<?php
declare(strict_types=1);

$totalOutput = array_sum(array_map(static fn (array $row): float => (float) $row['quantity'], $productionOutputs));
$totalInput = array_sum(array_map(static fn (array $row): float => (float) $row['quantity'], $productionInputs));
$statusLabel = $productionOrder['status'] === 'confirmed' ? 'Confirmada' : ucfirst((string) $productionOrder['status']);
?>
<section class="production-detail-hero">
    <div>
        <span class="eyebrow">ORDEN <?= e($productionOrder['production_code']) ?></span>
        <h1><?= e($productionOrder['internal_lot_code']) ?></h1>
        <p>Producción interna de Servicios Generales · <?= e(date('d/m/Y H:i', strtotime((string) $productionOrder['produced_at']))) ?></p>
    </div>
    <div class="production-detail-status">
        <span class="production-status <?= e($productionOrder['status']) ?>"><?= e($statusLabel) ?></span>
        <strong><?= e(sindesg_units($totalOutput)) ?></strong>
        <small>cantidad producida</small>
    </div>
</section>

<section class="production-detail-grid">
    <article class="production-info-panel">
        <span class="eyebrow">TRAZABILIDAD</span>
        <h2>Datos de la orden</h2>
        <dl>
            <div><dt>Orden</dt><dd><?= e($productionOrder['production_code']) ?></dd></div>
            <div><dt>Lote interno</dt><dd><?= e($productionOrder['internal_lot_code']) ?></dd></div>
            <div><dt>Almacén</dt><dd><?= e($productionOrder['warehouse_name']) ?></dd></div>
            <div><dt>Responsable</dt><dd><?= e($productionOrder['responsible_label']) ?></dd></div>
            <div><dt>Producto terminado</dt><dd><?= e(sindesg_units($totalOutput)) ?></dd></div>
            <div><dt>Insumos registrados</dt><dd><?= e(sindesg_units($totalInput)) ?></dd></div>
        </dl>
        <?php if (!empty($productionOrder['notes'])): ?>
            <div class="production-notes"><strong>Observaciones</strong><p><?= e($productionOrder['notes']) ?></p></div>
        <?php endif; ?>
    </article>

    <aside class="production-confirm-panel">
        <?php if ($productionOrder['status'] === 'draft'): ?>
            <span class="eyebrow">PASO PENDIENTE</span>
            <h2>Confirmar producción</h2>
            <p>SINDESG validará primero el stock de los insumos. Si todo está disponible, descontará los materiales y registrará el producto terminado como entrada.</p>
            <div class="production-warning">La confirmación es transaccional: si falta un insumo, no se aplicará ningún movimiento.</div>
            <form method="post" action="<?= e(sindesg_url('?module=production&action=confirm')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="production_order_id" value="<?= e($productionOrder['id']) ?>">
                <button class="primary-button confirm-production" type="submit">Confirmar producción y actualizar inventario</button>
            </form>
        <?php else: ?>
            <span class="eyebrow">KARDEX ACTUALIZADO</span>
            <h2>Producción contabilizada</h2>
            <p>La orden ya generó sus movimientos de salida e ingreso. Volver a confirmar no duplicará el inventario.</p>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Ver inventario</a>
        <?php endif; ?>
    </aside>
</section>

<section class="production-panel">
    <div class="production-section-head"><div><span class="eyebrow">SALIDA DE TALLER</span><h2>Producto terminado</h2></div></div>
    <div class="responsive-table">
        <table>
            <thead><tr><th>Categoría</th><th>Artículo</th><th>Código SINDESG</th><th>Talla</th><th>Marca</th><th>Cantidad</th><th>Observación</th></tr></thead>
            <tbody>
            <?php foreach ($productionOutputs as $row): ?>
                <tr>
                    <td><?= e($row['category_name']) ?></td><td><?= e($row['product_name']) ?></td><td><?= e(($row['size_name'] ?? '') ? 'Talla ' . $row['size_name'] : 'Presentación estándar') ?></td>
                    <td><?= e($row['size_name'] ?: '—') ?></td><td><?= e($row['brand_name'] ?: '—') ?></td>
                    <td class="production-qty output"><?= e(sindesg_units((float) $row['quantity'])) ?></td><td><?= e($row['notes'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="production-panel">
    <div class="production-section-head"><div><span class="eyebrow">CONSUMO</span><h2>Insumos utilizados</h2></div></div>
    <?php if ($productionInputs === []): ?>
        <div class="production-empty compact"><p>Esta orden no registró consumo de insumos.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead><tr><th>Categoría</th><th>Insumo</th><th>Código SINDESG</th><th>Talla</th><th>Marca</th><th>Cantidad</th><th>Observación</th></tr></thead>
                <tbody>
                <?php foreach ($productionInputs as $row): ?>
                    <tr>
                        <td><?= e($row['category_name']) ?></td><td><?= e($row['product_name']) ?></td><td><code><?= e($row['variant_code']) ?></code></td>
                        <td><?= e($row['size_name'] ?: '—') ?></td><td><?= e($row['brand_name'] ?: '—') ?></td>
                        <td class="production-qty input"><?= e(sindesg_units((float) $row['quantity'])) ?></td><td><?= e($row['notes'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="production-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=production')) ?>">← Producción</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Inventario</a>
</div>
