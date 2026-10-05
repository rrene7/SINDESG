<?php
declare(strict_types=1);
?>
<section class="adjustment-empty standalone">
    <div class="adjustment-empty-mark">AJ</div>
    <h1>Ajustes no disponible</h1>
    <p><?= e($adjustmentError ?? 'No fue posible cargar el módulo.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
