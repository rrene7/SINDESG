<?php
declare(strict_types=1);

$totalQuantity = 0.0;
foreach ($receiptItems as $item) {
    $totalQuantity += (float) $item['quantity'];
}

$statusLabel = $receipt['status'] === 'confirmed' ? 'Confirmada' : ucfirst((string) $receipt['status']);
?>
<section class="receipt-detail-hero">
    <div>
        <span class="eyebrow">RECEPCIÓN <?= e($receipt['receipt_code']) ?></span>
        <h1><?= e($receipt['lot_code']) ?></h1>
        <p><?= e($sourceTypes[$receipt['source_type']] ?? $receipt['source_type']) ?> · <?= e($receipt['supplier_or_origin']) ?></p>
    </div>
    <div class="receipt-detail-status">
        <span class="receipt-status <?= e($receipt['status']) ?>"><?= e($statusLabel) ?></span>
        <strong><?= e(number_format($totalQuantity, 0, '.', ',')) ?></strong>
        <small>cantidad total del lote</small>
    </div>
</section>

<section class="receipt-detail-grid">
    <article class="receipt-info-panel">
        <span class="eyebrow">TRAZABILIDAD</span>
        <h2>Datos de ingreso</h2>
        <dl>
            <div><dt>Recepción</dt><dd><?= e($receipt['receipt_code']) ?></dd></div>
            <div><dt>Lote</dt><dd><?= e($receipt['lot_code']) ?></dd></div>
            <div><dt>Fecha de ingreso</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) $receipt['received_at']))) ?></dd></div>
            <div><dt>Almacén</dt><dd><?= e($receipt['warehouse_name']) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e($receipt['document_number'] ?: 'Sin documento') ?></dd></div>
            <div><dt>Recibido por</dt><dd><?= e($receipt['received_by_label']) ?></dd></div>
        </dl>

        <?php if (!empty($receipt['notes'])): ?>
            <div class="receipt-notes">
                <strong>Observaciones</strong>
                <p><?= e($receipt['notes']) ?></p>
            </div>
        <?php endif; ?>
    </article>

    <aside class="receipt-confirm-panel">
        <?php if ($receipt['status'] === 'draft'): ?>
            <span class="eyebrow">PASO PENDIENTE</span>
            <h2>Confirmar ingreso</h2>
            <p>Revise cantidades y variantes. Al confirmar, SINDESG generará los movimientos de entrada y aumentará el inventario del Almacén Central.</p>
            <div class="receipt-warning">
                Una recepción confirmada no se corrige editando el saldo. Cualquier ajuste posterior debe quedar como un nuevo movimiento trazable.
            </div>
            <form method="post" action="<?= e(sindesg_url('?module=receipts&action=confirm')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="receipt_id" value="<?= e($receipt['id']) ?>">
                <button class="primary-button danger-aware" type="submit">Confirmar recepción e ingresar al inventario</button>
            </form>
        <?php else: ?>
            <span class="eyebrow">KARDEX ACTUALIZADO</span>
            <h2>Ingreso contabilizado</h2>
            <p>Esta recepción ya generó sus movimientos de entrada. Volver a confirmar no duplicará el inventario.</p>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Ver inventario</a>
        <?php endif; ?>
    </aside>
</section>

<section class="receipt-table-panel">
    <div class="receipt-section-head">
        <div>
            <span class="eyebrow">CONTENIDO</span>
            <h2>Detalle del lote</h2>
        </div>
        <span class="receipt-total-label"><?= e(count($receiptItems)) ?> renglones</span>
    </div>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Categoría</th>
                <th>Artículo</th>
                <th>Código SINDESG</th>
                <th>Talla</th>
                <th>Marca</th>
                <th>Cód. institucional</th>
                <th>Cantidad</th>
                <th>Observación</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($receiptItems as $item): ?>
                <tr>
                    <td><?= e($item['category_name']) ?></td>
                    <td><?= e($item['product_name']) ?></td>
                    <td><code><?= e($item['variant_code']) ?></code></td>
                    <td><?= e($item['size_name'] ?: '—') ?></td>
                    <td><?= e($item['brand_name'] ?: '—') ?></td>
                    <td><?= e($item['institutional_code'] ?: '—') ?></td>
                    <td class="receipt-qty"><?= e(number_format((float) $item['quantity'], 3, '.', ',')) ?></td>
                    <td><?= e($item['notes'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="receipt-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=receipts')) ?>">← Recepciones</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Inventario</a>
</div>
