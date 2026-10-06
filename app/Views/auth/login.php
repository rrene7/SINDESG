<?php
declare(strict_types=1);
?>
<section class="auth-card">
    <div class="auth-card-head">
        <span class="eyebrow">ACCESO INSTITUCIONAL</span>
        <h1>Iniciar sesión</h1>
        <p>Ingrese con su usuario SINDESG. Las acciones realizadas quedarán vinculadas a su cuenta en la auditoría.</p>
    </div>

    <form method="post" action="<?= e(sindesg_url('?module=auth&action=login')) ?>" class="auth-form">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <label>
            <span>Usuario</span>
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>

        <label>
            <span>Contraseña</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button class="primary-button auth-submit" type="submit">Entrar a SINDESG</button>
    </form>

    <div class="auth-note">
        <strong>Seguridad</strong>
        <span>No comparta su cuenta. SINDESG registra usuario, módulo, acción, fecha/hora y datos de trazabilidad de las operaciones.</span>
    </div>
</section>
