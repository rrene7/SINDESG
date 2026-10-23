<?php
declare(strict_types=1);
// Rendering fragment used only with a role-authorized P4 dashboard.
// Records are scoped by P4OperationalSummary before entering this view.
foreach ($rows as $record):
    $canOpen = sindesg_role_action_allowed(
        (string)$record['module'], 'show', 'GET', (array)sindesg_user()
    ) && sindesg_can(sindesg_permission_for((string)$record['module'], 'show', 'GET') ?? 'dashboard.view');
    $document = trim((string)($record['code']??''));
    $label = trim((string)($record['title']??''));
    $status = $statusLabels[(string)($record['status']??'')] ?? (string)($record['status']??'');
?>
<div class="p4-v2-central-entry">
    <div class="p4-v2-central-entry-head">
        <?php if ($canOpen): ?>
            <a href="<?= e(sindesg_url('?module='.$record['module'].'&action=show&id='.(int)$record['id'])) ?>">
                <?= e($document !== '' ? $document : ($record['module'] === 'requests' ? 'Solicitud' : 'Transferencia')) ?> ↗
            </a>
        <?php else: ?>
            <strong><?= e($document !== '' ? $document : ($record['module'] === 'requests' ? 'Solicitud' : 'Transferencia')) ?></strong>
        <?php endif; ?>
        <span><?= e($status) ?></span>
    </div>
    <div class="p4-v2-central-entry-title" title="<?= e($label) ?>"><?= e($label) ?></div>
    <small>
        <?= e($record['warehouse_name'] ?? 'Almacén P4') ?> ·
        <?= !empty($record['event_at']) ? e(date('d/m/Y H:i',strtotime((string)$record['event_at']))) : '—' ?>
    </small>
</div>
<?php endforeach; ?>
