<?php
declare(strict_types=1);

$permissionsByModule = [];
foreach ($securityPermissions as $permission) {
    $permissionsByModule[$permission['module_code']][] = $permission;
}
?>
<section class="security-hero compact">
    <div>
        <span class="eyebrow">ROLES Y PERMISOS</span>
        <h1>Matriz de acceso</h1>
        <p>El rol Administrador conserva todos los permisos. Los demás roles pueden ajustarse según la operación institucional.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=admin')) ?>">← Administración</a>
</section>

<?php foreach ($securityRoles as $role): ?>
<section class="security-panel role-panel">
    <div class="security-section-head">
        <div><span class="eyebrow"><?= e($role['code']) ?></span><h2><?= e($role['name']) ?></h2><p><?= e($role['description']) ?></p></div>
        <?php if ($role['code'] === 'ADMIN'): ?><span class="security-lock">Permisos completos</span><?php endif; ?>
    </div>

    <form method="post" action="<?= e(sindesg_url('?module=admin&action=save-role')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="role_id" value="<?= e($role['id']) ?>">

        <div class="permission-groups">
            <?php foreach ($permissionsByModule as $moduleCode => $permissions): ?>
                <fieldset>
                    <legend><?= e(strtoupper($moduleCode)) ?></legend>
                    <?php foreach ($permissions as $permission): ?>
                        <label>
                            <input type="checkbox"
                                   name="permission_codes[]"
                                   value="<?= e($permission['code']) ?>"
                                   <?= in_array($permission['code'], $role['permission_codes'], true) ? 'checked' : '' ?>
                                   <?= $role['code'] === 'ADMIN' ? 'disabled' : '' ?>>
                            <span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['description']) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <?php if ($role['code'] !== 'ADMIN'): ?>
            <button class="primary-button" type="submit">Guardar permisos de <?= e($role['name']) ?></button>
        <?php endif; ?>
    </form>
</section>
<?php endforeach; ?>
