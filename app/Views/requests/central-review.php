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
                    <td><strong><?= e($group['product_name']) ?></strong>
                        <small class="request-cell-sub"><?= e(($group['size_name'] ?? '') ? 'Talla ' . $group['size_name'] : 'Presentación estándar') ?></small></td>
                    <td><strong><?= e($group['size_name'] ?: '—') ?></strong></td>
                    <td><?= count($group['items']) ?></td>
                    <td><?= e(sindesg_units($requested)) ?></td>
                    <td><?= e(sindesg_units((float) $group['central_stock'])) ?></td>
                    <td class="<?= $limit <= 0 ? 'central-zero-stock' : '' ?>"><?= e(sindesg_units($available)) ?></td>
                    <td><?= e(sindesg_units((float) $group['approved'])) ?></td>
                    <?php if ($mayReview): ?>
                        <td>
                            <div class="central-decision" data-central-variant
                                 data-variant-id="<?= (int) $group['variant_id'] ?>"
                                 data-variant-available="<?= e(number_format($available, 3, '.', '')) ?>">
                                <label>
                                    <span class="sr-only">Cantidad para talla <?= e($group['size_name']) ?></span>
                                    <input type="number" step="0.001" min="0"
                                           max="<?= e(number_format($limit, 3, '.', '')) ?>"
                                           value="0" data-central-quantity aria-label="Cantidad a aprobar de talla <?= e($group['size_name']) ?>">
                                </label>
                                <button type="button" class="secondary-button" data-central-apply>Aplicar a grupo</button>
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
    <h2>Confirmación de revisión del Central</h2>
    <p>«Aplicar a grupo» reparte la cantidad autorizada a los primeros renglones visibles del grupo.
        Puede ajustar beneficiarios dentro de cada grupo antes de registrar la decisión. El servidor siempre verifica el stock comprometible.</p>
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
    <p class="central-review-message" data-central-message role="status">Aún no se han aplicado cantidades para aprobar.</p>
    <button type="submit" class="primary-button">Registrar decisión del Almacén Central</button>
</section>
</form>
<?php endif; ?>

<div class="request-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $serviceRequest['id'])) ?>">← Solicitud y seguimiento</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">Todas las solicitudes</a>
</div>
