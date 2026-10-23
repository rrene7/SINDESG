<?php
declare(strict_types=1);

$receiptContent = \App\Services\ReceiptContentSummary::fromItems($receiptItems);
$totalQuantity = $receiptContent['total_quantity'];
$productCount = $receiptContent['product_count'];
$primaryProduct = $receiptContent['primary_name'];

$statusLabel = $receipt['status'] === 'confirmed' ? 'Confirmada' : ucfirst((string) $receipt['status']);
?>
<section class="receipt-detail-hero">
    <div>
        <span class="eyebrow">RECEPCIÓN <?= e($receipt['receipt_code']) ?></span>
        <h1><?= e($receipt['lot_code']) ?></h1>
        <p><?= e($sourceTypes[$receipt['source_type']] ?? $receipt['source_type']) ?> · <?= e($receipt['supplier_or_origin']) ?></p>
        <div class="receipt-product-identity" aria-label="Artículos contenidos en este lote">
            <span class="eyebrow">ARTÍCULO RECIBIDO<?= $productCount === 1 ? '' : ' / CONTENIDO DEL LOTE' ?></span>
            <?php if ($primaryProduct !== null): ?>
                <h2><?= e($primaryProduct) ?></h2>
                <p><?= (int) $receiptContent['products'][0]['variant_count'] ?> tallas o variantes registradas</p>
            <?php elseif ($productCount > 1): ?>
                <h2><?= (int) $productCount ?> artículos diferentes</h2>
                <ul class="receipt-product-breakdown">
                    <?php foreach ($receiptContent['products'] as $product): ?>
                        <li><strong><?= e($product['name']) ?></strong>
                            <span><?= e(sindesg_units((float) $product['quantity'])) ?> unidades · <?= (int) $product['variant_count'] ?> tallas o variantes</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <h2>Sin artículos registrados</h2>
            <?php endif; ?>
        </div>
    </div>
    <div class="receipt-detail-status">
        <span class="receipt-status <?= e($receipt['status']) ?>"><?= e($statusLabel) ?></span>
        <strong><?= e(sindesg_units($totalQuantity)) ?></strong>
        <small><?= $primaryProduct !== null ? 'Unidades recibidas · ' . e($primaryProduct) : 'Unidades totales del lote' ?></small>
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
            <div><dt>Cédula</dt><dd><?= e($receipt['received_by_document_snapshot'] ?? '—') ?></dd></div>
            <div><dt>Posición</dt><dd><?= e($receipt['received_by_position_snapshot'] ?? '—') ?></dd></div>
            <div><dt>Rango</dt><dd><?= e($receipt['received_by_rank_snapshot'] ?? '—') ?></dd></div>
            <div><dt>Unidad al recibir</dt><dd><?= e($receipt['received_by_unit_name_snapshot'] ?? '—') ?></dd></div>
        </dl>

        <?php if (!empty($receipt['notes'])): ?>
            <div class="receipt-notes">
                <strong>Observaciones</strong>
                <p><?= e($receipt['notes']) ?></p>
            </div>
        <?php endif; ?>
    </article>

    <?php if ($receipt['status'] === 'draft' && sindesg_can('admin.manage')): ?>
    <article class="receipt-info-panel">
        <span class="eyebrow">ADMINISTRACIÓN · BORRADOR</span>
        <h2>Editar datos de recepción</h2>
        <p>Corrija los datos documentales antes de confirmar el ingreso. Esta edición queda auditada y no altera cantidades ni kardex.</p>
        <form method="post" action="<?= e(sindesg_url('?module=receipts&action=update-draft')) ?>" class="receipt-form">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="receipt_id" value="<?= e($receipt['id']) ?>">
            <label><span>Procedencia</span>
                <select name="source_type" required>
                <?php foreach ($sourceTypes as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $receipt['source_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
                </select>
            </label>
            <label><span>Proveedor / origen</span>
                <input type="text" name="supplier_or_origin" maxlength="180" required value="<?= e($receipt['supplier_or_origin']) ?>">
            </label>
            <label><span>Documento soporte</span>
                <input type="text" name="document_number" maxlength="120" value="<?= e($receipt['document_number'] ?? '') ?>">
            </label>
            <label><span>Observaciones</span>
                <textarea name="notes" rows="3" maxlength="5000"><?= e($receipt['notes'] ?? '') ?></textarea>
            </label>
            <button class="primary-button" type="submit">Guardar correcciones del borrador</button>
        </form>
    </article>
    <?php endif; ?>

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

<?php if ($receipt['status'] === 'confirmed'): ?>
<section class="receipt-verification-panel">
    <div class="receipt-section-head">
        <div>
            <span class="eyebrow">VERIFICACIÓN DE INVENTARIO</span>
            <h2>Recepción → kardex → existencia</h2>
        </div>
        <span class="receipt-verification-badge <?= !empty($receiptVerification['all_verified']) ? 'ok' : 'warn' ?>">
            <?= e((int) ($receiptVerification['matched'] ?? 0)) ?>/<?= e((int) ($receiptVerification['expected'] ?? 0)) ?> movimientos verificados
        </span>
    </div>

    <?php if (!empty($receiptVerification['all_verified'])): ?>
        <p class="receipt-verification-copy">
            Cada renglón de esta recepción tiene su movimiento de entrada único en el kardex.
            El stock mostrado corresponde a la existencia actual de la variante en el Almacén Central.
        </p>
    <?php else: ?>
        <div class="receipt-warning">
            La recepción figura confirmada, pero uno o más renglones no coinciden con su movimiento esperado.
            No registre otra recepción para compensar: revise esta trazabilidad antes de continuar.
        </div>
    <?php endif; ?>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Artículo</th>
                <th>Variante</th>
                <th>Esperado</th>
                <th>Movimiento IN</th>
                <th>Stock Central actual</th>
                <th>Estado</th>
                <th>Kardex</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($receiptVerification['rows'] ?? []) as $row): ?>
                <tr>
                    <td><?= e($row['product_name']) ?></td>
                    <td>
                        <?= e(($row['size_name'] ?? '') ? 'Talla ' . $row['size_name'] : 'Presentación estándar') ?>
                        <?php if (!empty($row['size_name'])): ?>
                            <small class="table-subline">Talla <?= e($row['size_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(sindesg_units((float) $row['expected_quantity'])) ?></td>
                    <td>
                        <?php if (!empty($row['movement_id'])): ?>
                            <?= e(sindesg_units((float) $row['movement_quantity'])) ?>
                            <small class="table-subline">#<?= e($row['movement_id']) ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="receipt-qty"><?= e(sindesg_units((float) $row['central_stock'])) ?></td>
                    <td>
                        <span class="receipt-verification-status <?= !empty($row['verified']) ? 'ok' : 'warn' ?>">
                            <?= !empty($row['verified']) ? 'Verificado' : 'Revisar' ?>
                        </span>
                    </td>
                    <td>
                        <a class="receipt-kardex-link"
                           href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int) $row['product_id'])) ?>">
                            Ver artículo →
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="receipt-table-panel">
    <div class="receipt-section-head">
        <div>
            <span class="eyebrow">CONTENIDO</span>
            <h2>Detalle del lote</h2>
        </div>
        <span class="receipt-total-label"><?= e(count($receiptItems)) ?> renglones</span>
    </div>

    <?php if ($receipt['status'] === 'draft' && sindesg_can('admin.manage')): ?>
    <details class="receipt-info-panel">
        <summary><strong>+ Agregar cantidad a una talla existente</strong></summary>
        <p>Para corregir una cantidad omitida en este borrador. Se suma a la cantidad actual sin generar inventario todavía.</p>
        <form method="post" action="<?= e(sindesg_url('?module=receipts&action=increase-draft-quantity')) ?>" class="receipt-form">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="receipt_id" value="<?= e($receipt['id']) ?>">
            <label><span>Talla / variante del lote</span>
                <select name="receipt_item_id" required>
                    <option value="">Seleccione una talla...</option>
                    <?php foreach ($receiptItems as $item): ?>
                        <option value="<?= e($item['id']) ?>"><?= e($item['product_name'] . ' · ' . ($item['size_name'] ?: 'Sin talla') . ' · ' . $item['variant_code']) ?> — actual: <?= e(sindesg_units((float) $item['quantity'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Cantidad adicional omitida</span>
                <input name="added_quantity" type="number" min="0.001" step="0.001" max="999999999.999" required>
            </label>
            <button class="primary-button" type="submit">Sumar cantidad al borrador</button>
        </form>
    </details>
    <?php endif; ?>

    <?php if ($receipt['status'] === 'draft' && sindesg_can('admin.manage')): ?>
    <details class="receipt-info-panel">
        <summary><strong>+ Agregar talla omitida del catálogo al lote</strong></summary>
        <p>Para una talla que existe en SINDESG pero aún no se incluyó en esta recepción. No genera movimientos hasta confirmar el lote.</p>
        <form method="post" action="<?= e(sindesg_url('?module=receipts&action=add-missing-variant')) ?>" class="receipt-form">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="receipt_id" value="<?= e($receipt['id']) ?>">
            <label><span>Artículo y talla omitida</span>
                <select name="variant_id" required>
                    <option value="">Seleccione del catálogo...</option>
                    <?php
                    $existingVariantIds = array_map('intval', array_column($receiptItems, 'variant_id'));
                    foreach ($variantOptions as $option):
                        if (in_array((int) $option['id'], $existingVariantIds, true)) continue;
                    ?>
                        <option value="<?= e($option['id']) ?>"><?= e($option['product_name'] . ' · Talla ' . ($option['size_name'] ?: 'Sin talla') . ' · ' . $option['variant_code'] . ' · ' . ($option['brand_name'] ?: 'Sin marca')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Cantidad de la talla omitida</span>
                <input name="quantity" type="number" min="0.001" step="0.001" required>
            </label>
            <label><span>Observación (opcional)</span>
                <input name="item_notes" maxlength="255" type="text" placeholder="Corrección de captura">
            </label>
            <button class="primary-button" type="submit">Agregar talla al borrador</button>
        </form>
    </details>
    <?php endif; ?>

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
                    <td><?= e(($item['size_name'] ?? '') ? 'Talla ' . $item['size_name'] : 'Presentación estándar') ?></td>
                    <td><?= e($item['size_name'] ?: '—') ?></td>
                    <td><?= e($item['brand_name'] ?: '—') ?></td>
                    <td><?= e($item['institutional_code'] ?: '—') ?></td>
                    <td class="receipt-qty"><?= e(sindesg_units((float) $item['quantity'])) ?></td>
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
