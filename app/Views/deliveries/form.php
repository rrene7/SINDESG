<?php
declare(strict_types=1);

$isNominal = is_array($pendingLine);
?>
<section class="delivery-hero compact">
    <div>
        <span class="eyebrow"><?= $isNominal ? 'ENTREGA NOMINAL' : 'NUEVO DESPACHO' ?></span>
        <h1><?= $isNominal ? 'Entregar prenda reservada' : 'Crear borrador de despacho' ?></h1>
        <p><?= $isNominal
            ? 'La prenda ya fue recibida en el P4 para este beneficiario. Confirme quién entrega y la cantidad.'
            : 'Seleccione el P4 y el funcionario receptor. Después podrá agregar artículos disponibles en ese P4.' ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">← Volver</a>
</section>

<form class="delivery-form" method="post" action="<?= e(sindesg_url('?module=deliveries&action=save')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <?php if ($isNominal): ?>
        <input type="hidden" name="request_item_id" value="<?= e($pendingLine['request_item_id']) ?>">
        <input type="hidden" name="p4_warehouse_id" value="<?= e($pendingLine['p4_warehouse_id']) ?>">
        <input type="hidden" name="recipient_employee_id" value="<?= e($pendingLine['beneficiary_employee_id']) ?>">
    <?php endif; ?>

    <section class="delivery-panel">
        <div class="delivery-section-head">
            <div><span class="eyebrow">ORIGEN Y DESTINO</span><h2>P4 y funcionario receptor</h2></div>
            <div class="delivery-auto-code"><strong>Código automático</strong><span>DSP-######</span></div>
        </div>

        <?php if ($isNominal): ?>
            <div class="delivery-fixed-grid">
                <article>
                    <span>P4</span>
                    <strong><?= e($pendingLine['p4_code']) ?></strong>
                    <small><?= e($pendingLine['p4_name'] ?: 'P4') ?></small>
                </article>
                <article>
                    <span>Funcionario receptor</span>
                    <strong><?= e($pendingLine['beneficiary_label_snapshot']) ?></strong>
                    <small><?= e($pendingLine['beneficiary_document_snapshot'] ?: '—') ?></small>
                </article>
                <article>
                    <span>Artículo reservado</span>
                    <strong><?= e($pendingLine['product_name']) ?></strong>
                    <small><?= e($pendingLine['variant_code'] . ($pendingLine['size_name'] ? ' · Talla ' . $pendingLine['size_name'] : '')) ?></small>
                </article>
                <article>
                    <span>Origen logístico</span>
                    <strong><?= e($pendingLine['request_code']) ?></strong>
                    <small><?= e($pendingLine['transfer_code'] ?: 'Transferencia recibida') ?></small>
                </article>
            </div>

            <label class="delivery-quantity-field">
                <span>Cantidad a entregar *</span>
                <input type="number"
                       name="linked_quantity"
                       min="0.001"
                       max="<?= e($pendingLine['pending_quantity']) ?>"
                       step="0.001"
                       value="<?= e($pendingLine['pending_quantity']) ?>"
                       required>
                <small>Máximo pendiente: <?= e(number_format((float) $pendingLine['pending_quantity'], 3, '.', ',')) ?></small>
            </label>
        <?php else: ?>
            <div class="delivery-form-grid">
                <label>
                    <span>P4 *</span>
                    <select name="p4_warehouse_id" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($activeP4s as $p4): ?>
                            <option value="<?= e($p4['id']) ?>"><?= e($p4['code'] . ' · ' . ($p4['organization_unit_name_snapshot'] ?: $p4['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="delivery-employee-picker"
                     data-delivery-picker
                     data-recipient-picker
                     data-search-url="<?= e($employeeSearchUrl) ?>"
                     data-history-url="<?= e($historyApiUrl) ?>">
                    <label>
                        <span>Funcionario receptor *</span>
                        <input type="search" data-delivery-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                        <input type="hidden" name="recipient_employee_id" data-delivery-id>
                    </label>
                    <div class="delivery-picker-results" data-delivery-results hidden></div>
                    <div class="delivery-picker-selection" data-delivery-selection hidden></div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="delivery-panel">
        <div class="delivery-section-head">
            <div><span class="eyebrow">RESPONSABLE</span><h2>Funcionario que entrega</h2></div>
        </div>

        <div class="delivery-form-grid">
            <div class="delivery-employee-picker"
                 data-delivery-picker
                 data-search-url="<?= e($employeeSearchUrl) ?>">
                <label>
                    <span>Buscar funcionario *</span>
                    <input type="search" data-delivery-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                    <input type="hidden" name="delivered_by_employee_id" data-delivery-id>
                </label>
                <div class="delivery-picker-results" data-delivery-results hidden></div>
                <div class="delivery-picker-selection" data-delivery-selection hidden></div>
            </div>

            <label>
                <span>Fecha y hora *</span>
                <input type="datetime-local" name="delivered_at" value="<?= e($defaultDeliveredAt) ?>" required>
            </label>

            <label>
                <span>Documento / comprobante</span>
                <input type="text" name="document_number" maxlength="120" placeholder="Opcional">
            </label>

            <label>
                <span>Observaciones</span>
                <input type="text" name="notes" maxlength="255" placeholder="Opcional">
            </label>
        </div>
    </section>

    <section class="delivery-panel history-preview" data-history-preview <?= $isNominal && $priorHistory !== [] ? '' : 'hidden' ?>>
        <div class="delivery-section-head">
            <div>
                <span class="eyebrow">VALIDACIÓN PREVIA</span>
                <h2>Entregas anteriores del funcionario</h2>
                <p>El historial informa entregas previas; no bloquea automáticamente una reposición porque la periodicidad todavía no está parametrizada.</p>
            </div>
        </div>
        <div data-history-preview-body>
            <?php if ($isNominal && $priorHistory !== []): ?>
                <?php foreach ($priorHistory as $row): ?>
                    <div class="history-preview-row">
                        <strong><?= e($row['product_name']) ?></strong>
                        <span><?= e($row['variant_code'] . ($row['size_name'] ? ' · Talla ' . $row['size_name'] : '')) ?></span>
                        <span><?= e(number_format((float) $row['quantity'], 3, '.', ',')) ?> · <?= e(date('d/m/Y', strtotime((string) $row['delivered_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="delivery-form-actions">
        <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Crear borrador</button>
    </div>
</form>
