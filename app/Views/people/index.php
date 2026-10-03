<?php
declare(strict_types=1);
?>
<section class="people-hero">
    <div>
        <span class="eyebrow">FASE 5 · RRHH2029</span>
        <h1>Funcionarios y estructura vigente</h1>
        <p>SINDESG consulta directamente la fuente maestra de Recursos Humanos. No copia ni modifica funcionarios o unidades.</p>
    </div>
    <div class="people-readonly-badge">
        <span class="status-dot ok"></span>
        <div><strong>Solo lectura</strong><small>Fuente: RRHH2029</small></div>
    </div>
</section>

<section class="people-stats">
    <article><span>Funcionarios con asignación vigente</span><strong><?= e(number_format((int) $rrhhStats['current_people'])) ?></strong></article>
    <article><span>Unidades vigentes</span><strong><?= e(number_format((int) $rrhhStats['active_units'])) ?></strong></article>
    <article><span>Rangos registrados</span><strong><?= e(number_format((int) $rrhhStats['ranks_count'])) ?></strong></article>
</section>

<section class="people-panel">
    <div class="people-section-head">
        <div>
            <span class="eyebrow">BÚSQUEDA DIRECTA</span>
            <h2>Buscar funcionario</h2>
        </div>
    </div>

    <form class="people-search-form" method="get" action="<?= e(sindesg_url()) ?>">
        <input type="hidden" name="module" value="people">
        <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Nombre, apellido, cédula o posición..." autocomplete="off">
        <button class="primary-button" type="submit">Buscar en RRHH</button>
    </form>

    <?php if ($searchQuery !== ''): ?>
        <div class="people-results-heading">
            <strong><?= e(count($peopleResults)) ?> resultado(s)</strong>
            <span>Consulta vigente para “<?= e($searchQuery) ?>”</span>
        </div>

        <?php if ($peopleResults === []): ?>
            <div class="people-empty"><p>No se encontraron funcionarios con esos datos.</p></div>
        <?php else: ?>
            <div class="people-results-grid">
                <?php foreach ($peopleResults as $person): ?>
                    <a class="person-card" href="<?= e(sindesg_url('?module=people&action=show&id=' . (int) $person['employee_id'])) ?>">
                        <div class="person-avatar"><?= e(strtoupper(substr((string) $person['first_name'], 0, 1) . substr((string) $person['last_name'], 0, 1))) ?></div>
                        <div class="person-card-copy">
                            <span class="person-rank"><?= e($person['rank_name'] ?: 'Sin rango') ?></span>
                            <strong><?= e(trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])) ?></strong>
                            <small>Cédula <?= e($person['document_number'] ?: '—') ?> · Pos. <?= e($person['position_number'] ?: '—') ?></small>
                            <div class="person-org-context">
                                <span><b>Dirección:</b> <?= e($person['direction_name'] ?: '—') ?></span>
                                <span><b>Oficina:</b> <?= e($person['office_name'] ?: '—') ?></span>
                                <span><b>Dependencia actual:</b> <?= e($person['organization_unit_name'] ?: 'Sin asignación territorial vigente') ?></span>
                            </div>
                        </div>
                        <span class="person-arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="people-panel">
    <div class="people-section-head">
        <div>
            <span class="eyebrow">ESTRUCTURA OFICIAL</span>
            <h2>Raíces territoriales y organizacionales</h2>
            <p>Navegue desde las unidades superiores hacia sus dependencias vigentes.</p>
        </div>
    </div>

    <div class="unit-grid">
        <?php foreach ($rootUnits as $unit): ?>
            <a class="unit-card" href="<?= e(sindesg_url('?module=people&action=unit&id=' . (int) $unit['id'])) ?>">
                <span class="unit-code"><?= e($unit['system_code'] ?: 'SIN-CÓDIGO') ?></span>
                <strong><?= e($unit['official_name']) ?></strong>
                <small><?= e($unit['unit_type'] ?: 'Unidad') ?></small>
                <div class="unit-metrics">
                    <span><?= e((int) $unit['child_count']) ?> subunidades</span>
                    <span><?= e((int) $unit['personnel_count']) ?> funcionarios</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
