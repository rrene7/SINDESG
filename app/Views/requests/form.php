<?php
declare(strict_types=1);
?>
<section class="request-hero compact">
    <div>
        <span class="eyebrow">NUEVA SOLICITUD</span>
        <h1>Crear borrador</h1>
        <p>Primero identifique el P4 y el funcionario solicitante. Luego agregará los artículos requeridos.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">← Volver</a>
</section>

<?php if ($activeP4s === []): ?>
    <section class="request-empty">
        <div class="request-empty-mark">P4</div>
        <h2>No hay P4 activos</h2>
        <p>Debe existir al menos un P4 activo antes de registrar solicitudes.</p>
        <a class="primary-button" href="<?= e(sindesg_url('?module=warehouses&action=new')) ?>">Habilitar P4</a>
    </section>
<?php else: ?>
<form class="request-form" method="post" action="<?= e(sindesg_url('?module=requests&action=save')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <section class="request-panel">
        <div class="request-section-head">
            <div><span class="eyebrow">ORIGEN</span><h2>P4 solicitante</h2></div>
            <div class="request-auto-code"><strong>Código automático</strong><span>SOL-######</span></div>
        </div>

        <div class="request-form-grid">
            <label>
                <span>P4 *</span>
                <select name="p4_warehouse_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($activeP4s as $p4): ?>
                        <option value="<?= e($p4['id']) ?>"><?= e($p4['code'] . ' · ' . ($p4['organization_unit_name_snapshot'] ?: $p4['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Fecha y hora *</span>
                <input type="datetime-local" name="requested_at" value="<?= e($defaultRequestedAt) ?>" required>
            </label>
        </div>
    </section>

    <section class="request-panel">
        <div class="request-section-head">
            <div><span class="eyebrow">SOLICITANTE</span><h2>Funcionario que remite la solicitud</h2></div>
        </div>

        <?php if (in_array('P4', (array) (sindesg_user()['role_codes'] ?? []), true)
            && !in_array('ADMIN', (array) (sindesg_user()['role_codes'] ?? []), true)
            && !in_array('CENTRAL', (array) (sindesg_user()['role_codes'] ?? []), true)): ?>
            <p>Funcionario solicitante: <strong><?= e(sindesg_user()['display_name'] ?? 'Usuario P4') ?></strong>.
            Se utiliza la identidad de la sesión y se valida en RRHH2029.</p>
        <?php else: ?>
        <div class="request-employee-picker" data-request-employee-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Buscar funcionario *</span>
                <input type="search" data-request-employee-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="requested_by_employee_id" data-request-employee-id>
            </label>
            <div class="request-picker-results" data-request-employee-results hidden></div>
            <div class="request-picker-selection" data-request-employee-selection hidden></div>
        </div>

        <?php endif; ?>

        <label class="request-notes-field">
            <span>Observaciones</span>
            <textarea name="notes" rows="4" placeholder="Referencia, periodo, necesidad u observación del P4."></textarea>
        </label>
    </section>

    <div class="request-form-actions">
        <a href="<?= e(sindesg_url('?module=requests')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Crear borrador</button>
    </div>
</form>
<?php endif; ?>
