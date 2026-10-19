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
                <?php if ($deliveryP4Only): ?>
                    <div class="p4-signature-wrap" data-p4-signature>
                        <strong>Firma del funcionario que recibe</strong>
                        <p>Verifique la identidad del beneficiario antes de capturar su firma.</p>
                        <canvas width="650" height="180" aria-label="Área para firma manuscrita de recibido" data-p4-signature-canvas></canvas>
                        <input type="hidden" name="recipient_signature_png" data-p4-signature-value>
                        <button type="button" class="secondary-button" data-p4-signature-clear>Limpiar firma</button>
                        <label class="p4-signature-ack"><input type="checkbox" data-p4-signature-ack required> Confirmo que el receptor identificado firmó el comprobante al recibir los artículos.</label>
                        <small>La firma capturada es una constancia manuscrita digitalizada, no una firma electrónica certificada.</small>
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
                            <?= e($row['variant_code']) ?><?= !empty($row['size_name']) ? ' · Talla ' . e($row['size_name']) : '' ?>
                        </small>
                    </td>
                    <td class="delivery-qty"><?= e(number_format((float) $row['expected_quantity'], 3, '.', ',')) ?></td>
                    <td>
                        <?php if (!empty($row['movement_id'])): ?>
                            <?= e(number_format((float) $row['movement_quantity'], 3, '.', ',')) ?>
                            <small class="delivery-cell-sub">#<?= e($row['movement_id']) ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e(number_format((float) $row['p4_stock'], 3, '.', ',')) ?></td>
                    <td><?= e($row['request_code'] ?: 'Stock libre P4') ?></td>
                    <td>
                        <?php if ($row['request_item_id'] !== null): ?>
                            <?= e(number_format((float) $row['request_received'], 3, '.', ',')) ?>
                            /
                            <?= e(number_format((float) $row['request_delivered'], 3, '.', ',')) ?>
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
                        <td><code><?= e($item['variant_code']) ?></code></td>
                        <td><?= e($item['size_name'] ?: '—') ?></td>
                        <td class="delivery-qty"><?= e(number_format((float) $item['quantity'], 3, '.', ',')) ?></td>
                        <td><?= e($item['request_code'] ?: 'Stock libre P4') ?></td>
                        <td><?= e($item['transfer_code'] ?: '—') ?></td>
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

