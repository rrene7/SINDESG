<?php
declare(strict_types=1);
/** @var array<string,mixed> $serviceRequest */
/** @var list<array<string,mixed>> $variantGroups */
$status = (string) $serviceRequest['status'];
$mayReview = $status === 'in_review'
    && sindesg_role_action_allowed('requests', 'decide', 'POST', (array) sindesg_user())
    && sindesg_can('requests.manage');
$totalVariants = count($variantGroups);
$totalNominees = array_sum(array_map(
    static fn (array $g): int => count($g['items']), $variantGroups
));
$totalAvailable = array_sum(array_map(
    static fn (array $g): float => min((float) $g['requested'], (float) $g['available_to_approve']),
    $variantGroups
));
?>
<section class="request-detail-hero">
    <div>
        <span class="eyebrow">ALMACÉN CENTRAL · REVISIÓN POR TALLAS</span>
        <h1>Solicitud de dotación — <?= e($serviceRequest['p4_name_snapshot'] ?: $serviceRequest['p4_internal_name']) ?></h1>
        <p><?= e($serviceRequest['requested_by_label_snapshot']) ?> · Solicitud registrada <?= e(date('d/m/Y H:i', strtotime((string) $serviceRequest['requested_at']))) ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $serviceRequest['id'])) ?>">
        Volver a solicitud y seguimiento
    </a>
</section>

<section class="request-panel central-overview" aria-label="Resumen de la solicitud">
    <div class="request-section-head">
        <div><span class="eyebrow">CONSOLIDADO DEL CENTRAL</span>
            <h2>Dotación agrupada de <?= (int) $totalNominees ?> renglones nominales</h2>
            <p>Una fila por artículo y talla. Pulse «Ver destinatarios» para revisar y ajustar cada asignación cuando sea necesario, sin abrir todos los expedientes.</p>
        </div>
    </div>
    <div class="request-verification-summary">
        <span><strong><?= (int) $totalNominees ?></strong><small>Renglones nominales</small></span>
        <span><strong><?= (int) $totalVariants ?></strong><small>Variantes / tallas</small></span>
        <span><strong><?= e(sindesg_units((float) $reviewTotal)) ?></strong><small>Unidades solicitadas</small></span>
        <span><strong><?= e(sindesg_units($totalAvailable)) ?></strong><small>Máximo comprometible hoy</small></span>
    </div>
    <?php if ($totalAvailable < (float) $reviewTotal): ?>
        <p class="central-warning" role="status">
            Stock insuficiente para aprobar la totalidad. Almacén Central debe registrar las existencias reales
            mediante recepción o saldo inicial autorizado antes de comprometer estas cantidades.
            La solicitud puede revisarse y permanecer pendiente sin inventar stock.
            <a href="<?= e(sindesg_url('?module=receipts')) ?>">Ver recepciones</a>
        </p>
    <?php endif; ?>
    <?php if ($status === 'draft'): ?>
        <p class="request-cell-sub">La solicitud todavía es borrador P4. El P4 debe enviarla antes de iniciar su revisión.</p>
    <?php elseif ($status === 'submitted'): ?>
        <p class="request-cell-sub">El Central recibió la solicitud. Inicie la revisión desde la ficha de la solicitud; la revisión agrupada ya está disponible.</p>
        <form method="post" action="<?= e(sindesg_url('?module=requests&action=start-review')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
            <button class="primary-button" type="submit">Iniciar revisión del Central</button>
        </form>
    <?php elseif ($mayReview): ?>
        <p class="request-cell-sub">Puede revisar por grupo y registrar cantidades aprobadas. El servidor verificará la disponibilidad de CADA variante antes de aprobar. Aprobar todavía no despacha.</p>
    <?php else: ?>
        <p class="request-cell-sub">La solicitud ya tiene una decisión: <?= e($status) ?>. Este resumen es de consulta y no modifica aprobaciones.</p>
    <?php endif; ?>
</section>

<?php if ($status === 'rejected'
    && in_array('ADMIN', (array) (sindesg_user()['role_codes'] ?? []), true)
    && sindesg_can('admin.manage') && sindesg_can('requests.manage')): ?>
