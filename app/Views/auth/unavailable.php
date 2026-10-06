<?php
declare(strict_types=1);
?>
<section class="auth-card">
    <div class="auth-card-head">
        <span class="eyebrow">SEGURIDAD</span>
        <h1>Configuración pendiente</h1>
        <p><?= e($authError ?? 'No fue posible iniciar el módulo de seguridad.') ?></p>
    </div>
</section>
