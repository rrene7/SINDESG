<?php
declare(strict_types=1);

$typeLabels = [
    'return' => 'Devolución',
    'exchange' => 'Cambio de talla / código',
    'replacement' => 'Reposición',
    'deterioration' => 'Deterioro',
    'writeoff' => 'Baja autorizada',
    'loss' => 'Pérdida',
    'shortage' => 'Faltante',
    'surplus' => 'Sobrante',
    'authorized_adjustment' => 'Ajuste autorizado',
    'cycle_count' => 'Conteo cíclico',
];
$statusLabels = ['draft' => 'Borrador', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado'];
$type = (string) $adjustment['adjustment_type'];
$isConfirmed = $adjustment['status'] === 'confirmed';

$expectedText = match ($type) {
    'return' => $adjustment['return_condition'] === 'reusable'
        ? 'Entrada al P4 de la variante devuelta.'
        : 'Registro de devolución no reutilizable sin reingreso a stock.',
    'exchange' => $adjustment['return_condition'] === 'reusable'
        ? 'Entrada de la variante devuelta y salida de la variante nueva.'
        : 'Salida de la variante nueva; la devuelta no reingresa a stock.',
    'replacement' => 'Salida de la variante de reposición.',
    'deterioration', 'writeoff', 'loss', 'shortage' => 'Salida del almacén.',
    'surplus' => 'Entrada al almacén.',
    'authorized_adjustment' => (($adjustment['manual_direction'] ?? '') === 'IN' ? 'Entrada' : 'Salida') . ' autorizada.',
    'cycle_count' => 'Ajuste por diferencia entre el sistema y el conteo físico.',
    default => 'Movimiento de ajuste.',
};
?>
<section class="adjustment-detail-hero">
    <div>
        <span class="eyebrow"><?= e($adjustment['adjustment_code']) ?> · FASE 10</span>
        <h1><?= e($typeLabels[$type] ?? $type) ?></h1>
        <p><?= e($adjustment['warehouse_code'] . ' · ' . ($adjustment['warehouse_name'] ?: $adjustment['warehouse_internal_name'])) ?></p>
    </div>
    <div class="adjustment-detail-status">
        <span class="adjustment-status <?= e($adjustment['status']) ?>"><?= e($statusLabels[$adjustment['status']] ?? $adjustment['status']) ?></span>
        <strong><?= e(sindesg_units((float) $adjustment['quantity'])) ?></strong>
        <small><?= $type === 'cycle_count' && !$isConfirmed ? 'diferencia al confirmar' : 'unidades de impacto' ?></small>
    </div>
</section>

<section class="adjustment-detail-grid">
    <article class="adjustment-panel">
        <span class="eyebrow">TRAZABILIDAD</span>
        <h2>Datos de la operación</h2>
        <dl class="adjustment-dl">
            <div><dt>Código</dt><dd><?= e($adjustment['adjustment_code']) ?></dd></div>
            <div><dt>Tipo</dt><dd><?= e($typeLabels[$type] ?? $type) ?></dd></div>
            <div><dt>Fecha</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) $adjustment['occurred_at']))) ?></dd></div>
            <div><dt>Almacén</dt><dd><?= e($adjustment['warehouse_code']) ?></dd></div>
            <div><dt>Responsable</dt><dd><?= e($adjustment['responsible_label_snapshot']) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e($adjustment['document_number'] ?: '—') ?></dd></div>
            <div><dt>Motivo</dt><dd><?= e($adjustment['reason']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= e($statusLabels[$adjustment['status']] ?? $adjustment['status']) ?></dd></div>
        </dl>

        <?php if (!empty($adjustment['affected_employee_id'])): ?>
            <div class="adjustment-audit-note">
                <strong>Funcionario afectado</strong>
                <p><?= e($adjustment['affected_employee_label_snapshot']) ?> · <?= e($adjustment['affected_employee_document_snapshot'] ?: 'Sin cédula histórica') ?></p>
                <?php if (!empty($adjustment['source_delivery_code'])): ?>
                    <p>Entrega original: <?= e($adjustment['source_delivery_code']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($adjustment['notes'])): ?>
            <div class="adjustment-audit-note"><strong>Observaciones</strong><p><?= e($adjustment['notes']) ?></p></div>
        <?php endif; ?>
    </article>

    <aside class="adjustment-panel adjustment-control">
        <span class="eyebrow">CONTROL</span>
        <h2><?= e($statusLabels[$adjustment['status']] ?? $adjustment['status']) ?></h2>
        <p><?= e($expectedText) ?></p>

        <?php if ($adjustment['status'] === 'draft'): ?>
            <p class="adjustment-current-stock">
                Stock actual principal:
                <strong><?= e(sindesg_units((float) $currentPrimaryStock)) ?></strong>
                <?php if ($currentReplacementStock !== null): ?>
                    · Reemplazo:
                    <strong><?= e(sindesg_units((float) $currentReplacementStock)) ?></strong>
                <?php endif; ?>
            </p>
            <form method="post" action="<?= e(sindesg_url('?module=adjustments&action=confirm')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="adjustment_id" value="<?= e($adjustment['id']) ?>">
                <button class="primary-button adjustment-full-button" type="submit">Confirmar operación</button>
            </form>
            <form method="post" action="<?= e(sindesg_url('?module=adjustments&action=cancel')) ?>" class="adjustment-cancel-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="adjustment_id" value="<?= e($adjustment['id']) ?>">
                <button class="adjustment-danger-button" type="submit">Cancelar borrador</button>
            </form>
        <?php elseif ($adjustment['status'] === 'confirmed'): ?>
            <p>La operación ya fue aplicada al kardex y no debe editarse. Cualquier corrección posterior se registra mediante otra operación compensatoria.</p>
            <button class="secondary-button adjustment-full-button" type="button" onclick="window.print()">Imprimir registro</button>
        <?php else: ?>
            <p>El borrador fue cancelado sin afectar inventario.</p>
        <?php endif; ?>
    </aside>
</section>

<section class="adjustment-panel">
    <div class="adjustment-section-head">
        <div><span class="eyebrow">IMPACTO</span><h2>Artículo y stock</h2></div>
    </div>

    <div class="adjustment-impact-grid">
        <article>
            <span>Variante principal</span>
            <strong><?= e($adjustment['product_name']) ?></strong>
            <small><?= e(($adjustment['product_name'] ?? 'Artículo') . ($adjustment['size_name'] ? ' · Talla ' . $adjustment['size_name'] : '')) ?></small>
            <?php if ($isConfirmed): ?>
                <div class="adjustment-stock-pair">
                    <b><?= e(sindesg_units((float) $adjustment['primary_stock_before'])) ?></b>
                    <span>→</span>
                    <b><?= e(sindesg_units((float) $adjustment['primary_stock_after'])) ?></b>
                </div>
            <?php endif; ?>
        </article>

        <?php if (!empty($adjustment['replacement_variant_id'])): ?>
            <article>
                <span>Variante de reemplazo</span>
                <strong><?= e($adjustment['replacement_product_name']) ?></strong>
                <small><?= e($adjustment['replacement_variant_code'] . ($adjustment['replacement_size_name'] ? ' · Talla ' . $adjustment['replacement_size_name'] : '')) ?></small>
                <?php if ($isConfirmed): ?>
                    <div class="adjustment-stock-pair">
                        <b><?= e(sindesg_units((float) $adjustment['replacement_stock_before'])) ?></b>
                        <span>→</span>
                        <b><?= e(sindesg_units((float) $adjustment['replacement_stock_after'])) ?></b>
                    </div>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if ($type === 'cycle_count'): ?>
            <article>
                <span>Conteo físico</span>
                <strong><?= e(sindesg_units((float) $adjustment['counted_quantity'])) ?></strong>
                <small>
                    <?php if ($isConfirmed): ?>
                        Sistema al confirmar: <?= e(sindesg_units((float) $adjustment['system_quantity_snapshot'])) ?>
                    <?php else: ?>
                        El sistema calculará la diferencia al confirmar.
                    <?php endif; ?>
                </small>
            </article>
        <?php endif; ?>

        <?php if (in_array($type, ['return', 'exchange'], true)): ?>
            <article>
                <span>Condición devuelta</span>
                <strong><?= $adjustment['return_condition'] === 'reusable' ? 'Reutilizable' : 'No reutilizable' ?></strong>
                <small><?= $adjustment['return_condition'] === 'reusable' ? 'Regresa al stock del P4.' : 'No incrementa el stock.' ?></small>
            </article>
        <?php endif; ?>
    </div>
</section>

<div class="adjustment-bottom-actions no-print">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=adjustments')) ?>">← Ajustes</a>
    <?php if (!empty($adjustment['source_delivery_id'])): ?>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries&action=show&id=' . (int) $adjustment['source_delivery_id'])) ?>">Ver entrega original</a>
    <?php endif; ?>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $adjustment['warehouse_id'])) ?>">Ver almacén</a>
</div>
