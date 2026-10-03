<?php
declare(strict_types=1);
?>
<section class="request-empty standalone">
    <div class="request-empty-mark">SO</div>
    <h1>Solicitudes no disponible</h1>
    <p><?= e($requestError ?? 'No fue posible cargar el módulo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
