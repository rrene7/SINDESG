<?php
declare(strict_types=1);
?>
<section class="delivery-empty standalone">
    <div class="delivery-empty-mark">DI</div>
    <h1>Despacho individual no disponible</h1>
    <p><?= e($deliveryError ?? 'No fue posible cargar el módulo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
