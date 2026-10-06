<?php
declare(strict_types=1);
?>
<section class="auth-card auth-card-wide">
    <div class="auth-card-head">
        <span class="eyebrow">PRIMER ACCESO · FASE 12</span>
        <h1>Crear administrador inicial</h1>
        <p>No existen usuarios todavía. Seleccione un funcionario vigente de RRHH2029 y defina la primera cuenta administradora. SINDESG no crea contraseñas predeterminadas.</p>
    </div>

    <form method="post" action="<?= e(sindesg_url('?module=auth&action=setup')) ?>" class="auth-form" data-auth-setup>
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <div class="auth-employee-picker" data-auth-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Funcionario administrador *</span>
                <input type="search" data-auth-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="employee_id" data-auth-id>
            </label>
            <div class="auth-picker-results" data-auth-results hidden></div>
            <div class="auth-picker-selection" data-auth-selection hidden></div>
        </div>

        <label>
            <span>Usuario *</span>
            <input type="text" name="username" minlength="4" maxlength="80" pattern="[a-z0-9._-]+" autocomplete="username" required placeholder="ej.: rrivera">
            <small>Minúsculas, números, punto, guion o guion bajo.</small>
        </label>

        <div class="auth-two">
            <label>
                <span>Contraseña *</span>
                <input type="password" name="password" minlength="12" autocomplete="new-password" required>
            </label>
            <label>
                <span>Confirmar contraseña *</span>
                <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
            </label>
        </div>

        <button class="primary-button auth-submit" type="submit">Crear administrador y continuar</button>
    </form>

    <div class="auth-note">
        <strong>RRHH2029 permanece en solo lectura</strong>
        <span>La cuenta guarda el ID del funcionario y un snapshot de sus datos actuales para trazabilidad, pero no modifica RRHH.</span>
    </div>
</section>
