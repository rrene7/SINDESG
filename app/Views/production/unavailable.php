<?php
declare(strict_types=1);
?>
<section class="production-empty standalone">
    <div class="production-empty-mark">!</div>
    <h1>Producción no disponible</h1>
    <p><?= e($productionError ?? 'El módulo no pudo cargarse.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
