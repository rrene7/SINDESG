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