<section class="request-panel central-reopen-panel">
    <h2>Corrección administrativa de un rechazo</h2>
    <p>Esta operación excepcional reabre la solicitud para revisión sin borrar la decisión anterior.
       Se registra el motivo y los datos de la decisión rechazada en auditoría. No modifica inventario ni cantidades nominales.
       Solo procede cuando no hay aprobaciones, distribuciones o movimientos asociados.</p>
    <form method="post" action="<?= e(sindesg_url('?module=requests&action=reopen-rejected')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
        <label>Motivo de corrección administrativa (obligatorio)
            <textarea name="reopen_reason" required minlength="15" rows="3"
                placeholder="Describa el error y la autorización para revisar nuevamente"></textarea>
        </label>
        <label><input type="checkbox" name="reopen_ack" value="yes" required>
            Confirmo que solicito reabrir para corregir el rechazo y conservar la evidencia anterior.</label>
        <button type="submit" class="secondary-button">Reabrir solicitud para nueva revisión</button>
    </form>
</section>
<?php endif; ?>

<?php if ($mayReview): ?>
<form method="post"
      action="<?= e(sindesg_url('?module=requests&action=decide')) ?>"
      class="request-review-form central-grouped-review" data-central-review>
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
<?php endif; ?>

<section class="request-panel central-review-panel">
    <div class="request-section-head">
        <div><span class="eyebrow">ALMACÉN CENTRAL</span><h2>Resumen por artículo y talla</h2>
            <p>El stock central es real y proviene del kardex. «Comprometible» descuenta reservas de otras solicitudes.</p>
        </div>
    </div>
    <?php if ($mayReview): ?>
    <div class="central-bulk-toolbar" role="group" aria-label="Completar revisión por tallas">
        <button type="button" class="primary-button" data-central-complete-all>Completar todas las tallas</button>
        <button type="button" class="secondary-button" data-central-clear-all>Limpiar selección</button>
        <p>Selecciona por artículo y talla hasta la cantidad solicitada o el stock comprometible, lo que sea menor.
           <strong>Esta acción no aprueba, rechaza ni guarda.</strong> Revise los destinatarios y valide antes de registrar una decisión.</p>
        <p class="central-bulk-feedback" data-central-bulk-feedback role="status" aria-live="polite">Sin selección automática aplicada.</p>
    </div>
    <?php endif; ?>
    <div class="responsive-table">
        <table class="central-size-table">
            <thead>
            <tr><th>Artículo / variante</th><th>Talla</th><th>Funcionarios</th><th>Solicitado</th>
                <th>Stock Central</th><th>Comprometible</th><th>Aprobado</th>
                <?php if ($mayReview): ?><th>Decisión por talla</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($variantGroups as $group): ?>
                <?php
                $requested = (float) $group['requested'];
                $available = max(0, (float) $group['available_to_approve']);
                $limit = min($requested, $available);
                ?>
                <tr>
                    <td data-label="Artículo"><strong><?= e($group['product_name']) ?></strong>
                        <small class="request-cell-sub"><?= e(($group['size_name'] ?? '') ? 'Talla ' . $group['size_name'] : 'Presentación estándar') ?></small></td>
                    <td data-label="Talla"><strong><?= e($group['size_name'] ?: '—') ?></strong></td>
                    <td data-label="Funcionarios"><?= count($group['items']) ?></td>
                    <td data-label="Solicitado"><?= e(sindesg_units($requested)) ?></td>
                    <td data-label="Stock Central"><?= e(sindesg_units((float) $group['central_stock'])) ?></td>
                    <td data-label="Comprometible" class="<?= $limit <= 0 ? 'central-zero-stock' : '' ?>"><?= e(sindesg_units($available)) ?></td>
                    <td data-label="Aprobado"><?= e(sindesg_units((float) $group['approved'])) ?></td>
                    <?php if ($mayReview): ?>
                        <td data-label="Decisión por talla">
                            <div class="central-decision" data-central-variant
                                 data-variant-id="<?= (int) $group['variant_id'] ?>"
                                 data-variant-available="<?= e(number_format($available, 3, '.', '')) ?>"
                                 data-central-requested="<?= e(number_format($requested, 3, '.', '')) ?>">
                                <label>
                                    <span class="sr-only">Cantidad para talla <?= e($group['size_name']) ?></span>
                                    <input type="number" step="0.001" min="0"
                                           max="<?= e(number_format($limit, 3, '.', '')) ?>"
                                           value="0" data-central-quantity
                                           name="group_quantity[<?= (int) $group['variant_id'] ?>]"
                                           aria-label="Cantidad a aprobar de talla <?= e($group['size_name']) ?>">
                                </label>
                                <button type="button" class="secondary-button" data-central-apply>Aplicar cantidad</button>
                                <button type="button" class="secondary-button" data-central-complete-group>Completar talla</button>
                                <small class="central-group-help">Al cambiar esta cantidad se distribuye entre los primeros destinatarios. Puede ajustar el detalle nominal antes de aprobar.</small>
                                <small data-central-selected>0 asignadas</small>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
                <tr class="central-detail-row">
                    <td colspan="<?= $mayReview ? 8 : 7 ?>">
                        <details class="central-group-detail">
                            <summary>Ver <?= count($group['items']) ?> destinatarios de <?= e($group['product_name'] . ' · talla ' . ($group['size_name'] ?: '—')) ?></summary>
                            <div class="central-nominees">
                                <?php foreach ($group['items'] as $item): ?>
                                    <div class="central-nominee">
                                        <span><?= e($item['beneficiary_label_snapshot'] ?: 'Solicitud consolidada') ?></span>
                                        <small><?= e($item['beneficiary_position_snapshot'] ?: 'Sin posición') ?></small>
                                        <?php if ($mayReview): ?>
                                            <small>Solicitado: <?= e(sindesg_units((float) $item['quantity_requested'])) ?></small>
                                            <label>
                                                <span class="sr-only">Cantidad aprobada de <?= e($item['beneficiary_label_snapshot'] ?: 'renglón') ?></span>
                                                <input type="number" step="0.001" min="0"
                                                       max="<?= e(number_format((float) $item['quantity_requested'], 3, '.', '')) ?>"
                                                       value="0"
                                                       name="approved_quantity[<?= (int) $item['id'] ?>]"
                                                       data-central-item
                                                       data-requested="<?= e(number_format((float) $item['quantity_requested'], 3, '.', '')) ?>"
                                                       data-variant-id="<?= (int) $group['variant_id'] ?>"
                                                       required>
                                            </label>
                                        <?php else: ?>
                                            <small>Solicitado <?= e(sindesg_units((float) $item['quantity_requested'])) ?></small>
                                        <?php endif; ?>
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

