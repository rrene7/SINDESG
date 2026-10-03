<?php
declare(strict_types=1);

$statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviada',
    'in_review' => 'En revisión',
    'approved' => 'Aprobada',
    'partially_approved' => 'Aprobada parcial',
    'rejected' => 'Rechazada',
    'fulfilled' => 'Atendida',
    'closed' => 'Cerrada',
];
?>
<section class="request-hero">
    <div>
        <span class="eyebrow">FASE 7 · NECESIDAD LOGÍSTICA</span>
        <h1>Solicitudes P4 → Almacén Central</h1>
        <p>Registre necesidades, revise disponibilidad y deje aprobadas las cantidades que la Fase 8 podrá distribuir físicamente.</p>
    </div>
    <a class="primary-button" href="<?= e(sindesg_url('?module=requests&action=new')) ?>">+ Nueva solicitud</a>
</section>

<section class="request-stats">
    <article><span>Borradores</span><strong><?= e($requestStats['drafts']) ?></strong><small>En preparación</small></article>
    <article><span>Pendientes de revisión</span><strong><?= e($requestStats['pending_review']) ?></strong><small>Enviadas / revisión</small></article>
    <article><span>Aprobadas</span><strong><?= e($requestStats['approved']) ?></strong><small>Comprometen disponibilidad</small></article>
    <article><span>Parciales</span><strong><?= e($requestStats['partials']) ?></strong><small>Aprobación parcial</small></article>
    <article><span>Rechazadas</span><strong><?= e($requestStats['rejected']) ?></strong><small>Sin compromiso</small></article>
</section>

<section class="request-panel">
    <div class="request-section-head">
        <div><span class="eyebrow">TRAZABILIDAD</span><h2>Solicitudes recientes</h2></div>
    </div>

    <?php if ($serviceRequests === []): ?>
        <div class="request-empty">
            <div class="request-empty-mark">SO</div>
            <h3>Aún no hay solicitudes</h3>
            <p>Un P4 activo puede crear el primer borrador y remitirlo al Almacén Central.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Solicitud</th><th>P4</th><th>Solicitante</th><th>Fecha</th><th>Renglones</th><th>Solicitado</th><th>Aprobado</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($serviceRequests as $row): ?>
                    <tr>
                        <td><a class="request-link" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $row['id'])) ?>"><?= e($row['request_code']) ?></a></td>
                        <td><?= e(($row['p4_code'] ?: 'P4') . ' · ' . ($row['p4_name'] ?: 'Unidad')) ?></td>
                        <td><?= e($row['requested_by_label_snapshot']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['requested_at']))) ?></td>
                        <td><?= e((int) $row['item_count']) ?></td>
                        <td><?= e(number_format((float) $row['quantity_requested'], 0, '.', ',')) ?></td>
                        <td><?= e(number_format((float) $row['quantity_approved'], 0, '.', ',')) ?></td>
                        <td><span class="request-status <?= e($row['status']) ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
