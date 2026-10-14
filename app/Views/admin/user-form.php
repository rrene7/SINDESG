<?php
declare(strict_types=1);

$isEdit = is_array($securityUser);
?>
<section class="security-hero compact">
    <div>
        <span class="eyebrow"><?= $isEdit ? 'EDITAR CUENTA' : 'NUEVO USUARIO' ?></span>
        <h1><?= $isEdit ? e($securityUser['display_name_snapshot']) : 'Crear usuario SINDESG' ?></h1>
        <p><?= $isEdit
            ? 'Puede ajustar roles, estado o restablecer la contraseña. La vinculación RRHH original se conserva.'
            : 'Seleccione un funcionario vigente de RRHH2029 y asigne al menos un rol.' ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=admin')) ?>">← Administración</a>
</section>

<?php if (!$isEdit): ?>
<form class="security-form" method="post" action="<?= e(sindesg_url('?module=admin&action=save-user')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <section class="security-panel">
        <div class="security-section-head"><div><span class="eyebrow">FUNCIONARIO</span><h2>Vinculación RRHH2029</h2></div></div>
        <div class="security-employee-picker" data-security-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Buscar funcionario *</span>
                <input type="search" data-security-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="employee_id" data-security-id>
            </label>
            <div class="security-picker-results" data-security-results hidden></div>
            <div class="security-picker-selection" data-security-selection hidden></div>
        </div>
    </section>

    <section class="security-panel">
        <div class="security-form-grid">
            <label><span>Usuario *</span><input type="text" name="username" minlength="4" maxlength="80" pattern="[a-z0-9._-]+" required></label>
            <label><span>Contraseña *</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
            <label><span>Confirmar contraseña *</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
        </div>

        <fieldset class="security-role-fieldset">
            <legend>Roles *</legend>
            <div class="security-role-grid">
                <?php foreach ($securityRoles as $role): ?>
                    <label><input type="checkbox" name="role_ids[]" value="<?= e($role['id']) ?>"><span><strong><?= e($role['name']) ?></strong><small><?= e($role['description']) ?></small></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <button class="primary-button" type="submit">Crear usuario</button>
    </section>
</form>
<?php else: ?>
<section class="security-panel">
    <dl class="security-dl">
        <div><dt>Usuario</dt><dd><?= e($securityUser['username']) ?></dd></div>
        <div><dt>Cédula</dt><dd><?= e($securityUser['document_snapshot'] ?: '—') ?></dd></div>
        <div><dt>Posición</dt><dd><?= e($securityUser['position_snapshot'] ?: '—') ?></dd></div>
        <div><dt>Rango</dt><dd><?= e($securityUser['rank_snapshot'] ?: '—') ?></dd></div>
        <div><dt>Dirección snapshot</dt><dd><?= e($securityUser['direction_snapshot'] ?: '—') ?></dd></div>
        <div><dt>Oficina snapshot</dt><dd><?= e($securityUser['office_snapshot'] ?: '—') ?></dd></div>
    </dl>
</section>

<section class="security-panel">
    <form method="post" action="<?= e(sindesg_url('?module=admin&action=update-user')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="user_id" value="<?= e($securityUser['id']) ?>">

        <div class="security-form-grid">
            <label>
                <span>Estado</span>
                <select name="status">
                    <option value="active" <?= $securityUser['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="disabled" <?= $securityUser['status'] === 'disabled' ? 'selected' : '' ?>>Deshabilitado</option>
                </select>
            </label>
        </div>

        <fieldset class="security-role-fieldset">
            <legend>Roles</legend>
            <div class="security-role-grid">
                <?php foreach ($securityRoles as $role): ?>
                    <label>
                        <input type="checkbox" name="role_ids[]" value="<?= e($role['id']) ?>" <?= in_array($role['code'], $selectedRoleCodes, true) ? 'checked' : '' ?>>
                        <span><strong><?= e($role['name']) ?></strong><small><?= e($role['description']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <button class="primary-button" type="submit">Guardar usuario</button>
    </form>
</section>

<section class="security-panel">
    <div class="security-section-head">
        <div><span class="eyebrow">ÁMBITO INSTITUCIONAL</span><h2>Unidades P4 autorizadas</h2>
            <p>La asignación debe ser expresa. Sin P4 asignado, el usuario P4 no tendrá acceso a registros de ninguna unidad cuando se active el control integral.</p>
        </div>
    </div>
    <form method="post" action="<?= e(sindesg_url('?module=admin&action=save-p4-scopes')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="user_id" value="<?= e($securityUser['id']) ?>">
        <div class="security-role-grid">
            <?php foreach ($p4WarehouseOptions as $warehouse): ?>
                <label><input type="checkbox" name="p4_warehouse_ids[]" value="<?= e($warehouse['id']) ?>"
                    <?= in_array((int) $warehouse['id'], $selectedP4Scopes, true) ? 'checked' : '' ?>>
                    <span><strong><?= e($warehouse['code']) ?></strong>
                    <small><?= e($warehouse['organization_unit_name_snapshot'] ?: $warehouse['name']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="primary-button">Guardar unidades P4</button>
    </form>
</section>

<section class="security-panel">
    <div class="security-section-head"><div><span class="eyebrow">CONTRASEÑA</span><h2>Restablecimiento administrativo</h2><p>La contraseña nunca se muestra ni se guarda en texto plano.</p></div></div>
    <form class="security-form-grid" method="post" action="<?= e(sindesg_url('?module=admin&action=reset-password')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="user_id" value="<?= e($securityUser['id']) ?>">
        <label><span>Nueva contraseña</span><input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>
        <label><span>Confirmar</span><input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required></label>
        <button class="primary-button" type="submit">Restablecer contraseña</button>
    </form>
</section>
<?php endif; ?>