<?php if ($mayReview): ?>
<section class="request-panel central-review-submit">
    <h2>Validar cantidades antes de decidir</h2>
    <p>Introduzca las cantidades por talla; se asignarán provisionalmente a los primeros destinatarios. Puede cambiar individualmente las asignaciones desplegando la talla.
       <strong>Validar cantidades no guarda, no aprueba y no rechaza.</strong> El inventario no se modifica hasta el despacho correspondiente.</p>
    <div class="request-employee-picker" data-reviewer-picker data-search-url="<?= e($employeeSearchUrl) ?>">
        <label><span>Funcionario revisor *</span>
            <input type="search" data-reviewer-search autocomplete="off" placeholder="Buscar en RRHH por nombre, cédula o posición" required>
            <input type="hidden" name="reviewed_by_employee_id" data-reviewer-id>
        </label>
        <div class="request-picker-results" data-reviewer-results hidden></div>
        <div class="request-picker-selection" data-reviewer-selection hidden></div>
    </div>
    <label class="request-notes-field"><span>Observación de revisión</span>
        <textarea name="review_notes" rows="3" placeholder="Motivo de aprobación parcial o cantidades no autorizadas..."></textarea>
    </label>
    <div class="central-approval-toolbar">
        <button type="button" class="secondary-button" data-central-validate>1. Validar cantidades · sin guardar</button>
        <p class="central-review-message" data-central-message role="status" aria-live="polite">Primero introduzca las cantidades por talla y valide. Ninguna decisión ha sido registrada.</p>
    </div>
    <label class="central-partial-confirmation">
        <input type="checkbox" name="partial_nominal_confirmed" value="yes" data-central-partial-check>
        Confirmé los funcionarios incluidos y excluidos en caso de aprobación parcial.
    </label>
    <div class="central-final-actions">
        <button type="submit" name="decision_action" value="approve"
                class="primary-button" data-central-approve>2. Registrar aprobación definitiva</button>
        <details class="central-rejection-option">
            <summary>Rechazar toda la solicitud · acción irreversible sin autorización administrativa</summary>
            <p>Solo utilice esta operación si corresponde rechazar expresamente todas las unidades.</p>
            <label><input type="checkbox" name="reject_ack" value="yes" data-central-reject-check>
                Confirmo que deseo rechazar la solicitud completa y que todas las cantidades aprobadas son cero.</label>
            <button type="submit" name="decision_action" value="reject"
                    class="secondary-button" data-central-reject>Registrar rechazo definitivo</button>
        </details>
    </div>
</section>
</form>
<?php endif; ?>

<div class="request-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $serviceRequest['id'])) ?>">← Solicitud y seguimiento</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">Todas las solicitudes</a>
</div>
