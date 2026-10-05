<?php
declare(strict_types=1);

$statusLabels = [
    'draft' => 'Borrador',
    'confirmed' => 'Confirmado',
    'cancelled' => 'Cancelado',
];

$totalQuantity = array_sum(array_map(
    static fn (array $row): float => (float) $row['quantity'],
    $deliveryItems
));
?>
<section class="delivery-detail-hero">
    <div>
        <span class="eyebrow">DESPACHO <?= e($delivery['delivery_code']) ?></span>
        <h1><?= e($delivery['recipient_label_snapshot']) ?></h1>
        <p><?= e($delivery['recipient_document_snapshot'] ?: 'Sin cédula histórica') ?> · <?= e($delivery['p4_code'] . ' · ' . ($delivery['p4_name'] ?: $delivery['p4_internal_name'])) ?></p>
    </div>
    <div class="delivery-detail-status">
        <span class="delivery-status <?= e($delivery['status']) ?>"><?= e($statusLabels[$delivery['status']] ?? $delivery['status']) ?></span>
        <strong><?= e(number_format($totalQuantity, 3, '.', ',')) ?></strong>
        <small>unidades</small>
    </div>
</section>

<section class="delivery-detail-grid">
    <article class="delivery-panel">
        <span class="eyebrow">COMPROBANTE</span>
        <h2>Datos del despacho</h2>
        <dl class="delivery-dl">
            <div><dt>Código</dt><dd><?= e($delivery['delivery_code']) ?></dd></div>
            <div><dt>Fecha y hora</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) $delivery['delivered_at']))) ?></dd></div>
            <div><dt>P4</dt><dd><?= e($delivery['p4_code']) ?></dd></div>
            <div><dt>Unidad P4</dt><dd><?= e($delivery['p4_name'] ?: '—') ?></dd></div>
            <div><dt>Funcionario receptor</dt><dd><?= e($delivery['recipient_label_snapshot']) ?></dd></div>
            <div><dt>Cédula</dt><dd><?= e($delivery['recipient_document_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Posición</dt><dd><?= e($delivery['recipient_position_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Dirección</dt><dd><?= e($delivery['recipient_direction_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Oficina</dt><dd><?= e($delivery['recipient_office_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Entrega</dt><dd><?= e($delivery['delivered_by_label_snapshot']) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e($delivery['document_number'] ?: '—') ?></dd></div>
            <div><dt>Estado</dt><dd><?= e($statusLabels[$delivery['status']] ?? $delivery['status']) ?></dd></div>
            <div><dt>Excepción de adscripción</dt><dd><?= (int) ($delivery['scope_override_enabled'] ?? 0) === 1 ? 'Sí' : 'No' ?></dd></div>
        </dl>
        <?php if ((int) ($delivery['scope_override_enabled'] ?? 0) === 1): ?>
            <div class="delivery-scope-audit">
                <strong>Entrega excepcional autorizada</strong>
                <p><?= e($delivery['scope_override_reason'] ?: 'Sin motivo registrado') ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($delivery['notes'])): ?>
            <div class="delivery-note"><strong>Observaciones</strong><p><?= e($delivery['notes']) ?></p></div>
        <?php endif; ?>
    </article>

    <aside class="delivery-panel delivery-workflow">
        <span class="eyebrow">CONTROL</span>
        <h2><?= e($statusLabels[$delivery['status']] ?? $delivery['status']) ?></h2>

        <?php if ($delivery['status'] === 'draft'): ?>
            <p>Confirmar descontará físicamente estas unidades del P4. El movimiento no puede editarse después; las correcciones posteriores se harán mediante movimientos compensatorios.</p>

            <form method="post" action="<?= e(sindesg_url('?module=deliveries&action=confirm')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">
                <button class="primary-button delivery-full-button" type="submit" <?= $deliveryItems === [] ? 'disabled' : '' ?>>Confirmar despacho</button>
            </form>

            <form method="post" action="<?= e(sindesg_url('?module=deliveries&action=cancel')) ?>" class="delivery-cancel-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">
                <button class="delivery-danger-button" type="submit">Cancelar borrador</button>
            </form>
        <?php elseif ($delivery['status'] === 'confirmed'): ?>
            <p>La entrega está confirmada y ya forma parte del historial de dotación del funcionario.</p>
            <button class="secondary-button delivery-full-button" type="button" onclick="window.print()">Imprimir comprobante</button>
            <a class="secondary-button delivery-full-button" href="<?= e(sindesg_url('?module=deliveries&action=history&employee_id=' . (int) $delivery['recipient_employee_id'])) ?>">Historial del funcionario</a>
        <?php else: ?>
            <p>El borrador fue cancelado sin afectar el inventario del P4.</p>
        <?php endif; ?>
    </aside>
</section>

<?php if ($delivery['status'] === 'draft'): ?>
<section class="delivery-panel no-print">
    <div class="delivery-section-head">
        <div>
            <span class="eyebrow">AGREGAR ARTÍCULO</span>
            <h2>Stock libre del P4</h2>
            <p>El disponible manual excluye unidades recibidas para beneficiarios específicos.</p>
        </div>
    </div>

    <?php
    $freeOptions = array_values(array_filter(
        $p4StockOptions,
        static fn (array $row): bool => (float) $row['manual_available'] > 0
    ));
    ?>

    <?php if ($freeOptions === []): ?>
        <div class="delivery-empty compact"><p>No hay otras variantes con stock libre en este P4.</p></div>
    <?php else: ?>
        <form class="delivery-item-form" method="post" action="<?= e(sindesg_url('?module=deliveries&action=add-item')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">

            <label>
                <span>Artículo / variante *</span>
                <select name="product_variant_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($freeOptions as $option): ?>
                        <option value="<?= e($option['id']) ?>">
                            <?= e($option['product_name']
                                . ' · ' . $option['variant_code']
                                . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '')
                                . ' · Libre ' . number_format((float) $option['manual_available'], 3, '.', ',')
                                . ' / Físico ' . number_format((float) $option['physical_stock'], 3, '.', ',')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Cantidad *</span>
                <input type="number" name="quantity" min="0.001" step="0.001" required placeholder="0">
            </label>

            <label>
                <span>Observación</span>
                <input type="text" name="item_notes" maxlength="255" placeholder="Opcional">
            </label>

            <button class="primary-button" type="submit">Agregar</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="delivery-panel">
    <div class="delivery-section-head">
        <div><span class="eyebrow">ARTÍCULOS</span><h2>Detalle de entrega</h2></div>
        <span class="delivery-total"><?= e(count($deliveryItems)) ?> renglones</span>
    </div>

    <?php if ($deliveryItems === []): ?>
        <div class="delivery-empty compact"><p>El borrador aún no contiene artículos.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Artículo</th><th>Variante</th><th>Talla</th><th>Cantidad</th><th>Origen</th><th>Transferencia</th><?php if ($delivery['status'] === 'draft'): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($deliveryItems as $item): ?>
                    <tr>
                        <td><strong><?= e($item['product_name']) ?></strong><small class="delivery-cell-sub"><?= e($item['category_name']) ?></small></td>
                        <td><code><?= e($item['variant_code']) ?></code></td>
                        <td><?= e($item['size_name'] ?: '—') ?></td>
                        <td class="delivery-qty"><?= e(number_format((float) $item['quantity'], 3, '.', ',')) ?></td>
                        <td><?= e($item['request_code'] ?: 'Stock libre P4') ?></td>
                        <td><?= e($item['transfer_code'] ?: '—') ?></td>
                        <?php if ($delivery['status'] === 'draft'): ?>
                            <td>
                                <form method="post" action="<?= e(sindesg_url('?module=deliveries&action=remove-item')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">
                                    <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                                    <button class="delivery-remove-button" type="submit">×</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="delivery-panel no-print">
    <div class="delivery-section-head">
        <div>
            <span class="eyebrow">HISTORIAL DE DOTACIÓN</span>
            <h2>Entregas anteriores</h2>
        </div>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries&action=history&employee_id=' . (int) $delivery['recipient_employee_id'])) ?>">Ver historial completo</a>
    </div>

    <?php
    $priorRows = array_values(array_filter(
        $recipientHistory,
        static fn (array $row): bool => (int) $row['delivery_id'] !== (int) $delivery['id']
    ));
    ?>

    <?php if ($priorRows === []): ?>
        <div class="delivery-empty compact"><p>No hay entregas confirmadas anteriores para este funcionario.</p></div>
    <?php else: ?>
        <div class="history-strip">
            <?php foreach (array_slice($priorRows, 0, 8) as $row): ?>
                <article>
                    <strong><?= e($row['product_name']) ?></strong>
                    <span><?= e($row['variant_code'] . ($row['size_name'] ? ' · Talla ' . $row['size_name'] : '')) ?></span>
                    <small><?= e(number_format((float) $row['quantity'], 3, '.', ',')) ?> · <?= e(date('d/m/Y', strtotime((string) $row['delivered_at']))) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="delivery-bottom-actions no-print">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">← Despachos</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $delivery['p4_warehouse_id'])) ?>">Ver P4</a>
</div>
