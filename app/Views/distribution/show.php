<?php
declare(strict_types=1);

$statusLabels = [
    'prepared' => 'Preparada',
    'authorized' => 'Lista para retirar',
    'dispatched' => 'En tránsito',
    'received' => 'Recibida',
    'cancelled' => 'Cancelada',
];
$totalQuantity = array_sum(array_map(static fn (array $row): float => (float) $row['quantity'], $distributionItems));
?>
<section class="distribution-detail-hero">
    <div>
        <span class="eyebrow">TRANSFERENCIA <?= e($distribution['transfer_code']) ?></span>
        <h1><?= e($distribution['destination_code'] . ' · ' . ($distribution['destination_name'] ?: $distribution['destination_internal_name'])) ?></h1>
        <p>Solicitud <?= e($distribution['request_code']) ?> · <?= e($distribution['source_name']) ?> → <?= e($distribution['destination_name'] ?: $distribution['destination_internal_name']) ?></p>
    </div>
    <div class="distribution-detail-status">
        <span class="distribution-status <?= e($distribution['status']) ?>"><?= e($statusLabels[$distribution['status']] ?? $distribution['status']) ?></span>
        <strong><?= e(number_format($totalQuantity, 0, '.', ',')) ?></strong>
        <small>unidades</small>
    </div>
</section>

