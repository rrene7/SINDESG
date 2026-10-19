<?php
declare(strict_types=1);

$steps = [
    ['number' => '01', 'title' => 'Preparar solicitud', 'description' => 'Registrar la necesidad institucional y seleccionar las tallas y cantidades.', 'module' => 'requests', 'action' => 'new', 'permission' => 'requests.manage'],
    ['number' => '02', 'title' => 'Enviar al Almacén Central', 'description' => 'Revisar la solicitud, adjuntar la información nominal y remitirla para evaluación.', 'module' => 'requests', 'action' => 'show', 'permission' => 'requests.view'],
    ['number' => '03', 'title' => 'Seguimiento y recepción', 'description' => 'Consultar transferencias asignadas y confirmar solamente lo efectivamente recibido.', 'module' => 'distribution', 'action' => 'index', 'permission' => 'distribution.view'],
    ['number' => '04', 'title' => 'Entrega al funcionario', 'description' => 'Registrar la entrega física de las unidades recibidas y conservar su historial.', 'module' => 'deliveries', 'action' => 'index', 'permission' => 'deliveries.view'],
];
?>
<?php
$warehouseNames = [];
foreach (($p4AssignedWarehouses ?? []) as $wh) {
    $warehouseNames[(int) $wh['id']] = (string) ($wh['organization_unit_name_snapshot'] ?: $wh['name']);
}
$requestRows = []; $transferRows = []; $deliveryRows = [];
foreach (($p4OperationalSummary ?? []) as $whId => $data) {
    foreach ($data['requests'] ?? [] as $r) { $r['warehouse_name'] = $warehouseNames[(int)$whId] ?? 'Almacén P4'; $requestRows[]=$r; }
    foreach ($data['transfers'] ?? [] as $r) { $r['warehouse_name'] = $warehouseNames[(int)$whId] ?? 'Almacén P4'; $transferRows[]=$r; }
    foreach ($data['deliveries'] ?? [] as $r) { $r['warehouse_name'] = $warehouseNames[(int)$whId] ?? 'Almacén P4'; $deliveryRows[]=$r; }
}
$descriptions = [
 'draft'=>'Borrador · pendiente de enviar',
 'submitted'=>'Enviada a Central',
 'in_review'=>'Central está revisando',
 'approved'=>'Aprobada por Central',
 'partially_approved'=>'Aprobada parcialmente',
 'rejected'=>'No aprobada',
 'fulfilled'=>'Atendida',
 'closed'=>'Cerrada',
 'prepared'=>'Preparada',
 'authorized'=>'Autorizada',
 'dispatched'=>'En camino al P4',
 'received'=>'Recibida en el P4',
 'confirmed'=>'Entregada al funcionario',
 'cancelled'=>'Cancelada'
];
?>
<style>
.p4-dashboard-new{display:grid;gap:14px}
.p4-dashboard-new .p4-overview{border:1px solid #19618c;border-radius:14px;padding:20px 23px;background:linear-gradient(110deg,#082844,#073857);display:flex;gap:18px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.p4-dashboard-new .p4-overview h1{margin:5px 0;font-size:clamp(1.45rem,2.2vw,2rem)}
.p4-dashboard-new .p4-overview p{margin:0;color:#b7dbed}
.p4-dashboard-new .p4-actions{display:flex;gap:8px;flex-wrap:wrap}
.p4-dashboard-new .p4-actions a{border:1px solid #68c9f8;border-radius:8px;padding:10px 14px;color:#f4fbff;text-decoration:none;font-size:.85rem}
.p4-dashboard-new .p4-actions a:first-child{background:#197cc5;border-color:#197cc5}
.p4-dashboard-new .p4-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.p4-dashboard-new .p4-metric{border:1px solid #174f75;border-radius:12px;padding:15px 17px;background:#061c33}
.p4-dashboard-new .p4-metric strong{display:block;font-size:1.9rem;font-variant-numeric:tabular-nums}
.p4-dashboard-new .p4-metric span{color:#bfd6e5;font-size:.85rem}
.p4-dashboard-new .p4-metric small{display:block;color:#93b5c9;font-size:.72rem;margin-top:5px}
.p4-dashboard-new .p4-work-rows{display:grid;gap:7px}
.p4-dashboard-new .p4-work-row{display:grid;grid-template-columns:minmax(160px,2fr) minmax(110px,1.2fr) minmax(130px,1fr);gap:12px;align-items:center;border:1px solid #17486c;border-radius:8px;padding:10px 13px}
.p4-dashboard-new .p4-work-row a{color:#86d7ff;font-weight:700;text-decoration:none}
.p4-dashboard-new .p4-work-row span{font-size:.8rem;color:#c5dcec}
.p4-dashboard-new .p4-note{font-size:.76rem;color:#99bed5}
@media(max-width:640px){.p4-dashboard-new .p4-metrics{grid-template-columns:1fr}.p4-dashboard-new .p4-work-row{grid-template-columns:1fr;gap:5px}}
</style>
<div class="p4-dashboard-new">
  <header class="p4-overview">
    <div>
      <span class="eyebrow">MI PANEL DE TRABAJO · P4</span>
      <h1><?= e(count($warehouseNames) === 1 ? reset($warehouseNames) : 'Mis almacenes autorizados') ?></h1>
      <p>Solicitudes, mercancía recibida y entregas al personal, en un solo lugar.</p>
    </div>
    <nav class="p4-actions" aria-label="Acciones del almacén">
      <?php if (!empty($p4ScopeReady) && $warehouseNames && sindesg_can('requests.manage')): ?>
      <a href="<?= e(sindesg_url('?module=requests&action=new')) ?>">+ Nueva solicitud</a>
      <?php endif; ?>
      <?php if (!empty($p4ScopeReady) && $warehouseNames && sindesg_can('deliveries.view')): ?>
      <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Entregar dotación →</a>
      <?php endif; ?>
    </nav>
  </header>
  <?php if (empty($p4ScopeReady) || !$warehouseNames || empty($p4OperationsReadable)): ?>
    <section class="p4-home-panel"><h2>Acceso pendiente de verificación</h2><p>No podemos mostrar operaciones hasta verificar los almacenes autorizados y la conexión de datos.</p></section>
  <?php else: ?>
    <section class="p4-metrics" aria-label="Resumen de actividad">
      <article class="p4-metric"><strong><?= e(count($requestRows)) ?></strong><span>Solicitudes recientes</span><small>Registros visibles de mis almacenes</small></article>
      <article class="p4-metric"><strong><?= e(count($transferRows)) ?></strong><span>Transferencias recientes</span><small>Recibidas o en proceso</small></article>
      <article class="p4-metric"><strong><?= e(count($deliveryRows)) ?></strong><span>Entregas recientes</span><small>Confirmadas o en proceso</small></article>
    </section>
    <section class="p4-home-panel">
      <div class="p4-home-heading"><div><span class="eyebrow">ACTIVIDAD DE MIS ALMACENES</span><h2>¿Qué está pasando?</h2></div></div>
      <div class="p4-work-rows">
      <?php foreach ([
        ['Solicitudes', $requestRows, 'requests'],
        ['Transferencias', $transferRows, 'distribution'],
        ['Entregas', $deliveryRows, 'deliveries']
      ] as [$label, $rows, $module]): ?>
        <?php foreach ($rows as $record): ?>
        <div class="p4-work-row">
          <div>
            <?php $canOpen = $module !== 'deliveries' ? sindesg_role_action_allowed($module, 'show', 'GET', (array)sindesg_user()) : sindesg_role_action_allowed('deliveries', 'show', 'GET', (array)sindesg_user()); ?>
            <?php if ($canOpen): ?>
              <a href="<?= e(sindesg_url('?module=' . $module . '&action=show&id=' . (int)$record['id'])) ?>"><?= e($label) ?> · <?= e($record['warehouse_name']) ?></a>
            <?php else: ?>
              <strong><?= e($label) ?> · <?= e($record['warehouse_name']) ?></strong>
            <?php endif; ?>
            <div class="p4-note">Referencia: <?= e($record['code']) ?></div>
          </div>
          <span><?= e($descriptions[(string)$record['status']] ?? (string)$record['status']) ?></span>
          <span><?= e((string)($record['event_at'] ?? '')) ?></span>
        </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (!$requestRows && !$transferRows && !$deliveryRows): ?><p>No hay actividad reciente registrada en tus almacenes.</p><?php endif; ?>
      </div>
      <p class="p4-note">Este resumen muestra registros recientes, no totales históricos ni existencias de inventario. Solo consulta los almacenes asignados a tu usuario.</p>
    </section>
  <?php endif; ?>
</div>


<section class="p4-home-panel" aria-labelledby="p4-next-heading">
  <span class="eyebrow">¿QUÉ DEBO HACER?</span>
  <h2 id="p4-next-heading">Mi proceso de dotación</h2>
  <div class="p4-home-grid">
    <article class="p4-step-card"><h3>1. Solicitar</h3><p>Registra las necesidades del personal y envía la solicitud al Almacén Central.</p></article>
    <article class="p4-step-card"><h3>2. Esperar aprobación</h3><p>Central revisa las cantidades, prepara y despacha las prendas autorizadas.</p></article>
    <article class="p4-step-card"><h3>3. Recibir</h3><p>Consulta tus transferencias y confirma físicamente lo recibido en tu almacén.</p></article>
    <article class="p4-step-card"><h3>4. Entregar</h3><p>Selecciona al funcionario, registra su firma y confirma la entrega.</p></article>
  </div>
</section>
