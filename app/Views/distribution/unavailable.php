<?php
declare(strict_types=1);
?>
<section class="distribution-empty standalone">
    <div class="distribution-empty-mark">DP</div>
    <h1>Distribución no disponible</h1>
    <p><?= e($distributionError ?? 'No fue posible cargar el módulo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