<section class="distribution-detail-grid">
    <article class="distribution-panel">
        <span class="eyebrow">TRAZABILIDAD</span>
        <h2>Datos de transferencia</h2>
        <dl class="distribution-dl">
            <div><dt>Código</dt><dd><?= e($distribution['transfer_code']) ?></dd></div>
            <div><dt>Solicitud</dt><dd><?= e($distribution['request_code']) ?></dd></div>
            <div><dt>Origen</dt><dd><?= e($distribution['source_name']) ?></dd></div>
            <div><dt>Destino</dt><dd><?= e($distribution['destination_name'] ?: $distribution['destination_internal_name']) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e($distribution['document_number'] ?: '—') ?></dd></div>
            <div><dt>Preparado por</dt><dd><?= e($distribution['prepared_by_label_snapshot']) ?></dd></div>
        </dl>
        <?php if (!empty($distribution['notes'])): ?><div class="distribution-note"><strong>Observaciones</strong><p><?= e($distribution['notes']) ?></p></div><?php endif; ?>
    </article>

    <aside class="distribution-panel workflow-panel">
        <span class="eyebrow">FLUJO</span>
        <h2><?= e($statusLabels[$distribution['status']] ?? $distribution['status']) ?></h2>

        <?php if ($distribution['status'] === 'prepared' && sindesg_role_action_allowed('distribution', 'authorize', 'POST', (array) sindesg_user())): ?>
            <p>Autorice la transferencia después de verificar cantidades y stock del Almacén Central.</p>
            <form method="post" action="<?= e(sindesg_url('?module=distribution&action=authorize')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="distribution_id" value="<?= e($distribution['id']) ?>">
                <div class="distribution-employee-picker" data-distribution-picker="authorize" data-search-url="<?= e($employeeSearchUrl) ?>">
                    <label><span>Funcionario autorizador *</span><input type="search" data-distribution-search required autocomplete="off" placeholder="Nombre, cédula o posición..."><input type="hidden" name="authorized_by_employee_id" data-distribution-id></label>
                    <div class="distribution-picker-results" data-distribution-results hidden></div>
                    <div class="distribution-picker-selection" data-distribution-selection hidden></div>
                </div>
                <label class="distribution-notes-field"><span>Observación</span><textarea name="authorization_notes" rows="2"></textarea></label>
                <button class="primary-button distribution-full-button" type="submit">Autorizar transferencia</button>
            </form>
            <form method="post" action="<?= e(sindesg_url('?module=distribution&action=cancel')) ?>" class="distribution-cancel-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="distribution_id" value="<?= e($distribution['id']) ?>">
                <button class="distribution-danger-button" type="submit">Cancelar transferencia</button>
            </form>
        <?php elseif ($distribution['status'] === 'authorized' && sindesg_role_action_allowed('distribution', 'dispatch', 'POST', (array) sindesg_user())): ?>
            <p>Al despachar, el stock saldrá del Central e ingresará al inventario en tránsito.</p>
            <form method="post" action="<?= e(sindesg_url('?module=distribution&action=dispatch')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="distribution_id" value="<?= e($distribution['id']) ?>">
                <div class="distribution-employee-picker" data-distribution-picker="dispatch" data-search-url="<?= e($employeeSearchUrl) ?>">
                    <label><span>Funcionario que entrega *</span><input type="search" data-distribution-search required autocomplete="off" placeholder="Nombre, cédula o posición..."><input type="hidden" name="delivered_by_employee_id" data-distribution-id></label>
                    <div class="distribution-picker-results" data-distribution-results hidden></div><div class="distribution-picker-selection" data-distribution-selection hidden></div>
                </div>
                <label class="distribution-notes-field"><span>Observación de despacho</span><textarea name="dispatch_notes" rows="2"></textarea></label>
                <button class="primary-button distribution-full-button" type="submit">Despachar al P4</button>
            </form>
            <form method="post" action="<?= e(sindesg_url('?module=distribution&action=cancel')) ?>" class="distribution-cancel-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="distribution_id" value="<?= e($distribution['id']) ?>">
                <button class="distribution-danger-button" type="submit">Cancelar transferencia</button>
            </form>
        <?php elseif ($distribution['status'] === 'dispatched' && sindesg_role_action_allowed('distribution', 'receive', 'POST', (array) sindesg_user())): ?>
            <p>Los artículos están contabilizados en <strong>Inventario en tránsito</strong>. El P4 debe confirmar la recepción.</p>
            <form method="post" action="<?= e(sindesg_url('?module=distribution&action=receive')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="distribution_id" value="<?= e($distribution['id']) ?>">
                <?php if (in_array('P4', (array) (sindesg_user()['role_codes'] ?? []), true) && !in_array('ADMIN', (array) (sindesg_user()['role_codes'] ?? []), true) && !in_array('CENTRAL', (array) (sindesg_user()['role_codes'] ?? []), true)): ?>
                    <p>Recibe: <strong><?= e(sindesg_user()['display_name'] ?? 'Funcionario P4') ?></strong>. Se valida la identidad de sesión en RRHH2029.</p>
                <?php else: ?>
                <div class="distribution-employee-picker" data-distribution-picker="receive" data-search-url="<?= e($employeeSearchUrl) ?>">
                    <label><span>Funcionario que recibe en P4 *</span><input type="search" data-distribution-search required autocomplete="off" placeholder="Nombre, cédula o posición..."><input type="hidden" name="received_by_employee_id" data-distribution-id></label>
                    <div class="distribution-picker-results" data-distribution-results hidden></div><div class="distribution-picker-selection" data-distribution-selection hidden></div>
                </div>
                <?php endif; ?>
                <label class="distribution-notes-field"><span>Observación de recepción</span><textarea name="receipt_notes" rows="2"></textarea></label>
                <button class="primary-button distribution-full-button" type="submit">Confirmar recepción en P4</button>
            </form>
        <?php else: ?>
            <div class="distribution-summary">
                <?php if (!empty($distribution['authorized_by_label_snapshot'])): ?><span><strong><?= e($distribution['authorized_by_label_snapshot']) ?></strong><small>Autorizó</small></span><?php endif; ?>
                <?php if (!empty($distribution['delivered_by_label_snapshot'])): ?><span><strong><?= e($distribution['delivered_by_label_snapshot']) ?></strong><small>Entregó</small></span><?php endif; ?>
                <?php if (!empty($distribution['received_by_label_snapshot'])): ?><span><strong><?= e($distribution['received_by_label_snapshot']) ?></strong><small>Recibió</small></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</section>

