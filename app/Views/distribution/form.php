<?php
declare(strict_types=1);
?>
<section class="distribution-hero compact">
    <div>
        <span class="eyebrow">PREPARACIÓN</span>
        <h1><?= e($distributionRequest['request_code']) ?></h1>
        <p><?= e($distributionRequest['p4_code'] . ' · ' . ($distributionRequest['p4_name'] ?: 'P4')) ?> · Seleccione cuánto se enviará en esta transferencia.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=distribution')) ?>">← Volver</a>
</section>

<form class="distribution-form" method="post" action="<?= e(sindesg_url('?module=distribution&action=prepare')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="request_id" value="<?= e($distributionRequest['id']) ?>">

    <section class="distribution-panel">
        <div class="distribution-section-head">
            <div>
                <span class="eyebrow">CONTENIDO</span>
                <h2>Cantidades a transferir</h2>
                <p>Puede enviar toda la aprobación o una parte. Después de recibir esta transferencia podrá preparar otra por el saldo pendiente.</p>
            </div>
            <div class="distribution-auto-code"><strong>Código automático</strong><span>TRF-######</span></div>
        </div>

        <div class="responsive-table">
            <table>
                <thead><tr><th>Artículo</th><th>Talla</th><th>Beneficiario</th><th>Aprobado</th><th>Ya despachado</th><th>Pendiente</th><th>Stock Central</th><th>Enviar ahora</th></tr></thead>
                <tbody>
                <?php foreach ($distributionRequestItems as $item): ?>
                    <tr>
                        <td><strong><?= e($item['product_name']) ?></strong><small class="distribution-cell-sub"><?= e($item['variant_code']) ?> · <?= e($item['category_name']) ?></small></td>
                        <td><?= e($item['size_name'] ?: '—') ?></td>
                        <td><?= e($item['beneficiary_label_snapshot'] ?: 'Cantidad consolidada') ?></td>
                        <td><?= e(number_format((float) $item['quantity_approved'], 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) $item['quantity_dispatched'], 3, '.', ',')) ?></td>
                        <td class="distribution-qty"><?= e(number_format((float) $item['remaining_quantity'], 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) $item['central_stock'], 3, '.', ',')) ?></td>
                        <td>
                            <input type="number"
                                   name="transfer_quantity[<?= e($item['request_item_id']) ?>]"
                                   min="0"
                                   max="<?= e($item['remaining_quantity']) ?>"
                                   step="0.001"
                                   value="<?= e($item['remaining_quantity']) ?>"
                                   required>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="distribution-panel">
        <div class="distribution-section-head">
            <div><span class="eyebrow">RESPONSABLE DE PREPARACIÓN</span><h2>Funcionario que prepara</h2></div>
        </div>

        <div class="distribution-employee-picker" data-distribution-picker="prepared" data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Buscar funcionario *</span>
                <input type="search" data-distribution-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="prepared_by_employee_id" data-distribution-id>
            </label>
            <div class="distribution-picker-results" data-distribution-results hidden></div>
            <div class="distribution-picker-selection" data-distribution-selection hidden></div>
        </div>

        <div class="distribution-form-grid">
            <label><span>Documento / requisición</span><input type="text" name="document_number" maxlength="120" placeholder="Opcional"></label>
            <label><span>Observaciones</span><input type="text" name="notes" maxlength="255" placeholder="Opcional"></label>
        </div>
    </section>

    <div class="distribution-form-actions">
        <a href="<?= e(sindesg_url('?module=distribution')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Preparar transferencia</button>
    </div>
</form>
