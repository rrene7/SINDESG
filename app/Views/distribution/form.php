<?php
declare(strict_types=1);
$distributionGroups = $distributionGroups ?? \App\Services\DistributionPreparationGroups::groups($distributionRequestItems);
$nominalCount = array_sum(array_map(static fn(array $g): int => count($g['items']), $distributionGroups));
$pendingTotal = array_sum(array_map(static fn(array $g): float => (float) $g['pending'], $distributionGroups));
?>
<section class="distribution-hero compact">
    <div>
        <span class="eyebrow">PREPARACIÓN · DISTRIBUCIÓN AGRUPADA</span>
        <h1>Preparar transferencia de dotación</h1>
        <p><?= e($distributionRequest['p4_name'] ?: 'Almacén P4') ?> · Una fila por artículo y talla; destinatarios desplegables.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=distribution')) ?>">← Volver</a>
</section>

<form class="distribution-form distribution-grouped-form" data-distribution-grouped
      method="post" action="<?= e(sindesg_url('?module=distribution&action=prepare')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="request_id" value="<?= e($distributionRequest['id']) ?>">

    <section class="distribution-panel distribution-compact-overview">
        <div class="distribution-section-head">
            <div>
                <span class="eyebrow">CONSOLIDADO PARA EL CENTRAL</span>
                <h2>Seleccionar cantidades a transferir por talla</h2>
                <p>Use «Seleccionar todos los pendientes» para distribuir las cantidades autorizadas de todas las tallas en un solo clic, sin sobrepasar el stock mostrado. También puede ajustar una talla o un funcionario. Ninguna cantidad viene preseleccionada.</p>
            </div>
            <div class="distribution-auto-code"><strong>Código automático</strong><span>TRF-######</span></div>
        </div>
        <div class="distribution-group-stats" aria-label="Resumen nominal de la solicitud">
            <span><strong><?= (int) $nominalCount ?></strong><small>Renglones nominales</small></span>
            <span><strong><?= count($distributionGroups) ?></strong><small>Artículos / tallas</small></span>
            <span><strong><?= e(sindesg_units($pendingTotal)) ?></strong><small>Unidades pendientes</small></span>
            <span><strong data-transfer-global-selected>0</strong><small>Seleccionadas ahora</small></span>
        </div>
        <div class="distribution-bulk-toolbar" role="group" aria-label="Selección rápida de la transferencia">
            <button type="button" class="primary-button" data-transfer-select-all>Seleccionar todos los pendientes</button>
            <button type="button" class="secondary-button" data-transfer-clear-all>Limpiar selección</button>
            <p>Selecciona automáticamente los funcionarios de cada talla hasta cubrir el saldo aprobado o el stock disponible, lo que sea menor. <strong>No guarda ni prepara la transferencia.</strong></p>
        </div>
        <p class="distribution-stock-caption">El stock mostrado procede del kardex Central. Se vuelve a verificar al despachar; preparar una transferencia no mueve existencias.</p>
        <div class="responsive-table distribution-group-table-wrap">
            <table class="distribution-size-table">
                <thead><tr>
                    <th>Artículo</th><th>Talla</th><th>Funcionarios</th>
                    <th>Aprobado</th><th>Ya despachado</th><th>Pendiente</th>
                    <th>Stock Central</th><th>Enviar ahora</th>
                </tr></thead>
                <tbody>
                <?php foreach ($distributionGroups as $group): ?>
                    <?php
                    $groupPending = (float) $group['pending'];
                    $groupStock = (float) $group['central_stock'];
                    $groupMax = min($groupPending, $groupStock);
                    ?>
                    <tr class="distribution-group-row" data-transfer-group
                        data-variant-id="<?= (int) $group['variant_id'] ?>"
                        data-pending="<?= e(number_format($groupPending, 3, '.', '')) ?>"
                        data-stock="<?= e(number_format($groupStock, 3, '.', '')) ?>">
                        <td data-label="Artículo">
                            <strong><?= e($group['product_name']) ?></strong>
                            <small class="distribution-cell-sub"><?= e($group['variant_code'] . ' · ' . $group['category_name']) ?></small>
                        </td>
                        <td data-label="Talla"><strong><?= e($group['size_name'] ?: '—') ?></strong></td>
                        <td data-label="Funcionarios"><?= count($group['items']) ?></td>
                        <td data-label="Aprobado"><?= e(sindesg_units((float) $group['approved'])) ?></td>
                        <td data-label="Ya despachado"><?= e(sindesg_units((float) $group['dispatched'])) ?></td>
                        <td data-label="Pendiente"><strong><?= e(sindesg_units($groupPending)) ?></strong></td>
                        <td data-label="Stock Central"><?= e(sindesg_units($groupStock)) ?></td>
                        <td data-label="Enviar ahora">
                            <div class="distribution-group-control">
                                <label>
                                    <span class="sr-only">Cantidad de <?= e($group['product_name'] . ', talla ' . ($group['size_name'] ?: '—')) ?></span>
                                    <input type="number" data-transfer-group-quantity
                                           value="0" min="0" step="0.001"
                                           max="<?= e(number_format($groupMax, 3, '.', '')) ?>"
                                           aria-label="Enviar ahora, talla <?= e($group['size_name'] ?: '—') ?>">
                                </label>
                                <button type="button" class="secondary-button" data-transfer-apply>Aplicar cantidad</button>
                                <button type="button" class="secondary-button" data-transfer-max-group>Completar talla</button>
                                <small data-transfer-selected>0 de <?= e(sindesg_units($groupPending)) ?> unidades · 0 destinatarios</small>
                            </div>
                        </td>
                    </tr>
                    <tr class="distribution-detail-row">
                        <td colspan="8">
                            <details class="distribution-group-detail">
                                <summary>Ver <?= count($group['items']) ?> destinatarios · <?= e($group['product_name'] . ' · talla ' . ($group['size_name'] ?: '—')) ?></summary>
                                <div class="distribution-nominal-grid">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <div class="distribution-nominal-person">
                                            <strong><?= e($item['beneficiary_label_snapshot'] ?: 'Cantidad consolidada') ?></strong>
                                            <small>Autorizado: <?= e(sindesg_units((float) $item['quantity_approved'])) ?>
                                                · Ya despachado: <?= e(sindesg_units((float) $item['quantity_dispatched'])) ?>
                                                · Pendiente: <?= e(sindesg_units((float) $item['remaining_quantity'])) ?></small>
                                            <label>
                                                <span>Enviar a este destinatario</span>
                                                <input type="number"
                                                       name="transfer_quantity[<?= (int) $item['request_item_id'] ?>]"
                                                       data-transfer-item
                                                       data-variant-id="<?= (int) $group['variant_id'] ?>"
                                                       data-max="<?= e(number_format((float) $item['remaining_quantity'], 3, '.', '')) ?>"
                                                       min="0" max="<?= e(number_format((float) $item['remaining_quantity'], 3, '.', '')) ?>"
                                                       step="0.001" value="0" required>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
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

    <section class="distribution-panel distribution-prepare-final">
        <h2>Confirmación de preparación</h2>
        <p>La validación solo comprueba cantidades y destinatarios en esta pantalla, sin guardar.
            «Preparar transferencia» crea el documento nominal; la autorización y el despacho se realizan después.</p>
        <p role="status" aria-live="polite" data-transfer-message>Sin selección. Puede seleccionar todos los pendientes con un clic, ajustar y validar sin guardar.</p>
        <div class="distribution-form-actions">
            <a href="<?= e(sindesg_url('?module=distribution')) ?>">Cancelar</a>
            <button class="secondary-button" type="button" data-transfer-validate>1. Validar sin guardar</button>
            <button class="primary-button" type="submit" data-transfer-submit>2. Preparar transferencia</button>
        </div>
    </section>
</form>