<?php if (in_array($distribution['status'], ['dispatched', 'received'], true)): ?>
<section class="distribution-panel distribution-verification-panel">
    <div class="distribution-section-head">
        <div>
            <span class="eyebrow">VERIFICACIÓN LOGÍSTICA</span>
            <h2>Central → Tránsito → P4</h2>
        </div>
        <span class="distribution-verification-badge <?= !empty($distributionVerification['all_verified']) ? 'ok' : 'warn' ?>">
            <?= e((int) ($distributionVerification['verified'] ?? 0)) ?>/<?= e((int) ($distributionVerification['expected'] ?? 0)) ?> renglones verificados
        </span>
    </div>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Central OUT</th>
                <th>Tránsito IN</th>
                <th>Tránsito OUT</th>
                <th>P4 IN</th>
                <th>Central actual</th>
                <th>Tránsito actual</th>
                <th>P4 actual</th>
                <th>Estado</th>
                <th>Kardex</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($distributionVerification['rows'] ?? []) as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['product_name']) ?></strong>
                        <small class="table-subline"><?= e($row['variant_code']) ?><?= !empty($row['size_name']) ? ' · Talla ' . e($row['size_name']) : '' ?></small>
                    </td>
                    <td class="distribution-qty"><?= e(number_format((float) $row['expected_quantity'], 3, '.', ',')) ?></td>
                    <td><?= !empty($row['dispatch_central_id']) ? e(number_format((float) $row['dispatch_central_quantity'], 3, '.', ',')) : '—' ?></td>
                    <td><?= !empty($row['dispatch_transit_id']) ? e(number_format((float) $row['dispatch_transit_quantity'], 3, '.', ',')) : '—' ?></td>
                    <td><?= !empty($row['receive_transit_id']) ? e(number_format((float) $row['receive_transit_quantity'], 3, '.', ',')) : '—' ?></td>
                    <td><?= !empty($row['receive_p4_id']) ? e(number_format((float) $row['receive_p4_quantity'], 3, '.', ',')) : '—' ?></td>
                    <td><?= e(number_format((float) $row['central_stock'], 3, '.', ',')) ?></td>
                    <td><?= e(number_format((float) $row['transit_stock'], 3, '.', ',')) ?></td>
                    <td><?= e(number_format((float) $row['p4_stock'], 3, '.', ',')) ?></td>
                    <td>
                        <span class="distribution-verification-status <?= !empty($row['verified']) ? 'ok' : 'warn' ?>">
                            <?= e($row['verification_label']) ?>
                        </span>
                    </td>
                    <td>
                        <a class="distribution-kardex-link" href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int) $row['product_id'])) ?>">
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

<section class="distribution-panel">
    <div class="distribution-section-head"><div><span class="eyebrow">CONTENIDO</span><h2>Artículos transferidos</h2></div></div>
    <div class="responsive-table">
        <table>
            <thead><tr><th>Categoría</th><th>Artículo</th><th>Variante</th><th>Talla</th><th>Beneficiario previsto</th><th>Cantidad</th></tr></thead>
            <tbody>
            <?php foreach ($distributionItems as $item): ?>
                <tr>
                    <td><?= e($item['category_name']) ?></td>
                    <td><?= e($item['product_name']) ?></td>
                    <td><code><?= e($item['variant_code']) ?></code></td>
                    <td><?= e($item['size_name'] ?: '—') ?></td>
                    <td><?= e($item['beneficiary_label_snapshot'] ?: 'Cantidad consolidada') ?></td>
                    <td class="distribution-qty"><?= e(number_format((float) $item['quantity'], 3, '.', ',')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="distribution-timeline">
    <div class="<?= $distribution['prepared_at'] ? 'done' : '' ?>"><strong>1. Preparada</strong><span><?= e($distribution['prepared_at'] ?: 'Pendiente') ?></span></div>
    <div class="<?= $distribution['authorized_at'] ? 'done' : '' ?>"><strong>2. Autorizada</strong><span><?= e($distribution['authorized_at'] ?: 'Pendiente') ?></span></div>
    <div class="<?= $distribution['dispatched_at'] ? 'done' : '' ?>"><strong>3. Despachada</strong><span><?= e($distribution['dispatched_at'] ?: 'Pendiente') ?></span></div>
    <div class="<?= $distribution['received_at'] ? 'done' : '' ?>"><strong>4. Recibida P4</strong><span><?= e($distribution['received_at'] ?: 'Pendiente') ?></span></div>
</section>

<div class="distribution-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=distribution')) ?>">← Distribución</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $distribution['request_id'])) ?>">Ver solicitud</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $distribution['destination_warehouse_id'])) ?>">Ver P4</a>
</div>
