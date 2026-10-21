<?php
declare(strict_types=1);
?>
<section class="security-hero">
    <div>
        <span class="eyebrow">FASE 12 · ADMINISTRACIÓN</span>
        <h1>Usuarios y parámetros</h1>
        <p>Administre quién puede entrar a SINDESG y bajo qué rol. Los usuarios se vinculan con funcionarios vigentes de RRHH2029.</p>
    </div>
    <div class="security-hero-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=admin&action=roles')) ?>">Roles y permisos</a>
        <a class="primary-button" href="<?= e(sindesg_url('?module=admin&action=new')) ?>">+ Nuevo usuario</a>
    </div>
</section>

<section class="security-panel">
    <div class="security-section-head">
        <div>
            <span class="eyebrow">SUPERADMINISTRACIÓN · CATALOGO INSTITUCIONAL</span>
            <h2>Gobierno de categorías y artículos</h2>
            <p>Edite categorías, ordene el catálogo y retire de la lista activa solo categorías sin artículos vinculados. Los códigos institucionales y las existencias están protegidos.</p>
        </div>
        <div class="inventory-actions">
            <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=duplicate-products')) ?>">Revisar artículos repetidos</a>
            <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=categories')) ?>">Administrar categorías →</a>
        </div>
    </div>
</section>

<section class="security-panel">
    <div class="security-section-head">
        <div><span class="eyebrow">USUARIOS</span><h2>Cuentas SINDESG</h2></div>
        <span><?= e(count($securityUsers)) ?> cuentas</span>
    </div>

    <div class="security-table-wrap">
        <table class="security-table">
            <thead><tr><th>Funcionario</th><th>Usuario</th><th>Roles</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($securityUsers as $user): ?>
                <tr>
                    <td><strong><?= e($user['display_name_snapshot']) ?></strong><small><?= e($user['document_snapshot'] ?: '—') ?> · <?= e($user['direction_snapshot'] ?: 'Sin dirección snapshot') ?></small></td>
                    <td><code><?= e($user['username']) ?></code></td>
                    <td><?= e($user['role_names'] ?: 'Sin rol') ?></td>
                    <td><span class="security-status <?= e($user['status']) ?>"><?= e($user['status'] === 'active' ? 'Activo' : 'Deshabilitado') ?></span></td>
                    <td><?= e($user['last_login_at'] ? date('d/m/Y H:i', strtotime((string) $user['last_login_at'])) : 'Nunca') ?></td>
                    <td><a class="security-link" href="<?= e(sindesg_url('?module=admin&action=edit&id=' . (int) $user['id'])) ?>">Administrar →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="security-panel">
    <div class="security-section-head">
        <div>
            <span class="eyebrow">PARÁMETROS DE SEGURIDAD</span>
            <h2>Sesión y contraseñas</h2>
            <p>Los cambios se aplican a nuevas validaciones y quedan registrados en auditoría.</p>
        </div>
    </div>

    <form class="security-settings-form" method="post" action="<?= e(sindesg_url('?module=admin&action=save-settings')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <label>
            <span>Expirar sesión tras inactividad</span>
            <div><input type="number" name="session_idle_minutes" min="15" max="720" value="<?= e($securitySettings['security.session_idle_minutes']) ?>" required><b>minutos</b></div>
        </label>

        <label>
            <span>Longitud mínima de contraseña</span>
            <div><input type="number" name="password_min_length" min="10" max="64" value="<?= e($securitySettings['security.password_min_length']) ?>" required><b>caracteres</b></div>
        </label>

        <button class="primary-button" type="submit">Guardar parámetros</button>
    </form>
</section>
