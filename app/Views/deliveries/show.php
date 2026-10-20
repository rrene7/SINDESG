<?php
declare(strict_types=1);
$deliveryRoles = (array) (sindesg_user()['role_codes'] ?? []);
$deliveryP4Only = in_array('P4', $deliveryRoles, true)
    && !in_array('ADMIN', $deliveryRoles, true)
    && !in_array('CENTRAL', $deliveryRoles, true);


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
        <span class="eyebrow">ENTREGA INDIVIDUAL</span>
        <h1><?= e($delivery['recipient_label_snapshot']) ?></h1>
        <p><?= e($delivery['recipient_document_snapshot'] ?: 'Sin cédula histórica') ?> · <?= e($delivery['p4_name'] ?: $delivery['p4_internal_name']) ?></p>
    </div>
    <div class="delivery-detail-status">
        <span class="delivery-status <?= e($delivery['status']) ?>"><?= e($statusLabels[$delivery['status']] ?? $delivery['status']) ?></span>
        <strong><?= e(sindesg_units($totalQuantity)) ?></strong>
        <small>unidades</small>
    </div>
</section>

<section class="delivery-detail-grid">
    <article class="delivery-panel">
        <span class="eyebrow">COMPROBANTE</span>
        <h2>Datos del despacho</h2>
        <dl class="delivery-dl">
            <div><dt>Referencia para auditoría</dt><dd><details><summary>Ver folio interno</summary><?= e($delivery['delivery_code']) ?></details></dd></div>
            <div><dt>Fecha y hora</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) $delivery['delivered_at']))) ?></dd></div>
            <div><dt>Almacén</dt><dd><?= e($delivery['p4_name'] ?: $delivery['p4_internal_name']) ?></dd></div>
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
                <?php if ($deliveryP4Only): ?>
                    <div class="p4-signature-wrap" data-p4-signature>
                        <strong>Firma del funcionario que recibe</strong>
                        <p>Verifique la identidad del beneficiario antes de capturar su firma.</p>
                        <canvas width="650" height="180" aria-label="Área para firma manuscrita de recibido" data-p4-signature-canvas></canvas>
                        <input type="hidden" name="recipient_signature_png" data-p4-signature-value>
                        <button type="button" class="secondary-button" data-p4-signature-clear>Limpiar firma</button>
                        <label class="p4-signature-ack"><input type="checkbox" data-p4-signature-ack required> Confirmo que el receptor identificado firmó el comprobante al recibir los artículos.</label>
                        <small>La firma capturada es una constancia manuscrita digitalizada, no una firma electrónica certificada.</small>
                        <label class="p4-signature-ack">
                            <input type="checkbox" name="issuer_ack" value="1" required>
                            Yo, responsable P4 autenticado, autorizo expresamente asociar mi firma institucional registrada a esta entrega.
                        </label>
                        <a href="<?= e(sindesg_url('?module=profile&action=signature')) ?>">Registrar o actualizar mi firma P4</a>
                    </div>
                <?php endif; ?>
                <button class="primary-button delivery-full-button" type="submit" data-p4-confirm-submit <?= $deliveryItems === [] ? 'disabled' : '' ?>>Confirmar despacho</button>
            </form>

            <?php if (!$deliveryP4Only): ?>
            <form method="post" action="<?= e(sindesg_url('?module=deliveries&action=cancel')) ?>" class="delivery-cancel-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">
                <button class="delivery-danger-button" type="submit">Cancelar borrador</button>
            </form>
            <?php endif; ?>
        <?php elseif ($delivery['status'] === 'confirmed'): ?>
            <p>La entrega está confirmada y ya forma parte del historial de dotación del funcionario.</p>
            <button class="secondary-button delivery-full-button" type="button" onclick="window.print()">Imprimir comprobante</button>
            <a class="secondary-button delivery-full-button" href="<?= e(sindesg_url('?module=deliveries&action=history&employee_id=' . (int) $delivery['recipient_employee_id'])) ?>">Historial del funcionario</a>
        <?php else: ?>
            <p>El borrador fue cancelado sin afectar el inventario del P4.</p>
        <?php endif; ?>
    </aside>
