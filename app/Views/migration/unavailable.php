<?php
declare(strict_types=1);
?>
<section class="migration-empty standalone">
    <div class="migration-empty-mark">HIS</div>
    <h1>Migración histórica no disponible</h1>
    <p><?= e($migrationError ?? 'No fue posible cargar el centro de históricos.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
