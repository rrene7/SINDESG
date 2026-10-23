<?php
declare(strict_types=1);
// All records were restricted to the authenticated P4 before rendering.
foreach ($rows as $record):
    $module = (string)$record['module'];
    $canOpen = sindesg_role_action_allowed($module, 'show', 'GET', (array)sindesg_user())
        && sindesg_can(sindesg_permission_for($module, 'show', 'GET') ?? 'dashboard.view');
    $code = trim((string)($record['code'] ?? ''));
    $code = $code !== '' ? $code : ($module === 'requests' ? 'Solicitud' : 'Transferencia');
    $summary = \App\Services\P4CentralCardSummary::item($record);
    $status = \App\Services\P4CentralCardSummary::status((string)($record['status'] ?? ''));
    $datetime = (string)($record['event_at'] ?? '');
    $timestamp = $datetime !== '' ? strtotime($datetime) : false;
?>
<div class="p4-v2-central-entry">
    <div class="p4-v2-central-entry-head">
        <?php if ($canOpen): ?>
            <a href="<?= e(sindesg_url('?module='.$module.'&action=show&id='.(int)$record['id'])) ?>"><?= e($code) ?> ↗</a>
        <?php else: ?><strong><?= e($code) ?></strong><?php endif; ?>
        <span class="p4-v2-central-entry-status"><?= e($status) ?></span>
    </div>
    <div class="p4-v2-central-entry-line" title="<?= e('Detalle completo del documento '.$code) ?>"><?= e($summary) ?></div>
    <div class="p4-v2-central-entry-meta">
        <?php if ($timestamp !== false): ?>
            <time datetime="<?= e($datetime) ?>" title="<?= e(date('d/m/Y H:i', $timestamp)) ?>"><?= e(date('d/m/Y', $timestamp)) ?></time>
        <?php endif; ?>
        <?php if (count($warehouses) > 1): ?>
            <span><?= e($record['warehouse_name'] ?? 'Almacén P4') ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