</section>

<?php if ($delivery['status'] === 'confirmed'): ?>
<section class="delivery-panel delivery-verification-panel">
    <div class="delivery-section-head">
        <div>
            <span class="eyebrow">VERIFICACIÓN DE INVENTARIO</span>
            <h2>P4 → funcionario</h2>
        </div>
        <span class="delivery-verification-badge <?= !empty($deliveryVerification['all_verified']) ? 'ok' : 'warn' ?>">
            <?= e((int) ($deliveryVerification['verified'] ?? 0)) ?>/<?= e((int) ($deliveryVerification['expected'] ?? 0)) ?> renglones verificados
        </span>
    </div>

    <?php if (!empty($deliveryVerification['all_verified'])): ?>
        <p class="delivery-verification-copy">
            Cada renglón tiene un movimiento OUT único en el P4 y la entrega ya forma parte del historial del funcionario.
        </p>
    <?php else: ?>
        <div class="delivery-warning">
            El despacho figura confirmado, pero uno o más renglones no coinciden con el movimiento esperado.
            No registre otro despacho para compensar: revise esta trazabilidad.
        </div>
    <?php endif; ?>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Artículo</th>
                <th>Entregado</th>
                <th>P4 OUT</th>
                <th>Stock P4 actual</th>
                <th>Solicitud</th>
                <th>Recibido / entregado</th>
                <th>Estado</th>
                <th>Kardex</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($deliveryVerification['rows'] ?? []) as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['product_name']) ?></strong>
                        <small class="delivery-cell-sub">
                            <?= !empty($row['size_name']) ? 'Talla ' . e($row['size_name']) : 'Presentación estándar' ?>
                        </small>
                    </td>
                    <td class="delivery-qty"><?= e(sindesg_units((float) $row['expected_quantity'])) ?></td>
                    <td>
                        <?php if (!empty($row['movement_id'])): ?>
                            <?= e(sindesg_units((float) $row['movement_quantity'])) ?>
                            <small class="delivery-cell-sub" title="Movimiento de kardex #<?= e($row['movement_id']) ?>">Movimiento registrado</small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e(sindesg_units((float) $row['p4_stock'])) ?></td>
                    <td><?= e($row['request_code'] ? 'Dotación solicitada' : 'Stock libre P4') ?></td>
                    <td>
                        <?php if ($row['request_item_id'] !== null): ?>
                            <?= e(sindesg_units((float) $row['request_received'])) ?>
                            /
                            <?= e(sindesg_units((float) $row['request_delivered'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="delivery-verification-status <?= !empty($row['verified']) ? 'ok' : 'warn' ?>">
                            <?= !empty($row['verified']) ? 'Verificado' : 'Revisar' ?>
                        </span>
                    </td>
                    <td>
                        <a class="delivery-link"
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
        <?php if (!$deliveryP4Only): ?>
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
                                
                                . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '')
                                . ' · Libre ' . sindesg_units((float) $option['manual_available'])
                                . ' / Físico ' . sindesg_units((float) $option['physical_stock'])) ?>
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
                <tr><th>Artículo</th><th>Variante</th><th>Talla</th><th>Cantidad</th><th>Origen</th><th>Transferencia</th><?php if ($delivery['status'] === 'draft' || $delivery['status'] === 'confirmed'): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($deliveryItems as $item): ?>
                    <tr>
                        <td><strong><?= e($item['product_name']) ?></strong><small class="delivery-cell-sub"><?= e($item['category_name']) ?></small></td>
                        <td><?= e(($item['size_name'] ?? '') ? 'Talla ' . $item['size_name'] : 'Presentación estándar') ?></td>
                        <td><?= e($item['size_name'] ?: '—') ?></td>
                        <td class="delivery-qty"><?= e(sindesg_units((float) $item['quantity'])) ?></td>
                        <td><?= e($item['request_code'] ? 'Dotación solicitada' : 'Stock libre P4') ?></td>
                        <td><?= e($item['transfer_code'] ? 'Transferencia desde Central' : '—') ?></td>
                        <?php if ($delivery['status'] === 'draft' && !$deliveryP4Only): ?>
                            <td>
                                <form method="post" action="<?= e(sindesg_url('?module=deliveries&action=remove-item')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="delivery_id" value="<?= e($delivery['id']) ?>">
                                    <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                                    <button class="delivery-remove-button" type="submit">×</button>
                                </form>
                            </td>
                        <?php elseif ($delivery['status'] === 'confirmed'): ?>
                            <td>
                                <a class="delivery-link"
                                   href="<?= e(sindesg_url('?module=adjustments&action=new&source_delivery_item_id=' . (int) $item['id'])) ?>">
                                    Devolver / cambiar
                                </a>
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
                    <span><?= e($row['product_name'] . ($row['size_name'] ? ' · Talla ' . $row['size_name'] : '')) ?></span>
                    <small><?= e(sindesg_units((float) $row['quantity'])) ?> · <?= e(date('d/m/Y', strtotime((string) $row['delivered_at']))) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="delivery-bottom-actions no-print">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">← Despachos</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $delivery['p4_warehouse_id'])) ?>">Ver P4</a>
</div>

<?php if (!empty($receiptEvidence)): ?>
<?php
// Read-only representation of the evidence captured with this confirmed delivery.
$receiptCapturedRaw = (string) ($receiptEvidence['captured_at'] ?? '');
$receiptCapturedUnix = $receiptCapturedRaw !== '' ? strtotime($receiptCapturedRaw) : false;
$receiptCapturedLabel = $receiptCapturedUnix === false
    ? ($receiptCapturedRaw !== '' ? $receiptCapturedRaw : 'No disponible')
    : date('d/m/Y H:i', $receiptCapturedUnix);
$receiptItemCount = count($deliveryItems);
$receiptTotalText = sindesg_units((float) $totalQuantity);
?>
<style>
/* A4 institutional receipt: self-contained, responsive and paginated for long deliveries. */
#signed-receipt{background:#fff;color:#162c41;border:1px solid #d3dfe9;border-radius:13px;padding:30px 36px;max-width:900px;margin:22px auto;font-family:Arial,Helvetica,sans-serif;box-shadow:0 12px 36px rgba(0,0,0,.16);font-size:12px;line-height:1.42}
#signed-receipt *{box-sizing:border-box}
#signed-receipt .receipt-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:16px;border-bottom:3px solid #164765}
#signed-receipt .receipt-mark{display:flex;align-items:center;gap:13px;min-width:0}
#signed-receipt .receipt-emblem{height:54px;width:54px;flex:0 0 54px;display:grid;place-items:center;border:2px solid #164765;border-radius:9px;font-size:20px;font-weight:800;color:#164765}
#signed-receipt .receipt-agency{font-size:14px;font-weight:800;letter-spacing:.045em;color:#123955}
#signed-receipt .receipt-subagency{font-size:10px;letter-spacing:.045em;color:#546b7c;margin-top:3px}
#signed-receipt .receipt-number{text-align:right;font-size:10px;color:#506479;min-width:145px}
#signed-receipt .receipt-number strong{display:block;font-size:17px;letter-spacing:.02em;color:#102f4a;margin:5px 0 10px;overflow-wrap:anywhere}
#signed-receipt .receipt-print-action{background:#164765;color:#fff;border:0;border-radius:7px;padding:9px 12px;font-size:11px;font-weight:700;cursor:pointer}
#signed-receipt .receipt-title{font-size:22px;line-height:1.25;font-weight:800;color:#12334d;margin:20px 0 5px}
#signed-receipt .receipt-intro{font-size:11px;color:#5b7182;margin:0 0 17px}
#signed-receipt .receipt-highlights{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:0 0 20px}
#signed-receipt .receipt-highlight{border:1px solid #d5e2eb;border-radius:8px;padding:10px 12px;background:#f5f9fc}
#signed-receipt .receipt-highlight span{display:block;color:#647789;font-size:9px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}
#signed-receipt .receipt-highlight strong{display:block;color:#173953;font-size:14px;margin-top:3px;overflow-wrap:anywhere}
#signed-receipt .receipt-block-heading{font-size:11px;font-weight:800;letter-spacing:.055em;color:#164765;text-transform:uppercase;border-bottom:1px solid #dae5ed;padding-bottom:7px;margin:18px 0 11px}
#signed-receipt .receipt-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 24px}
#signed-receipt .receipt-fact{min-width:0}
#signed-receipt .receipt-fact-label{display:block;font-size:10px;color:#5f7387;margin-bottom:3px}
#signed-receipt .receipt-fact strong{display:block;color:#182f43;font-size:12px;font-weight:700;overflow-wrap:anywhere}
#signed-receipt .receipt-fact small{display:block;color:#5f7387;font-size:10px;margin-top:3px}
#signed-receipt table{width:100%;border-collapse:collapse;table-layout:fixed;color:#162c41;font-size:12px}
#signed-receipt thead{background:#eaf2f7}
#signed-receipt th,#signed-receipt td{padding:9px 11px;text-align:left;border-bottom:1px solid #dce6ee;vertical-align:top;overflow-wrap:anywhere}
#signed-receipt th:nth-child(2),#signed-receipt td:nth-child(2){width:24%}
#signed-receipt th:last-child,#signed-receipt td:last-child{text-align:right;width:21%}
#signed-receipt .receipt-total{display:flex;justify-content:flex-end;gap:14px;align-items:baseline;margin-top:10px;padding:10px 12px;border-top:2px solid #164765;color:#1d435d;font-weight:700}
#signed-receipt .receipt-total strong{font-size:17px;color:#143c58}
#signed-receipt .receipt-additional{padding:9px 11px;background:#f5f9fc;border:1px solid #dce6ee;border-radius:7px;margin:13px 0;font-size:11px;overflow-wrap:anywhere}
#signed-receipt .receipt-attestation{font-size:10px;line-height:1.5;color:#4d6073;margin:7px 0 12px}
#signed-receipt .receipt-signoff{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:28px;margin:18px 0 0}
#signed-receipt .receipt-signature-box{min-height:152px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;text-align:center;min-width:0;padding-top:3px}
#signed-receipt .receipt-signature-image{height:90px;width:100%;object-fit:contain;display:block}
#signed-receipt .receipt-missing-signature{display:grid;place-items:center;height:90px;width:100%;padding:10px;color:#64788a;font-size:10px}
#signed-receipt .receipt-signature-box .receipt-line{border-top:1px solid #536c7f;padding-top:7px;width:100%;font-size:11px;font-weight:700;color:#1c3950}
#signed-receipt .receipt-signature-box small{font-size:10px;color:#536a7d;margin-top:4px;overflow-wrap:anywhere}
#signed-receipt .receipt-integrity{margin-top:20px;background:#f2f6fa;border:1px solid #e0e8ef;border-radius:7px;padding:11px 13px;font-size:9px;color:#4e6274}
#signed-receipt .receipt-integrity strong{display:block;font-size:10px;color:#244760;margin-bottom:6px}
#signed-receipt .receipt-integrity code{overflow-wrap:anywhere;word-break:break-all;white-space:normal;font-size:9px;color:#263f54}
#signed-receipt .receipt-integrity div+div{margin-top:4px}
#signed-receipt .receipt-integrity-note{margin-top:7px;color:#63798a}
#signed-receipt .receipt-footer{display:flex;justify-content:space-between;gap:12px;margin-top:16px;border-top:1px solid #dae5ed;padding-top:11px;font-size:10px;color:#597183}
#signed-receipt .receipt-footer span:last-child{white-space:nowrap}
@media(max-width:620px){
 #signed-receipt{padding:18px;margin:16px 0}
 #signed-receipt .receipt-top{flex-wrap:wrap}
 #signed-receipt .receipt-number{text-align:left}
 #signed-receipt .receipt-facts{grid-template-columns:1fr}
 #signed-receipt .receipt-highlights{grid-template-columns:1fr 1fr}
 #signed-receipt .receipt-signoff{gap:12px}
 #signed-receipt .receipt-emblem{width:44px;height:44px;flex-basis:44px}
}
@media print{
 @page{size:A4 portrait;margin:13mm}
 html,body{background:#fff!important;color:#000!important}
 body::before,.site-header,.main-nav,.more-menu,.site-footer,.flash-stack,.search-overlay{display:none!important}
 .app-shell,.main-content{background:transparent!important;border:0!important;box-shadow:none!important;max-width:none!important;width:100%!important;margin:0!important;padding:0!important}
 .main-content > :not(#signed-receipt){display:none!important}
 #signed-receipt{position:static!important;display:block!important;visibility:visible!important;max-width:none!important;width:100%!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:#fff!important;color:#162c41!important}
 #signed-receipt .receipt-print-action{display:none!important}
 #signed-receipt .receipt-highlights{grid-template-columns:repeat(3,minmax(0,1fr))}
 #signed-receipt .receipt-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
 #signed-receipt .receipt-signoff{grid-template-columns:repeat(2,minmax(0,1fr))}
 #signed-receipt thead{display:table-header-group;print-color-adjust:exact;-webkit-print-color-adjust:exact}
 #signed-receipt .receipt-highlight,#signed-receipt .receipt-integrity{print-color-adjust:exact;-webkit-print-color-adjust:exact}
 #signed-receipt tr,#signed-receipt .receipt-signoff,#signed-receipt .receipt-signature-box,#signed-receipt .receipt-integrity,#signed-receipt .receipt-footer{break-inside:avoid;page-break-inside:avoid}
 #signed-receipt .receipt-block-heading{break-after:avoid;page-break-after:avoid}
 #signed-receipt .receipt-highlights{break-inside:avoid;page-break-inside:avoid}
}
</style>
<section id="signed-receipt" aria-label="Comprobante institucional de recibido">
    <header class="receipt-top">
        <div class="receipt-mark">
            <div class="receipt-emblem" aria-hidden="true">PN</div>
            <div>
                <div class="receipt-agency">POLICÍA NACIONAL DE PANAMÁ</div>
                <div class="receipt-subagency">SINDESG · SISTEMA DE INVENTARIO Y DESPACHO DE SERVICIOS GENERALES</div>
            </div>
        </div>
        <div class="receipt-number">
            CONSTANCIA DE RECIBIDO
            <strong><?= e($delivery['delivery_code']) ?></strong>
            <button type="button" class="receipt-print-action" onclick="window.print()">Imprimir / guardar PDF</button>
        </div>
    </header>

    <h2 class="receipt-title">Comprobante de entrega de dotación</h2>
    <p class="receipt-intro">Registro institucional de entrega física, aceptación de recibido y responsabilidad del despacho.</p>

    <div class="receipt-highlights" aria-label="Resumen del comprobante">
        <div class="receipt-highlight"><span>Fecha de firma y registro</span><strong><?= e($receiptCapturedLabel) ?></strong></div>
        <div class="receipt-highlight"><span>Artículos registrados</span><strong><?= e($receiptItemCount) ?> renglones</strong></div>
        <div class="receipt-highlight"><span>Unidades entregadas</span><strong><?= e($receiptTotalText) ?></strong></div>
    </div>

    <h3 class="receipt-block-heading">Identificación y responsabilidad</h3>
    <div class="receipt-facts">
        <div class="receipt-fact">
            <span class="receipt-fact-label">Unidad / almacén que entrega</span>
            <strong><?= e($delivery['p4_name'] ?: $delivery['p4_internal_name'] ?: $delivery['p4_code']) ?></strong>
        </div>
        <div class="receipt-fact">
            <span class="receipt-fact-label">Fecha de entrega</span>
            <strong><?= e(date('d/m/Y H:i', strtotime((string) $delivery['delivered_at']))) ?></strong>
        </div>
        <div class="receipt-fact">
            <span class="receipt-fact-label">Funcionario receptor</span>
            <strong><?= e($delivery['recipient_label_snapshot']) ?></strong>
            <small><?= e($delivery['recipient_rank_snapshot'] ?: 'Rango no registrado') ?> · Posición <?= e($delivery['recipient_position_snapshot'] ?: 'No registrada') ?></small>
        </div>
        <div class="receipt-fact">
            <span class="receipt-fact-label">Documento de identidad del receptor</span>
            <strong><?= e($delivery['recipient_document_snapshot'] ?: 'No registrado') ?></strong>
        </div>
        <div class="receipt-fact">
            <span class="receipt-fact-label">Funcionario responsable de la entrega</span>
            <strong><?= e($delivery['delivered_by_label_snapshot']) ?></strong>
            <small><?= e($delivery['delivered_by_rank_snapshot'] ?: 'Rango no registrado') ?> · Posición <?= e($delivery['delivered_by_position_snapshot'] ?: 'No registrada') ?></small>
        </div>
        <div class="receipt-fact">
            <span class="receipt-fact-label">Documento de referencia, si existe</span>
            <strong><?= e($delivery['document_number'] ?: 'Sin referencia adicional') ?></strong>
        </div>
    </div>

    <h3 class="receipt-block-heading">Detalle de artículos entregados</h3>
    <table aria-label="Detalle de artículos entregados">
        <thead><tr><th scope="col">Artículo entregado</th><th scope="col">Talla</th><th scope="col">Unidades</th></tr></thead>
        <tbody>
        <?php foreach ($deliveryItems as $item): ?>
            <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e($item['size_name'] ?: 'Sin talla') ?></td>
                <td><?= e(sindesg_units((float) $item['quantity'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="receipt-total"><span>Total entregado</span><strong><?= e($receiptTotalText) ?> <?= abs((float) $totalQuantity - 1.0) < 0.000001 ? 'unidad' : 'unidades' ?></strong></div>

    <?php if (!empty($delivery['notes'])): ?>
        <div class="receipt-additional"><strong>Observaciones registradas:</strong> <?= e($delivery['notes']) ?></div>
    <?php endif; ?>
    <?php if ((int) ($delivery['scope_override_enabled'] ?? 0) === 1): ?>
        <div class="receipt-additional"><strong>Entrega con excepción de adscripción autorizada.</strong>
            <?= e($delivery['scope_override_reason'] ?: 'Motivo no registrado') ?></div>
    <?php endif; ?>

    <h3 class="receipt-block-heading">Conformidad y recepción</h3>
    <p class="receipt-attestation">Las firmas corresponden a las evidencias archivadas al confirmar esta entrega. Reemplazar posteriormente una firma institucional no modifica los comprobantes históricos.</p>
    <div class="receipt-signoff">
        <div class="receipt-signature-box">
            <img class="receipt-signature-image" alt="Firma manuscrita registrada del funcionario receptor" src="data:image/png;base64,<?= e(base64_encode((string) $receiptEvidence['signature_png'])) ?>">
            <div class="receipt-line">Firma del funcionario receptor</div>
            <small><?= e($delivery['recipient_label_snapshot']) ?></small>
        </div>
        <div class="receipt-signature-box">
            <?php if (!empty($issuerReceiptEvidence)): ?>
                <img class="receipt-signature-image" alt="Firma histórica del responsable P4" src="data:image/png;base64,<?= e(base64_encode((string) $issuerReceiptEvidence['signature_png'])) ?>">
            <?php else: ?>
                <div class="receipt-missing-signature">Sin firma personal archivada en esta entrega histórica</div>
            <?php endif; ?>
            <div class="receipt-line">Firma del responsable de la entrega</div>
            <small><?= e($delivery['delivered_by_label_snapshot']) ?></small>
            <small>Identificado mediante sesión institucional</small>
        </div>
    </div>

    <div class="receipt-integrity">
        <strong>Control de integridad documental</strong>
        <div>Firma del receptor · SHA-256: <code><?= e($receiptEvidence['signature_sha256']) ?></code></div>
        <?php if (!empty($issuerReceiptEvidence)): ?>
            <div>Firma del responsable P4 · SHA-256: <code><?= e($issuerReceiptEvidence['signature_sha256']) ?></code></div>
        <?php endif; ?>
        <div class="receipt-integrity-note">Estas huellas identifican las imágenes archivadas. No constituyen por sí solas una firma electrónica certificada ni una validación externa.</div>
    </div>
    <footer class="receipt-footer">
        <span>Generado desde SINDESG · Evidencia histórica del despacho sujeta a auditoría institucional</span>
        <span>Folio <?= e($delivery['delivery_code']) ?></span>
    </footer>
</section>
<?php endif; ?>

<?php if ($deliveryP4Only && $delivery['status'] === 'draft'): ?>
<style>
.p4-signature-wrap{display:grid;gap:9px;margin:18px 0;padding:16px;border:1px solid #245779;border-radius:12px;background:#061b30}
.p4-signature-wrap p,.p4-signature-wrap small{color:#9fc1d8;font-size:.82rem}
.p4-signature-wrap canvas{width:100%;height:180px;background:#fff;border-radius:8px;touch-action:none;cursor:crosshair}
.p4-signature-ack{display:flex;gap:9px;align-items:start;font-size:.87rem}
</style>
<script>
(() => {
 const root=document.querySelector('[data-p4-signature]');
 if(!root) return;
 const canvas=root.querySelector('canvas'),ctx=canvas.getContext('2d'),form=root.closest('form');
 const input=root.querySelector('[data-p4-signature-value]');
 const ack=root.querySelector('[data-p4-signature-ack]');
 const submit=form.querySelector('[data-p4-confirm-submit]');
 let drawing=false,drawn=false;
 ctx.strokeStyle='#142b47';ctx.lineWidth=2.7;ctx.lineCap='round';ctx.lineJoin='round';
 function pos(ev){const r=canvas.getBoundingClientRect();return {x:(ev.clientX-r.left)*canvas.width/r.width,y:(ev.clientY-r.top)*canvas.height/r.height};}
 canvas.addEventListener('pointerdown',ev=>{drawing=true;const p=pos(ev);ctx.beginPath();ctx.moveTo(p.x,p.y);ctx.lineTo(p.x+.1,p.y+.1);ctx.stroke();drawn=true;canvas.setPointerCapture(ev.pointerId);});
 canvas.addEventListener('pointermove',ev=>{if(!drawing)return;const p=pos(ev);ctx.lineTo(p.x,p.y);ctx.stroke();});
 canvas.addEventListener('pointerup',()=>{drawing=false;});
 canvas.addEventListener('pointercancel',()=>{drawing=false;});
 root.querySelector('[data-p4-signature-clear]').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);drawn=false;input.value='';ack.checked=false;});
 form.addEventListener('submit',ev=>{
   if(!drawn || !ack.checked){ev.preventDefault();alert('Capture la firma del receptor y confirme su identidad.');return;}
   input.value=canvas.toDataURL('image/png');
   if(input.value.length>220000){ev.preventDefault();alert('La firma excede el tamaño permitido. Límpiela e intente nuevamente.');input.value='';return;}
   submit.disabled=true;
 });
})();
</script>
<?php endif; ?>
