<?php
declare(strict_types=1);
?>
<section class="catalog-empty standalone">
    <div class="catalog-empty-mark">!</div>
    <h1>Catálogo no disponible</h1>
    <p><?= e($catalogError ?? 'No fue posible abrir el catálogo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">Volver al dashboard</a>
</section>
