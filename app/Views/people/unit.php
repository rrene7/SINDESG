<?php
declare(strict_types=1);
?>
<section class="unit-detail-hero">
    <div>
        <span class="eyebrow">ESTRUCTURA RRHH2029</span>
        <h1><?= e($unit['official_name']) ?></h1>
        <p><?= e($unit['unit_type'] ?: 'Unidad') ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=people')) ?>">← Estructura</a>
</section>

<section class="unit-detail-grid">
    <article class="people-panel">
        <span class="eyebrow">UNIDAD VIGENTE</span>
        <h2>Identificación</h2>
        <dl class="people-dl">
            <div><dt>Referencia para auditoría</dt><dd><details><summary>Ver identificación técnica</summary><?= e($unit['id']) ?> · <?= e($unit['system_code'] ?: '—') ?> · <?= e($unit['institutional_code'] ?: '—') ?></details></dd></div>
            
            
            <div><dt>Abreviatura</dt><dd><?= e($unit['official_abbreviation'] ?: '—') ?></dd></div>
            <div><dt>Tipo</dt><dd><?= e($unit['unit_type'] ?: '—') ?></dd></div>
            <div><dt>Nivel MOI</dt><dd><?= e($unit['moi_level'] ?: '—') ?></dd></div>
            <div><dt>Unidad padre</dt><dd><?= e($unit['parent_name'] ?: 'Raíz') ?></dd></div>
            <div><dt>Estado</dt><dd><?= e($unit['lifecycle_status'] ?: '—') ?></dd></div>
        </dl>
        <?php if (!empty($unit['hierarchy_path'])): ?>
            <div class="hierarchy-path"><strong>Ruta jerárquica</strong><span><?= e($unit['hierarchy_path']) ?></span></div>
        <?php endif; ?>
    </article>

    <article class="people-panel">
        <span class="eyebrow">DEPENDENCIAS</span>
        <h2>Subunidades vigentes</h2>
        <?php if ($childUnits === []): ?>
            <div class="people-empty compact"><p>No registra subunidades vigentes.</p></div>
        <?php else: ?>
            <div class="child-unit-list">
                <?php foreach ($childUnits as $child): ?>
                    <a href="<?= e(sindesg_url('?module=people&action=unit&id=' . (int) $child['id'])) ?>">
                        <span><strong><?= e($child['official_name']) ?></strong><small><?= e($child['unit_type'] ?: 'Unidad') ?></small></span>
                        <span><strong><?= e((int) $child['personnel_count']) ?></strong><small>funcionarios</small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="people-panel">
    <div class="people-section-head">
        <div>
            <span class="eyebrow">PERSONAL ADSCRITO</span>
            <h2>Asignación primaria vigente</h2>
        </div>
        <span class="people-count"><?= e(count($unitPeople)) ?> mostrados</span>
    </div>

    <?php if ($unitPeople === []): ?>
        <div class="people-empty"><p>No hay funcionarios con asignación primaria vigente directamente en esta unidad.</p></div>
    <?php else: ?>
        <div class="responsive-table people-table">
            <table>
                <thead>
                <tr><th>Posición</th><th>Rango</th><th>Funcionario</th><th>Cédula</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($unitPeople as $person): ?>
                    <tr>
                        <td><?= e($person['position_number'] ?: '—') ?></td>
                        <td><?= e($person['rank_name'] ?: '—') ?></td>
                        <td><strong><?= e(trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])) ?></strong></td>
                        <td><?= e($person['document_number'] ?: '—') ?></td>
                        <td><?= e($person['status_name'] ?: '—') ?></td>
                        <td><a class="people-link" href="<?= e(sindesg_url('?module=people&action=show&id=' . (int) $person['employee_id'])) ?>">Ver →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
