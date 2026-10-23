<?php
declare(strict_types=1);

// Only DeliveryController's P4-only guard can render this view.
// Do not accept recipient, P4, or responsible identity from browser fields.
?>
<section class="delivery-hero compact p4-direct-hero">
    <div>
        <span class="eyebrow">ENTREGA NOMINAL · P4</span>
        <h1>Entregar prenda al funcionario</h1>
        <p>Verifique el artículo, capture la firma de recibido y registre la entrega en un solo paso.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">← Volver</a>
</section>

<form method="post" class="delivery-form p4-direct-form"
      action="<?= e(sindesg_url('?module=deliveries&action=deliver-now')) ?>"
      data-p4-direct-signature>
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="request_item_id" value="<?= e($pendingLine['request_item_id']) ?>">
    <!-- Server validates nominal ownership and overwrites all identity fields. -->
    <section class="delivery-panel p4-direct-detail">
        <div class="delivery-section-head">
            <div><span class="eyebrow">DATOS YA REGISTRADOS</span><h2>Verifique la entrega</h2></div>
        </div>
        <div class="delivery-fixed-grid p4-direct-grid">
            <article><span>Funcionario receptor</span>
                <strong><?= e($pendingLine['beneficiary_label_snapshot']) ?></strong>
                <small><?= e($pendingLine['beneficiary_document_snapshot'] ?: 'Sin documento') ?></small></article>
            <article><span>Artículo y talla</span>
                <strong><?= e($pendingLine['product_name']) ?></strong>
                <small><?= e(!empty($pendingLine['size_name']) ? 'Talla ' . $pendingLine['size_name'] : 'Sin talla') ?></small></article>
            <article><span>P4 / Responsable</span>
                <strong><?= e($pendingLine['p4_name'] ?: 'P4 autorizado') ?></strong>
                <small><?= e((string) (sindesg_user()['display_name'] ?? 'Responsable autenticado')) ?></small></article>
        </div>
        <?php if (!empty($recipientOutOfScope)): ?>
            <p class="delivery-warning" role="alert">
                La adscripción actual del funcionario no corresponde a este P4.
                No se permite una entrega automática. Comuníquese con Almacén Central.
            </p>
        <?php endif; ?>
        <label class="delivery-quantity-field">
            <span>Unidades que recibe</span>
            <input type="number" name="linked_quantity" min="0.001"
                   max="<?= e($pendingLine['pending_quantity']) ?>" step="0.001"
                   value="<?= e(sindesg_units((float) $pendingLine['pending_quantity'])) ?>" required>
            <small>Pendientes autorizadas: <?= e(sindesg_units((float) $pendingLine['pending_quantity'])) ?></small>
        </label>
    </section>

    <section class="delivery-panel p4-direct-sign-panel">
        <div class="delivery-section-head">
            <div><span class="eyebrow">CONSTANCIA DIGITAL</span><h2>Firma de recibido</h2></div>
        </div>
        <p>El funcionario receptor firma aquí. La fecha y el responsable se registran automáticamente.</p>
        <div class="p4-direct-canvas-wrap">
            <canvas data-p4-direct-canvas width="650" height="180"
                    aria-label="Área para firma manuscrita de recibido"></canvas>
            <input type="hidden" name="recipient_signature_png" data-p4-direct-signature-value>
            <button type="button" class="secondary-button" data-p4-direct-clear>Limpiar firma</button>
        </div>
        <label class="p4-signature-ack">
            <input type="checkbox" name="recipient_ack" value="1" required>
            <span>Verifiqué la identidad y el funcionario firmó al recibir la prenda.</span>
        </label>
        <label class="p4-signature-ack">
            <input type="checkbox" name="issuer_ack" value="1" required>
            <span>Autorizo asociar mi firma institucional registrada a esta entrega.</span>
        </label>
        <?php if (!$issuerSignatureReady): ?>
            <div class="delivery-warning">
                Primero debe registrar su firma institucional. Este paso solo es necesario una vez.
                <a href="<?= e(sindesg_url('?module=profile&action=signature')) ?>">Registrar mi firma</a>
            </div>
        <?php endif; ?>
        <p class="p4-direct-explain">
            Al confirmar se descontará la cantidad del inventario del P4 y se archivará la constancia
            con ambas firmas y trazabilidad. La entrega confirmada no se podrá editar.
        </p>
    </section>

    <div class="delivery-form-actions p4-direct-actions">
        <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Cancelar</a>
        <button type="submit" class="primary-button" data-p4-direct-submit
                <?= !$issuerSignatureReady || !empty($recipientOutOfScope) ? 'disabled' : '' ?>>
            Confirmar entrega y generar constancia
        </button>
    </div>
</form>
