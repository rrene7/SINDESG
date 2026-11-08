<?php
declare(strict_types=1);
$requestId = max(0, (int) ($distributionRequestId ?? 0));
?>
<section class="distribution-empty standalone" role="status">
    <div class="distribution-empty-mark">DP</div>
    <h1>No se puede abrir esta distribución</h1>
    <p><?= e($distributionNotice ?? 'Esta distribución no está disponible.') ?></p>
    <?php if ($requestId > 0
        && sindesg_can('requests.view')
        && sindesg_role_action_allowed('requests', 'show', 'GET', (array) sindesg_user())): ?>
        <a class="primary-button" href="<?= e(sindesg_url('?module=requests&action=show&id=' . $requestId)) ?>">
            Ver solicitud y seguimiento
        </a>
    <?php endif; ?>
    <?php if (sindesg_can('distribution.view')
        && sindesg_role_action_allowed('distribution', 'index', 'GET', (array) sindesg_user())): ?>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=distribution')) ?>">
            Volver a distribuciones
        </a>
    <?php endif; ?>
</section>
