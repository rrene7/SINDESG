<?php
declare(strict_types=1);

$steps = [
    ['number' => '01', 'title' => 'Preparar solicitud', 'description' => 'Registrar la necesidad institucional y seleccionar las tallas y cantidades.', 'module' => 'requests', 'action' => 'new', 'permission' => 'requests.manage'],
    ['number' => '02', 'title' => 'Enviar al Almacén Central', 'description' => 'Revisar la solicitud, adjuntar la información nominal y remitirla para evaluación.', 'module' => 'requests', 'action' => 'index', 'permission' => 'requests.view'],
    ['number' => '03', 'title' => 'Seguimiento y recepción', 'description' => 'Consultar transferencias asignadas y confirmar solamente lo efectivamente recibido.', 'module' => 'distribution', 'action' => 'index', 'permission' => 'distribution.view'],
    ['number' => '04', 'title' => 'Entrega al funcionario', 'description' => 'Registrar la entrega física de las unidades recibidas y conservar su historial.', 'module' => 'deliveries', 'action' => 'index', 'permission' => 'deliveries.view'],
];
?>
<section class="p4-home-hero">
    <span class="eyebrow">SINDESG · OPERACIÓN LOGÍSTICA P4</span>
    <h1>Mi flujo de trabajo P4</h1>
    <p>Desde la solicitud al Almacén Central hasta la entrega documentada al funcionario.</p>
    <div class="p4-home-status">Piloto visual seguro · Operaciones bloqueadas hasta validar aislamiento de almacenes</div>
</section>

<section class="p4-home-panel" aria-labelledby="p4-unit-heading">
    <span class="eyebrow">ÁMBITO DEL USUARIO</span>
    <h2 id="p4-unit-heading">Mis almacenes P4 autorizados</h2>
    <?php if (empty($p4ScopeReady)): ?>
        <p>No fue posible verificar las asignaciones de almacén. Operaciones bloqueadas.</p>
    <?php elseif ($p4AssignedWarehouses === []): ?>
        <p>Sin almacén autorizado. Un administrador debe asignar expresamente la Zona Policial 16 de Pacora, después de verificar su registro oficial en SINDESG.</p>
    <?php else: ?>
        <div class="p4-home-grid">
            <?php foreach ($p4AssignedWarehouses as $warehouse): ?>
                <article class="p4-step-card">
                    <span class="p4-step-number"><?= e($warehouse['code']) ?></span>
                    <h3><?= e($warehouse['organization_unit_name_snapshot'] ?: $warehouse['name']) ?></h3>
                    <p>Almacén P4 autorizado · Identificador <?= e((int) $warehouse['id']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p>Las asignaciones se muestran para validación. Las operaciones continúan bloqueadas hasta integrar los controles de acceso a cada registro.</p>
    <?php endif; ?>
</section>
<section class="p4-home-panel">
    <span class="eyebrow">SEGUIMIENTO POR ALMACÉN</span>
    <h2>Mis solicitudes, transferencias y entregas</h2>
    <?php if (empty($p4OperationsReadable)): ?>
        <p>No fue posible validar los registros del P4. Información no disponible por seguridad.</p>
    <?php elseif (empty($p4OperationalSummary)): ?>
        <p>Asigne primero un almacén P4 autorizado.</p>
    <?php else: ?>
        <?php foreach ($p4OperationalSummary as $warehouseId => $operations): ?>
            <h3>Almacén P4 #<?= e((int) $warehouseId) ?></h3>
            <div class="p4-home-grid">
                <?php foreach ([
                    'requests' => 'Solicitudes',
                    'transfers' => 'Transferencias',
                    'deliveries' => 'Entregas',
                ] as $key => $label): ?>
                    <article class="p4-step-card">
                        <span class="p4-step-number"><?= e($label) ?></span>
                        <?php if ($operations[$key] === []): ?>
                            <p>Sin registros en este almacén.</p>
                        <?php else: ?>
                            <?php foreach ($operations[$key] as $record): ?>
                                <p><strong><?= e($record['code']) ?></strong>
                                · <?= e($record['status']) ?>
                                · <?= e($record['event_at'] ?? '') ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <p>Vista de seguimiento de solo lectura, filtrada en la base de datos por almacén. No autoriza movimientos de inventario.</p>
</section>

<section class="p4-home-panel" aria-labelledby="p4-workflow-title">
    <div class="p4-home-heading">
        <div>
            <span class="eyebrow">FLUJO OPERATIVO</span>
            <h2 id="p4-workflow-title">Etapas del P4</h2>
        </div>
        <small>Las acciones permanecen bloqueadas para el rol P4 hasta comprobar el acceso exclusivo a su almacén.</small>
    </div>
    <div class="p4-home-grid">
        <?php foreach ($steps as $step): ?>
            <?php
            $canAccess = !in_array('P4', (array) (sindesg_user()['role_codes'] ?? []), true)
                && sindesg_can($step['permission'])
                && sindesg_role_action_allowed($step['module'], $step['action'], 'GET', (array) sindesg_user());
            $url = '?module=' . $step['module']
                . ($step['action'] !== 'index' ? '&action=' . $step['action'] : '');
            ?>
            <article class="p4-step-card">
                <span class="p4-step-number"><?= e($step['number']) ?></span>
                <h3><?= e($step['title']) ?></h3>
                <p><?= e($step['description']) ?></p>
                <?php if ($canAccess): ?>
                    <a href="<?= e(sindesg_url($url)) ?>">Abrir etapa <span aria-hidden="true">→</span></a>
                <?php else: ?>
                    <span class="p4-step-locked">Próximamente: requiere aislamiento por P4</span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="p4-home-panel">
    <span class="eyebrow">TRAZABILIDAD</span>
    <h2>Responsabilidades</h2>
    <p>El P4 registra sus necesidades, confirma la recepción física y documenta las entregas. El Almacén Central revisa, autoriza y despacha las transferencias. Esta pantalla no presupone cantidades aprobadas, stock ni solicitudes existentes.</p>
</section>
