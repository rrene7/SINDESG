<?php
declare(strict_types=1);
?>
<section class="reports-empty standalone">
    <div class="reports-empty-mark">RE</div>
    <h1>Reportes no disponibles</h1>
    <p><?= e($reportError ?? 'No fue posible cargar el módulo de reportes.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
