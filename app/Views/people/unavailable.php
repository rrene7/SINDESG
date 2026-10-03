<?php
declare(strict_types=1);
?>
<section class="people-empty standalone">
    <div class="people-empty-mark">RH</div>
    <h1>Consulta RRHH no disponible</h1>
    <p><?= e($peopleError ?? 'No fue posible consultar RRHH2029.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
