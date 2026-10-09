<?php
declare(strict_types=1);

$fullName = trim((string) $person['first_name'] . ' ' . (string) $person['last_name']);
?>
<section class="person-detail-hero">
    <div class="person-large-avatar"><?= e(strtoupper(substr((string) $person['first_name'], 0, 1) . substr((string) $person['last_name'], 0, 1))) ?></div>
    <div>
        <span class="eyebrow">FUNCIONARIO · RRHH2029</span>
        <h1><?= e($fullName) ?></h1>
        <p><?= e($person['rank_name'] ?: 'Sin rango registrado') ?> · <?= e($person['status_name'] ?: 'Sin estado registrado') ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=people')) ?>">← Funcionarios</a>
</section>

<section class="person-detail-grid">
    <article class="people-panel">
        <span class="eyebrow">IDENTIFICACIÓN</span>
        <h2>Datos vigentes</h2>
        <dl class="people-dl">
            <div><dt>Employee ID</dt><dd><?= e($person['employee_id']) ?></dd></div>
            <div><dt>Cédula</dt><dd><?= e($person['document_number'] ?: '—') ?></dd></div>
            <div><dt>Posición</dt><dd><?= e($person['position_number'] ?: '—') ?></dd></div>
            <div><dt>Rango</dt><dd><?= e($person['rank_name'] ?: '—') ?></dd></div>
            <div><dt>Estado</dt><dd><?= e($person['status_name'] ?: '—') ?></dd></div>
            <div><dt>Sexo</dt><dd><?= e($person['sex'] ?: '—') ?></dd></div>
            <div><dt>Fecha de ingreso</dt><dd><?= e($person['hire_date'] ?: '—') ?></dd></div>
            <div><dt>Inicio posición vigente</dt><dd><?= e($person['position_start_date'] ?: '—') ?></dd></div>
        </dl>
    </article>

    <article class="people-panel unit-focus">
        <span class="eyebrow">ADSCRIPCIÓN ACTUAL</span>
        <h2><?= e($person['organization_unit_name'] ?: 'Sin unidad vigente') ?></h2>
        <?php if (!empty($person['organization_unit_id'])): ?>
            <div class="unit-focus-code"><?= e($person['organization_unit_code'] ?: 'SIN-CÓDIGO') ?></div>
            <p><?= e($person['hierarchy_path'] ?: 'Ruta jerárquica no disponible.') ?></p>
            <dl class="people-dl single">
                <div><dt>Dirección</dt><dd><?= e($person['direction_name'] ?: '—') ?></dd></div>
                <div><dt>Oficina</dt><dd><?= e($person['office_name'] ?: '—') ?></dd></div>
                <div><dt>Dependencia actual</dt><dd><?= e($person['organization_unit_name'] ?: '—') ?></dd></div>
                <div><dt>Tipo</dt><dd><?= e($person['unit_type'] ?: '—') ?></dd></div>
                <div><dt>Nivel MOI</dt><dd><?= e($person['moi_level'] ?: '—') ?></dd></div>
                <div><dt>Asignado desde</dt><dd><?= e($person['unit_assignment_start_date'] ?: '—') ?></dd></div>
            </dl>
            <a class="primary-button" href="<?= e(sindesg_url('?module=people&action=unit&id=' . (int) $person['organization_unit_id'])) ?>">Abrir unidad</a>
        <?php else: ?>
            <p>RRHH2029 no registra una asignación primaria vigente para este funcionario.</p>
        <?php endif; ?>
    </article>
</section>

<section class="people-panel person-delivery-history">
    <div class="people-section-head">
        <div>
            <span class="eyebrow">HISTORIAL SINDESG</span>
            <h2>Dotaciones entregadas</h2>
        </div>
        <a class="secondary-button"
           href="<?= e(sindesg_url('?module=deliveries&action=history&employee_id=' . (int) $person['employee_id'])) ?>">
            Historial completo
        </a>
    </div>

    <?php if (($deliveryHistory ?? []) === []): ?>
        <div class="people-empty compact">
            <p>Este funcionario no tiene entregas confirmadas registradas en SINDESG.</p>
        </div>
    <?php else: ?>
        <div class="person-delivery-grid">
            <?php foreach ($deliveryHistory as $row): ?>
                <a href="<?= e(sindesg_url('?module=deliveries&action=show&id=' . (int) $row['delivery_id'])) ?>">
                    <span>
                        <strong><?= e($row['product_name']) ?></strong>
                        <small>
                            <?= e($row['variant_code']) ?>
                            <?= !empty($row['size_name']) ? ' · Talla ' . e($row['size_name']) : '' ?>
                        </small>
                    </span>
                    <span>
                        <strong><?= e(number_format((float) $row['quantity'], 3, '.', ',')) ?></strong>
                        <small><?= e(date('d/m/Y', strtotime((string) $row['delivered_at']))) ?> · <?= e($row['p4_code']) ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="people-source-note">
    <strong>Fuente maestra RRHH2029</strong>
    <span>Esta ficha se consulta en tiempo real. SINDESG no edita estos datos.</span>
</section>
