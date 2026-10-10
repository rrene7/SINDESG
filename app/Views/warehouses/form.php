<?php
declare(strict_types=1);
?>
<section class="warehouse-hero compact">
    <div>
        <span class="eyebrow">NUEVO P4</span>
        <h1>Habilitar almacén P4</h1>
        <p>Seleccione una unidad oficial de RRHH2029. SINDESG generará el código del P4 y conservará un snapshot histórico.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses')) ?>">← Volver</a>
</section>

<?php if (!$rrhhAvailable): ?>
    <section class="warehouse-empty">
        <div class="warehouse-empty-mark">RH</div>
        <h2>RRHH2029 no está disponible</h2>
        <p>No se puede habilitar un nuevo P4 sin validar primero su unidad y responsable contra la fuente maestra.</p>
    </section>
<?php else: ?>
<form class="warehouse-form" method="post" action="<?= e(sindesg_url('?module=warehouses&action=save')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <section class="warehouse-panel">
        <div class="warehouse-section-head">
            <div>
                <span class="eyebrow">UNIDAD OFICIAL</span>
                <h2>Dirección o Zona responsable del P4</h2>
                <p>Seleccione la Dirección o Zona Policial oficial vigente. Las dependencias, zonas genéricas y sectores no se habilitan como P4 desde este formulario.</p>
            </div>
            <div class="warehouse-auto-code"><strong>Código automático</strong><span>P4-######</span></div>
        </div>

        <div class="warehouse-picker" data-unit-picker data-search-url="<?= e($unitSearchUrl) ?>">
            <label>
                <span>Buscar unidad *</span>
                <input type="search" data-unit-search autocomplete="off" placeholder="Nombre o código de Dirección/Zona..." required>
                <input type="hidden" name="organization_unit_id" data-unit-id>
            </label>
            <div class="warehouse-picker-results" data-unit-results hidden></div>
            <div class="warehouse-picker-selection" data-unit-selection hidden></div>
        </div>
    </section>

    <section class="warehouse-panel">
        <div class="warehouse-section-head">
            <div>
                <span class="eyebrow">RESPONSABLE</span>
                <h2>Funcionario responsable del P4</h2>
                <p>El funcionario se consulta en RRHH2029 y queda guardado por su ID técnico.</p>
            </div>
        </div>

        <div class="warehouse-picker" data-p4-employee-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Buscar funcionario *</span>
                <input type="search" data-p4-employee-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="responsible_employee_id" data-p4-employee-id>
            </label>
            <div class="warehouse-picker-results" data-p4-employee-results hidden></div>
            <div class="warehouse-picker-selection" data-p4-employee-selection hidden></div>
        </div>

        <label class="warehouse-notes-field">
            <span>Observaciones</span>
            <textarea name="notes" rows="4" placeholder="Ubicación física, horario, referencia interna u otra observación autorizada."></textarea>
        </label>
    </section>

    <div class="warehouse-form-actions">
        <a href="<?= e(sindesg_url('?module=warehouses')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Habilitar P4</button>
    </div>
</form>
<?php endif; ?>