<?php if (!empty($receiptEvidence)): ?>
<style>
/* Institutional receipt: fully scoped and printable without surrounding dashboard. */
#signed-receipt{background:#fff;color:#15263b;border:1px solid #ced8e3;border-radius:10px;padding:28px 32px;max-width:900px;margin:22px auto;font-family:Arial,Helvetica,sans-serif;box-shadow:0 8px 26px rgba(0,0,0,.12)}
#signed-receipt *{box-sizing:border-box}
#signed-receipt .receipt-top{display:flex;justify-content:space-between;align-items:start;gap:20px;padding-bottom:17px;border-bottom:3px solid #143e63}
#signed-receipt .receipt-mark{display:flex;gap:13px;align-items:center}
#signed-receipt .receipt-emblem{height:53px;width:53px;display:grid;place-items:center;border:2px solid #143e63;border-radius:10px;font-size:19px;font-weight:800;letter-spacing:-1px;color:#143e63}
#signed-receipt .receipt-agency{font-size:13px;font-weight:800;letter-spacing:.07em;color:#143e63}
#signed-receipt .receipt-subagency{font-size:10px;letter-spacing:.09em;color:#526378;margin-top:4px}
#signed-receipt .receipt-number{text-align:right;color:#3c5064;font-size:11px}
#signed-receipt .receipt-number strong{display:block;color:#152f4d;font-size:16px;margin-top:4px}
#signed-receipt .receipt-title{margin:20px 0 4px;font-size:22px;line-height:1.25;color:#142e49;font-weight:800}
#signed-receipt .receipt-intro{color:#586b7c;font-size:11px;margin:0 0 19px}
#signed-receipt .receipt-block-heading{font-size:11px;font-weight:800;letter-spacing:.08em;color:#163e61;text-transform:uppercase;border-bottom:1px solid #dce5ed;padding-bottom:8px;margin:19px 0 10px}
#signed-receipt .receipt-facts{display:grid;grid-template-columns:1fr 1fr;gap:12px 22px}
#signed-receipt .receipt-fact{min-width:0}
#signed-receipt .receipt-fact label{display:block;font-size:10px;color:#64778b;margin-bottom:3px}
#signed-receipt .receipt-fact strong{font-size:12px;color:#1a2939;overflow-wrap:anywhere}
#signed-receipt table{border-collapse:collapse;width:100%;font-size:12px;color:#1b2a3a}
#signed-receipt thead{background:#eaf0f6}
#signed-receipt th,#signed-receipt td{padding:10px 12px;text-align:left;border-bottom:1px solid #dce6ee}
#signed-receipt th:last-child,#signed-receipt td:last-child{text-align:right}
#signed-receipt .receipt-signoff{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:23px}
#signed-receipt .receipt-signature-box{min-height:125px;display:flex;flex-direction:column;justify-content:end;align-items:center;text-align:center}
#signed-receipt .receipt-signature-box img{display:block;height:90px;max-width:100%;object-fit:contain}
#signed-receipt .receipt-signature-box .receipt-line{border-top:1px solid #4d5a6b;width:100%;padding-top:7px;font-size:11px;font-weight:700}
#signed-receipt .receipt-signature-box small{display:block;font-size:10px;color:#5c7082;margin-top:5px}
#signed-receipt .receipt-integrity{margin-top:22px;background:#f2f6fa;border-radius:6px;padding:10px 12px;font-size:9px;color:#485e72}
#signed-receipt .receipt-integrity code{overflow-wrap:anywhere;white-space:normal;font-size:9px;color:#284057}
#signed-receipt .receipt-footer{border-top:1px solid #dce5ed;padding-top:10px;margin-top:16px;color:#657587;font-size:10px;display:flex;justify-content:space-between;gap:12px}
#signed-receipt .receipt-print-action{background:#153f62;color:white;border:0;border-radius:7px;padding:10px 15px;cursor:pointer;font-size:12px;font-weight:700}
@media(max-width:580px){#signed-receipt{padding:17px}#signed-receipt .receipt-facts{grid-template-columns:1fr}#signed-receipt .receipt-signoff{gap:12px}#signed-receipt .receipt-emblem{height:40px;width:40px}}
@media print{
 @page{size:A4 portrait;margin:13mm}
 html,body{background:white!important}
 body *{visibility:hidden!important}
 #signed-receipt,#signed-receipt *{visibility:visible!important}
 #signed-receipt{position:absolute!important;left:0!important;top:0!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:white!important;color:#15263b!important}
 #signed-receipt .receipt-print-action{display:none!important}
 #signed-receipt .receipt-integrity,#signed-receipt thead{print-color-adjust:exact;-webkit-print-color-adjust:exact}
 #signed-receipt .receipt-signoff,#signed-receipt .receipt-integrity{break-inside:avoid}
}
</style>
<section id="signed-receipt" aria-label="Comprobante institucional de recibido">
    <div class="receipt-top">
        <div class="receipt-mark">
            <div class="receipt-emblem" aria-hidden="true">PN</div>
            <div>
                <div class="receipt-agency">POLICÍA NACIONAL DE PANAMÁ</div>
                <div class="receipt-subagency">SINDESG · SERVICIOS GENERALES</div>
            </div>
        </div>
        <div class="receipt-number">
            CONSTANCIA DE RECIBIDO
            <strong><?= e($delivery['delivery_code']) ?></strong>
            <button type="button" class="receipt-print-action" onclick="window.print()">Imprimir / guardar PDF</button>
        </div>
    </div>
    <h2 class="receipt-title">Comprobante de entrega de dotación</h2>
    <p class="receipt-intro">Constancia institucional de recepción física de artículos asignados.</p>
    <h3 class="receipt-block-heading">Datos de la entrega</h3>
    <div class="receipt-facts">
        <div class="receipt-fact"><label>Unidad / almacén</label><strong><?= e($delivery['p4_name'] ?: $delivery['p4_code']) ?></strong></div>
        <div class="receipt-fact"><label>Fecha y hora de registro</label><strong><?= e((string) $receiptEvidence['captured_at']) ?></strong></div>
        <div class="receipt-fact"><label>Funcionario receptor</label><strong><?= e($delivery['recipient_label_snapshot']) ?></strong></div>
        <div class="receipt-fact"><label>Documento de identidad</label><strong><?= e($delivery['recipient_document_snapshot'] ?: 'No registrado') ?></strong></div>
        <div class="receipt-fact"><label>Funcionario que entrega</label><strong><?= e($delivery['delivered_by_label_snapshot']) ?></strong></div>
        <div class="receipt-fact"><label>Estado</label><strong>Entrega confirmada</strong></div>
    </div>
    <h3 class="receipt-block-heading">Detalle de artículos entregados</h3>
    <table aria-label="Detalle de artículos entregados">
        <thead><tr><th>Artículo</th><th>Talla</th><th>Cantidad</th></tr></thead>
        <tbody>
        <?php foreach ($deliveryItems as $item): ?>
            <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e($item['size_name'] ?: '—') ?></td>
                <td><?= e(number_format((float) $item['quantity'], 3, '.', ',')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h3 class="receipt-block-heading">Conformidad y recepción</h3>
    <div class="receipt-signoff">
        <div class="receipt-signature-box">
            <img alt="Firma manuscrita registrada del funcionario receptor" src="data:image/png;base64,<?= e(base64_encode((string) $receiptEvidence['signature_png'])) ?>">
            <div class="receipt-line">Firma del funcionario receptor</div>
            <small><?= e($delivery['recipient_label_snapshot']) ?></small>
        </div>
        <div class="receipt-signature-box">
            <div class="receipt-line">Funcionario responsable de la entrega</div>
            <small><?= e($delivery['delivered_by_label_snapshot']) ?></small>
            <small>Identificado mediante sesión institucional</small>
        </div>
    </div>
    <div class="receipt-integrity">
        <strong>Control de integridad de la firma</strong>
        <div>SHA-256: <code><?= e($receiptEvidence['signature_sha256']) ?></code></div>
        <div>Esta huella identifica la imagen de firma almacenada; no constituye por sí sola certificación electrónica.</div>
    </div>
    <div class="receipt-footer">
        <span>Documento generado desde SINDESG · Registro sujeto a auditoría institucional</span>
        <span><?= e($delivery['delivery_code']) ?></span>
    </div>
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
