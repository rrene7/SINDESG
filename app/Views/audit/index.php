<?php
declare(strict_types=1);
?>
<section class="security-hero">
    <div>
        <span class="eyebrow">FASE 12 · AUDITORÍA</span>
        <h1>Trazabilidad de acciones</h1>
        <p>Revise quién hizo qué, en qué módulo, cuándo y sobre qué registro. Contraseñas y tokens nunca se almacenan en el detalle.</p>
    </div>
</section>

<section class="security-panel">
    <form class="audit-filters" method="get" action="<?= e(sindesg_url()) ?>">
        <input type="hidden" name="module" value="audit">

        <label><span>Desde</span><input type="date" name="date_from" value="<?= e($auditFilters['date_from']) ?>"></label>
        <label><span>Hasta</span><input type="date" name="date_to" value="<?= e($auditFilters['date_to']) ?>"></label>

        <label>
            <span>Usuario</span>
            <select name="user_id">
                <option value="0">Todos</option>
                <?php foreach ($auditUsers as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= (int) $auditFilters['user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['display_name_snapshot'] . ' · ' . $user['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Módulo</span>
            <select name="module_filter" onchange="this.form.module_filter_value.value=this.value">
                <option value="">Todos</option>
                <?php foreach ($auditModules as $module): ?>
                    <option value="<?= e($module) ?>" <?= $auditFilters['module'] === $module ? 'selected' : '' ?>><?= e($module) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="module_filter_value" value="<?= e($auditFilters['module']) ?>">
        </label>

        <label><span>Acción</span><input type="text" name="action" value="<?= e($auditFilters['action']) ?>" placeholder="Ej.: login, POST:confirm..."></label>
        <label class="audit-search"><span>Búsqueda</span><input type="search" name="q" value="<?= e($auditFilters['q']) ?>" placeholder="Usuario, ID o detalle..."></label>
        <button class="primary-button" type="submit">Filtrar</button>
    </form>
</section>

<section class="security-panel">
    <div class="security-section-head">
        <div><span class="eyebrow">EVENTOS</span><h2><?= e(count($auditEvents)) ?> registros</h2></div>
    </div>
    <div class="security-table-wrap">
        <table class="security-table">
            <thead><tr><th>Fecha/hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Registro</th><th>IP</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($auditEvents as $event): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i:s', strtotime((string) $event['created_at']))) ?></td>
                    <td><strong><?= e($event['user_label_snapshot'] ?: 'Sistema') ?></strong><small><?= e($event['username'] ?: '—') ?></small></td>
                    <td><code><?= e($event['module_code']) ?></code></td>
                    <td><?= e($event['action_code']) ?></td>
                    <td><?= e(($event['record_type'] ?: '—') . ($event['record_id'] ? ' #' . $event['record_id'] : '')) ?></td>
                    <td><?= e($event['ip_address'] ?: '—') ?></td>
                    <td><a class="security-link" href="<?= e(sindesg_url('?module=audit&action=show&id=' . (int) $event['id'])) ?>">Ver →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
