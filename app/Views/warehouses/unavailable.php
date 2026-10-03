<?php
declare(strict_types=1);
?>
<section class="warehouse-empty standalone">
    <div class="warehouse-empty-mark">P4</div>
    <h1>Almacenes y P4 no disponible</h1>
    <p><?= e($warehouseError ?? 'No fue posible cargar el módulo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
