<?php
declare(strict_types=1);

$isSourceOperation = is_array($sourceDeliveryItem);
$sourceTypes = [
    'return' => 'Devolución',
    'exchange' => 'Cambio de talla / código',
    'replacement' => 'Reposición',
];
$warehouseTypes = [
    'deterioration' => 'Deterioro',
    'writeoff' => 'Baja autorizada',
    'loss' => 'Pérdida',
    'shortage' => 'Faltante',
    'surplus' => 'Sobrante',
    'authorized_adjustment' => 'Ajuste autorizado',
    'cycle_count' => 'Conteo cíclico',
];
?>
<section class="adjustment-hero compact">
    <div>
        <span class="eyebrow"><?= $isSourceOperation ? 'ENTREGA INDIVIDUAL · CORRECCIÓN' : 'AJUSTE DE ALMACÉN' ?></span>
        <h1><?= $isSourceOperation ? 'Devolver, cambiar o reponer' : 'Nueva operación de inventario' ?></h1>
        <p><?= $isSourceOperation
            ? 'La operación quedará enlazada a la entrega original y al funcionario.'
            : 'Seleccione el tipo de ajuste. El saldo solo cambia al confirmar el borrador.' ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=adjustments')) ?>">← Volver</a>
</section>

<form class="adjustment-form" method="post" action="<?= e(sindesg_url('?module=adjustments&action=save')) ?>" data-adjustment-form>
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <?php if ($isSourceOperation): ?>
        <input type="hidden" name="source_delivery_item_id" value="<?= e($sourceDeliveryItem['delivery_item_id']) ?>">
    <?php endif; ?>

    <section class="adjustment-panel">
        <div class="adjustment-section-head">
            <div><span class="eyebrow">OPERACIÓN</span><h2>Tipo y origen</h2></div>
            <div class="adjustment-auto-code"><strong>Código automático</strong><span>AJU-######</span></div>
        </div>

        <?php if ($isSourceOperation): ?>
            <div class="adjustment-source-grid">
                <article><span>Despacho original</span><strong><?= e($sourceDeliveryItem['delivery_code']) ?></strong><small><?= e(date('d/m/Y', strtotime((string) $sourceDeliveryItem['delivered_at']))) ?></small></article>
                <article><span>Funcionario</span><strong><?= e($sourceDeliveryItem['recipient_label_snapshot']) ?></strong><small><?= e($sourceDeliveryItem['recipient_document_snapshot'] ?: '—') ?></small></article>
                <article><span>P4</span><strong><?= e($sourceDeliveryItem['warehouse_code']) ?></strong><small><?= e($sourceDeliveryItem['warehouse_name'] ?: 'P4') ?></small></article>
                <article><span>Artículo entregado</span><strong><?= e($sourceDeliveryItem['product_name']) ?></strong><small><?= e($sourceDeliveryItem['variant_code'] . ($sourceDeliveryItem['size_name'] ? ' · Talla ' . $sourceDeliveryItem['size_name'] : '')) ?></small></article>
            </div>
            <div class="adjustment-source-note">
                Entregado: <strong><?= e(number_format((float) $sourceDeliveryItem['delivered_quantity'], 3, '.', ',')) ?></strong>
                · Aún devolvible: <strong><?= e(number_format((float) $sourceDeliveryItem['returnable_quantity'], 3, '.', ',')) ?></strong>
            </div>
        <?php endif; ?>

        <div class="adjustment-form-grid">
            <label>
                <span>Tipo de operación *</span>
                <select name="adjustment_type" data-adjustment-type required>
                    <option value="">Seleccione...</option>
                    <?php foreach (($isSourceOperation ? $sourceTypes : $warehouseTypes) as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedAdjustmentType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php if (!$isSourceOperation): ?>
                <label>
                    <span>Almacén *</span>
                    <select name="warehouse_id" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($adjustmentWarehouses as $warehouse): ?>
                            <option value="<?= e($warehouse['id']) ?>">
                                <?= e($warehouse['code']
                                    . ' · '
                                    . ($warehouse['warehouse_type'] === 'P4'
                                        ? ($warehouse['organization_unit_name_snapshot'] ?: $warehouse['name'])
                                        : $warehouse['name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="adjustment-span-2">
                    <span>Artículo / variante *</span>
                    <select name="product_variant_id" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($adjustmentVariants as $variant): ?>
                            <option value="<?= e($variant['id']) ?>">
                                <?= e($variant['product_name']
                                    . ' · ' . $variant['variant_code']
                                    . ($variant['size_name'] ? ' · Talla ' . $variant['size_name'] : '')
                                    . ($variant['brand_name'] ? ' · ' . $variant['brand_name'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isSourceOperation): ?>
        <section class="adjustment-panel">
            <div class="adjustment-section-head">
                <div><span class="eyebrow">MOVIMIENTO DEL FUNCIONARIO</span><h2>Detalle de devolución o reposición</h2></div>
            </div>

            <div class="adjustment-form-grid">
                <label>
                    <span>Cantidad *</span>
                    <input type="number"
                           name="quantity"
                           min="0.001"
                           step="0.001"
                           max="<?= e(max((float) $sourceDeliveryItem['delivered_quantity'], (float) $sourceDeliveryItem['returnable_quantity'])) ?>"
                           value="<?= e(number_format(max(0.0, (float) $sourceDeliveryItem['returnable_quantity']), 3, '.', '')) ?>"
                           required>
                </label>

                <label data-return-condition>
                    <span>Condición de lo devuelto *</span>
                    <select name="return_condition">
                        <option value="">Seleccione...</option>
                        <option value="reusable">Reutilizable · vuelve al stock del P4</option>
                        <option value="non_reusable">No reutilizable · no vuelve al stock</option>
                    </select>
                </label>

                <label class="adjustment-span-2" data-replacement-variant>
                    <span>Variante que se entregará como reemplazo *</span>
                    <select name="replacement_variant_id">
                        <option value="">Seleccione...</option>
                        <?php foreach ($replacementVariants as $variant): ?>
                            <option value="<?= e($variant['id']) ?>">
                                <?= e($variant['product_name']
                                    . ' · ' . $variant['variant_code']
                                    . ($variant['size_name'] ? ' · Talla ' . $variant['size_name'] : '')
                                    . ($variant['brand_name'] ? ' · ' . $variant['brand_name'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>
    <?php else: ?>
        <section class="adjustment-panel">
            <div class="adjustment-section-head">
                <div><span class="eyebrow">CANTIDAD</span><h2>Impacto físico</h2></div>
            </div>

            <div class="adjustment-form-grid">
                <label data-normal-quantity>
                    <span>Cantidad *</span>
                    <input type="number" name="quantity" min="0.001" step="0.001" placeholder="0">
                </label>

                <label data-counted-quantity hidden>
                    <span>Cantidad física contada *</span>
                    <input type="number" name="counted_quantity" min="0" step="0.001" placeholder="0">
                </label>

                <label data-manual-direction hidden>
                    <span>Dirección del ajuste *</span>
                    <select name="manual_direction">
                        <option value="">Seleccione...</option>
                        <option value="IN">Entrada</option>
                        <option value="OUT">Salida</option>
                    </select>
                </label>
            </div>
        </section>
    <?php endif; ?>

    <section class="adjustment-panel">
        <div class="adjustment-section-head">
            <div><span class="eyebrow">RESPONSABLE Y SOPORTE</span><h2>Trazabilidad administrativa</h2></div>
        </div>

        <div class="adjustment-form-grid">
            <div class="adjustment-employee-picker"
                 data-adjustment-picker
                 data-search-url="<?= e($employeeSearchUrl) ?>">
                <label>
                    <span>Funcionario responsable *</span>
                    <input type="search" data-adjustment-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                    <input type="hidden" name="responsible_employee_id" data-adjustment-id>
                </label>
                <div class="adjustment-picker-results" data-adjustment-results hidden></div>
                <div class="adjustment-picker-selection" data-adjustment-selection hidden></div>
            </div>

            <label>
                <span>Fecha y hora *</span>
                <input type="datetime-local" name="occurred_at" value="<?= e($defaultOccurredAt) ?>" required>
            </label>

            <label>
                <span>Documento / acta</span>
                <input type="text" name="document_number" maxlength="120" placeholder="Obligatorio para baja, ajuste autorizado y conteo">
            </label>

            <label>
                <span>Motivo *</span>
                <input type="text" name="reason" maxlength="255" required placeholder="Explique por qué se realiza la operación">
            </label>

            <label class="adjustment-span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3" placeholder="Detalles adicionales, condición física, autorización, hallazgo..."></textarea>
            </label>
        </div>
    </section>

    <div class="adjustment-form-actions">
        <a href="<?= e(sindesg_url('?module=adjustments')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Crear borrador</button>
    </div>
</form>
