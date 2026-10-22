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
        <p>Los P4 registran y envían sus necesidades. Almacén Central recibe, revisa y decide las solicitudes antes de su distribución.</p>
    </div>
    <?php if (sindesg_role_action_allowed('requests', 'new', 'GET', (array) sindesg_user())): ?>
    <div class="request-bottom-actions">
        <?php if (sindesg_role_action_allowed('requests', 'import-boots', 'GET', (array) sindesg_user())): ?>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=requests&action=import-boots')) ?>">Revisar Excel de dotación P4</a>
        <?php endif; ?>
        <a class="primary-button" href="<?= e(sindesg_url('?module=requests&action=new')) ?>">+ Nueva solicitud</a>
    </div>
    <?php else: ?>
        <div class="request-role-note" role="status">Gestión del Almacén Central: revise las solicitudes enviadas por los P4. La creación corresponde a cada unidad P4.</div>
    <?php endif; ?>
</section>

<section class="request-stats">
    <article><span>Borradores</span><strong><?= e($requestStats['drafts']) ?></strong><small>En preparación</small></article>
    <article><span>Pendientes de revisión</span><strong><?= e($requestStats['pending_review']) ?></strong><small>Enviadas / revisión</small></article>
    <article><span>Aprobadas</span><strong><?= e($requestStats['approved']) ?></strong><small>Comprometen disponibilidad</small></article>
    <article><span>Parciales</span><strong><?= e($requestStats['partials']) ?></strong><small>Aprobación parcial</small></article>
    <article><span>Rechazadas</span><strong><?= e($requestStats['rejected']) ?></strong><small>Sin compromiso</small></article>
</section>

<?php
$draftReady = null;
foreach ($serviceRequests as $candidate) {
    if ((string) ($candidate['status'] ?? '') === 'draft'
        && (int) ($candidate['item_count'] ?? 0) > 0) {
        $draftReady = $candidate;
        break;
    }
}
?>
<?php if ($draftReady !== null && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
<section class="request-panel request-draft-next-step" role="status">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">SIGUIENTE PASO · BORRADOR CON DOTACIONES CARGADAS</span>
            <h2>Solicitud de dotación — lista para revisar y enviar</h2>
            <p>
                <?= (int) $draftReady['item_count'] ?> asignaciones ya están en SINDESG.
                No vuelva a importar el Excel. Revise los destinatarios y envíe
                la solicitud al Almacén Central cuando corresponda.
                El envío no crea existencias ni entrega bienes.
            </p>
        </div>
        <a class="primary-button"
           href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $draftReady['id'] . '#request-send-panel')) ?>">
            Revisar borrador y enviar al Central →
        </a>
    </div>
</section>
<?php endif; ?>

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
                <tr><th>Solicitud</th><th>P4</th><th>Solicitante</th><th>Fecha</th><th>Renglones</th><th>Solicitado</th><th>Aprobado</th><th>Estado</th><th>Seguimiento</th></tr>
                </thead>
                <tbody>
                <?php foreach ($serviceRequests as $row): ?>
                    <tr>
                        <td><a class="request-link" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $row['id'])) ?>">Ver solicitud</a></td>
                        <td><?= e($row['p4_name'] ?: 'Unidad P4') ?></td>
                        <td><?= e($row['requested_by_label_snapshot']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['requested_at']))) ?></td>
                        <td><?= e((int) $row['item_count']) ?></td>
                        <td><?= e(sindesg_units((float) $row['quantity_requested'])) ?></td>
                        <td><?= e(sindesg_units((float) $row['quantity_approved'])) ?></td>
                        <td><span class="request-status <?= e($row['status']) ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                        <td>
                            <a class="request-link" href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $row['id'] . '#seguimiento-p4')) ?>">Ver estado →</a>
                            <?php if (in_array($row['status'], ['submitted', 'in_review'], true) && sindesg_role_action_allowed('requests', 'central-review', 'GET', (array) sindesg_user())): ?>
                                <small class="request-cell-sub"><a href="<?= e(sindesg_url('?module=requests&action=central-review&id=' . (int) $row['id'])) ?>">Revisión Central</a></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
