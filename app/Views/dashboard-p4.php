<?php
declare(strict_types=1);

$warehouses = [];
foreach (($p4AssignedWarehouses ?? []) as $warehouse) {
    $warehouses[(int)$warehouse['id']] =
        (string)($warehouse['organization_unit_name_snapshot'] ?: $warehouse['name']);
}
$totals = [
    'requests_waiting_central' => 0,
    'transfers_to_receive' => 0,
    'beneficiaries_pending' => 0,
    'deliveries_confirmed' => 0,
    'physical_units' => 0.0,
    'stock_variants' => 0,
];
if (!empty($p4DashboardDataReady)) {
    foreach (($p4DashboardData ?? []) as $data) {
        foreach ($totals as $metric => $value) {
            $totals[$metric] += $data['metrics'][$metric];
        }
    }
}
$dashboardEntries = [];
$statusLabels = [
 'draft'=>'Borrador', 'submitted'=>'Enviada a Central',
 'in_review'=>'Central está revisando', 'approved'=>'Aprobada',
 'partially_approved'=>'Aprobada parcialmente',
 'rejected'=>'No aprobada', 'fulfilled'=>'Atendida', 'closed'=>'Cerrada',
 'prepared'=>'En preparación', 'authorized'=>'Autorizada',
 'dispatched'=>'En camino al P4', 'received'=>'Recibida en P4',
 'confirmed'=>'Entregada al funcionario', 'cancelled'=>'Cancelada',
];
foreach (($p4OperationalSummary ?? []) as $warehouseId => $operations) {
    foreach ([
        'requests' => ['title'=>'Solicitud de dotación','module'=>'requests'],
        'transfers' => ['title'=>'Transferencia del Almacén Central','module'=>'distribution'],
        'deliveries' => ['title'=>'Entrega individual de dotación','module'=>'deliveries'],
    ] as $type => $info) {
        foreach ($operations[$type] ?? [] as $record) {
            $dashboardEntries[] = array_merge($record, $info, [
                'type' => $type,
                'title' => \App\Services\P4ActivityLabel::title($type, $record),
                'warehouse_name' => $warehouses[(int)$warehouseId] ?? 'Almacén P4',
            ]);
        }
    }
}
// Requests and Central distributions are a separate P4 <-> Central circuit.
// Confirmed employee handovers stay in local follow-up only.
$flowSections = \App\Services\P4DashboardActivitySections::group($dashboardEntries);
$activity = $flowSections['deliveries'];
[$activityPreview, $activityExtra] = \App\Services\P4ActivityList::split($activity);
[$centralRequestPreview, $centralRequestExtra] = \App\Services\P4ActivityList::split($flowSections['requests']);
[$centralTransferPreview, $centralTransferExtra] = \App\Services\P4ActivityList::split($flowSections['transfers']);
$ready = !empty($p4ScopeReady) && !empty($p4OperationsReadable)
    && !empty($p4DashboardDataReady) && $warehouses !== [];
?>
<style>
.p4-dashboard-v2{display:grid;gap:13px}
.p4-dashboard-v2 .p4-v2-hero{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid #1c658d;border-radius:15px;padding:20px 23px;background:linear-gradient(115deg,#082943,#093c59)}
.p4-dashboard-v2 .p4-v2-hero h1{font-size:clamp(1.5rem,2.35vw,2rem);line-height:1.2;margin:6px 0}
.p4-dashboard-v2 .p4-v2-hero p{font-size:.86rem;color:#b6d6e7;margin:0}
.p4-dashboard-v2 .p4-v2-actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
.p4-dashboard-v2 .p4-v2-actions a{padding:11px 14px;border:1px solid #399ad6;border-radius:8px;font-size:.83rem;color:#ecf9ff;text-decoration:none;font-weight:650}
.p4-dashboard-v2 .p4-v2-actions a:first-child{background:#167dc1;border-color:#167dc1}
.p4-dashboard-v2 .p4-v2-metrics{display:grid;grid-template-columns:repeat(3,minmax(145px,1fr));gap:10px}
.p4-dashboard-v2 .p4-v2-metric{border:1px solid #1b5479;border-radius:12px;background:#071f37;padding:16px}
.p4-dashboard-v2 .p4-v2-metric strong{display:block;font-size:1.85rem;line-height:1.15;font-variant-numeric:tabular-nums;margin-bottom:5px}
.p4-dashboard-v2 .p4-v2-metric span{display:block;color:#e0f1fb;font-size:.83rem;font-weight:650}
.p4-dashboard-v2 .p4-v2-metric small{display:block;margin-top:6px;color:#a2c2d7;font-size:.72rem;line-height:1.4}
.p4-dashboard-v2 .p4-v2-panel{border:1px solid #1b5479;border-radius:13px;background:#061b30;padding:17px 20px}
.p4-dashboard-v2 .p4-v2-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:13px}
.p4-dashboard-v2 .p4-v2-heading h2{font-size:1.15rem;margin:3px 0 0}
.p4-dashboard-v2 .p4-v2-heading small,.p4-dashboard-v2 .p4-v2-note{font-size:.73rem;color:#a7c6d9;line-height:1.5}
.p4-dashboard-v2 .p4-v2-inventory{display:grid;grid-template-columns:repeat(auto-fit,minmax(265px,1fr));gap:12px}
.p4-dashboard-v2 .p4-v2-warehouse{border:1px solid #265575;border-radius:9px;padding:12px 14px}
.p4-dashboard-v2 .p4-v2-warehouse h3{font-size:.95rem;margin:0 0 10px}
.p4-dashboard-v2 .p4-v2-stock-row{display:grid;grid-template-columns:minmax(0,1fr) 72px 85px;gap:8px;padding:7px 2px;border-bottom:1px solid #153c59;align-items:center;font-size:.82rem}
.p4-dashboard-v2 .p4-v2-stock-row span:nth-child(2){color:#a7cce3}
.p4-dashboard-v2 .p4-v2-stock-row strong{text-align:right;font-variant-numeric:tabular-nums}
.p4-dashboard-v2 .p4-v2-activity{display:grid;gap:6px}
.p4-dashboard-v2 .p4-v2-activity-row{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr) minmax(115px,.6fr);gap:10px;align-items:center;border:1px solid #204b6a;border-radius:8px;padding:9px 12px;min-width:0}
.p4-dashboard-v2 .p4-v2-activity-row>div{min-width:0}
.p4-dashboard-v2 .p4-v2-activity-row a,.p4-dashboard-v2 .p4-v2-activity-row strong{line-height:1.35}
.p4-dashboard-v2 .p4-v2-activity-row a{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.p4-dashboard-v2 .p4-v2-activity-more{margin-top:4px;border:1px solid #275875;border-radius:9px;background:#08233a;overflow:hidden}
.p4-dashboard-v2 .p4-v2-activity-more summary{cursor:pointer;min-height:40px;padding:11px 14px;color:#a0e0ff;font-size:.83rem;font-weight:750}
.p4-dashboard-v2 .p4-v2-activity-more summary:hover{background:#0b3454}
.p4-dashboard-v2 .p4-v2-activity-more summary:focus-visible{outline:2px solid #75d9ff;outline-offset:-3px}
.p4-dashboard-v2 .p4-v2-activity-more .p4-v2-open-label{display:none}
.p4-dashboard-v2 .p4-v2-activity-more[open] .p4-v2-open-label{display:inline}
.p4-dashboard-v2 .p4-v2-activity-more[open] .p4-v2-closed-label{display:none}
.p4-dashboard-v2 .p4-v2-activity-overflow{max-height:320px;overflow-y:auto;overscroll-behavior:contain;padding:0 7px 8px;display:grid;gap:6px}
.p4-dashboard-v2 .p4-v2-activity-overflow .p4-v2-activity-row{background:#061b30}

.p4-dashboard-v2 .p4-v2-activity-row a{color:#8bdcff;text-decoration:none;font-weight:700;overflow-wrap:anywhere}
.p4-dashboard-v2 .p4-v2-activity-row div{font-size:.84rem}
.p4-dashboard-v2 .p4-v2-activity-row small{display:block;font-size:.72rem;color:#9bc1d8;margin-top:3px}
.p4-dashboard-v2 .p4-v2-activity-row>span{font-size:.8rem;color:#d0e3ed}
.p4-dashboard-v2 .p4-v2-steps{display:flex;flex-wrap:wrap;gap:8px}
.p4-dashboard-v2 .p4-v2-steps span{padding:9px 11px;border:1px solid #285776;border-radius:8px;color:#c5e1f0;font-size:.78rem}
.p4-dashboard-v2 .p4-v2-signature-note{margin-top:10px;color:#9ac1d6;font-size:.75rem}
.p4-dashboard-v2 .p4-v2-central-panel{border-color:#26729d;background:linear-gradient(120deg,#06213b,#052b45)}
.p4-dashboard-v2 .p4-v2-central-heading{margin-bottom:14px}
.p4-dashboard-v2 .p4-v2-central-heading h2{font-size:1.18rem}
.p4-dashboard-v2 .p4-v2-central-action{display:inline-flex;align-items:center;padding:10px 13px;border-radius:9px;border:1px solid #5cbbec;background:#126a9e;color:#fff;text-decoration:none;font-size:.82rem;font-weight:750}
.p4-dashboard-v2 .p4-v2-central-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
.p4-dashboard-v2 .p4-v2-central-stat{border:1px solid #276186;border-radius:10px;padding:12px 14px;background:#041a31;min-width:0}
.p4-dashboard-v2 .p4-v2-central-stat strong{display:block;font-size:1.4rem;font-variant-numeric:tabular-nums}
.p4-dashboard-v2 .p4-v2-central-stat span{font-size:.8rem;color:#d8eeff}
.p4-dashboard-v2 .p4-v2-central-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.p4-dashboard-v2 .p4-v2-central-column{border:1px solid #225a7f;border-radius:12px;background:#061d34;padding:13px;min-width:0}
.p4-dashboard-v2 .p4-v2-central-column h3{font-size:.95rem;margin:0 0 6px}
.p4-dashboard-v2 .p4-v2-central-column>p{font-size:.76rem;color:#a6c9e2;line-height:1.4;margin:0 0 10px}
.p4-dashboard-v2 .p4-v2-central-list{display:grid;gap:6px}
.p4-dashboard-v2 .p4-v2-central-entry{padding:10px 11px;border:1px solid #225471;border-radius:8px;min-width:0}
.p4-dashboard-v2 .p4-v2-central-entry-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:7px;margin-bottom:5px}
.p4-dashboard-v2 .p4-v2-central-entry-head a,.p4-dashboard-v2 .p4-v2-central-entry-head strong{color:#b6e9ff;text-decoration:none;font-size:.83rem}
.p4-dashboard-v2 .p4-v2-central-entry-head span{color:#e5f6ff;font-size:.73rem;font-weight:700}
.p4-dashboard-v2 .p4-v2-central-entry-title{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;font-size:.77rem;line-height:1.42;overflow-wrap:anywhere;margin-bottom:4px;color:#e6f2fb}
.p4-dashboard-v2 .p4-v2-central-entry>small{display:block;font-size:.72rem;color:#a4c7df}
.p4-dashboard-v2 .p4-v2-central-more{margin-top:8px;border:1px solid #275875;border-radius:8px}
.p4-dashboard-v2 .p4-v2-central-more summary{cursor:pointer;color:#9fddfb;min-height:42px;padding:10px;font-weight:700;font-size:.78rem}
.p4-dashboard-v2 .p4-v2-central-more summary:focus-visible{outline:2px solid #86dbff;outline-offset:2px}
.p4-dashboard-v2 .p4-v2-central-more .p4-v2-open-label{display:none}
.p4-dashboard-v2 .p4-v2-central-more[open] .p4-v2-open-label{display:inline}
.p4-dashboard-v2 .p4-v2-central-more[open] .p4-v2-closed-label{display:none}
.p4-dashboard-v2 .p4-v2-central-overflow{max-height:260px;overflow:auto;display:grid;gap:6px;padding:7px}
.p4-dashboard-v2 .p4-v2-central-stages{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.p4-dashboard-v2 .p4-v2-central-stages span{border:1px solid #326583;border-radius:999px;padding:7px 10px;color:#beddf0;font-size:.74rem}
.p4-dashboard-v2 .p4-v2-central-caption{margin:9px 0 0;color:#9ac3db;font-size:.72rem;line-height:1.5}
@media(max-width:760px){.p4-dashboard-v2 .p4-v2-central-columns{grid-template-columns:1fr}.p4-dashboard-v2 .p4-v2-central-stats{grid-template-columns:1fr 1fr}}

@media(max-width:1050px){.p4-dashboard-v2 .p4-v2-metrics{grid-template-columns:repeat(3,minmax(140px,1fr))}}
@media(max-width:690px){.p4-dashboard-v2 .p4-v2-metrics{grid-template-columns:repeat(2,minmax(130px,1fr))}.p4-dashboard-v2 .p4-v2-activity-row{grid-template-columns:1fr;gap:4px}}
@media(max-width:420px){.p4-dashboard-v2 .p4-v2-metrics{grid-template-columns:1fr}}
</style>
<div class="p4-dashboard-v2">
    <header class="p4-v2-hero">
        <div>
            <span class="eyebrow">PANEL LOGÍSTICO · P4</span>
            <h1><?= e(count($warehouses) === 1 ? reset($warehouses) : 'Mis almacenes autorizados') ?></h1>
            <p>Conoce qué falta por atender y qué hay físicamente en tu almacén.</p>
        </div>
        <nav class="p4-v2-actions" aria-label="Acciones P4">
            <?php if ($ready && sindesg_can('requests.manage')
                && sindesg_role_action_allowed('requests','new','GET',(array)sindesg_user())): ?>
                <!-- Solicitudes a Central ahora pertenecen a la tarjeta del circuito P4 / Central. -->
            <?php endif; ?>
            <?php if ($ready && sindesg_can('deliveries.view')
                && sindesg_role_action_allowed('deliveries','index','GET',(array)sindesg_user())): ?>
                <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Entregar dotación →</a>
                <a href="<?= e(sindesg_url('?module=deliveries&action=nominal-report')) ?>">Reporte nominal →</a>
            <?php endif; ?>
            <?php if ($ready && sindesg_role_action_allowed('profile','signature','GET',(array)sindesg_user())): ?>
                <a href="<?= e(sindesg_url('?module=profile&action=signature')) ?>">✍ Mi firma institucional</a>
            <?php endif; ?>
        </nav>
    </header>
    <?php if (!$ready): ?>
        <section class="p4-v2-panel">
            <h2>Información no disponible</h2>
            <p>No se pudieron verificar los almacenes y sus registros. Los indicadores y el inventario permanecen ocultos por seguridad.</p>
        </section>
    <?php else: ?>
        <section class="p4-v2-metrics" aria-label="Indicadores reales de mi almacén">
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['beneficiaries_pending'])) ?></strong><span>Funcionarios pendientes</span><small>Con prendas recibidas aún sin entregar</small></article>
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['deliveries_confirmed'])) ?></strong><span>Entregas confirmadas</span><small>Total histórico de mis almacenes</small></article>
            <article class="p4-v2-metric"><strong><?= e(\App\Services\P4QuantityDisplay::units((float)$totals['physical_units'])) ?></strong><span>Unidades físicas</span><small>Unidades, no miles · Incluye dotación reservada</small></article>
        </section>
        <section class="p4-v2-panel p4-v2-central-panel" aria-labelledby="p4-central-title">
            <div class="p4-v2-heading p4-v2-central-heading">
                <div><span class="eyebrow">RELACIÓN LOGÍSTICA · P4 ↔ ALMACÉN CENTRAL</span>
                    <h2 id="p4-central-title">Solicitudes al Almacén Central</h2></div>
                <?php if (sindesg_can('requests.manage') &&
                    sindesg_role_action_allowed('requests','new','GET',(array)sindesg_user())): ?>
                    <a class="p4-v2-central-action" href="<?= e(sindesg_url('?module=requests&action=new')) ?>">+ Nueva solicitud a Central</a>
                <?php endif; ?>
            </div>
            <div class="p4-v2-central-stats">
                <div class="p4-v2-central-stat"><strong><?= e(sindesg_units((int)$totals['requests_waiting_central'])) ?></strong><span>Solicitudes enviadas o en revisión por Central</span></div>
                <div class="p4-v2-central-stat"><strong><?= e(sindesg_units((int)$totals['transfers_to_receive'])) ?></strong><span>Transferencias despachadas pendientes de recibir en P4</span></div>
            </div>
            <div class="p4-v2-central-columns">
                <div class="p4-v2-central-column">
                    <h3>Solicitudes del P4 a Central</h3>
                    <p>Documentos que el P4 envió para revisión, aprobación y atención.</p>
                    <div class="p4-v2-central-list">
                        <?php $rows = $centralRequestPreview; require __DIR__.'/partials/p4-central-log.php'; ?>
                        <?php if (!$centralRequestPreview): ?><p class="p4-v2-note">No hay solicitudes registradas en tus almacenes.</p><?php endif; ?>
                    </div>
                    <?php if ($centralRequestExtra): ?>
                        <details class="p4-v2-central-more"><summary>
                            <span class="p4-v2-closed-label">Ver <?= e(count($centralRequestExtra)) ?> solicitudes más ↓</span>
                            <span class="p4-v2-open-label">Ocultar solicitudes adicionales ↑</span>
                        </summary><div class="p4-v2-central-overflow">
                            <?php $rows = $centralRequestExtra; require __DIR__.'/partials/p4-central-log.php'; ?>
                        </div></details>
                    <?php endif; ?>
                </div>
                <div class="p4-v2-central-column">
                    <h3>Despachos de Central hacia el P4</h3>
                    <p>Preparación, despacho, traslado y recepción de mercancía.</p>
                    <div class="p4-v2-central-list">
                        <?php $rows = $centralTransferPreview; require __DIR__.'/partials/p4-central-log.php'; ?>
                        <?php if (!$centralTransferPreview): ?><p class="p4-v2-note">No hay transferencias registradas para tus almacenes.</p><?php endif; ?>
                    </div>
                    <?php if ($centralTransferExtra): ?>
                        <details class="p4-v2-central-more"><summary>
                            <span class="p4-v2-closed-label">Ver <?= e(count($centralTransferExtra)) ?> transferencias más ↓</span>
                            <span class="p4-v2-open-label">Ocultar transferencias adicionales ↑</span>
                        </summary><div class="p4-v2-central-overflow">
                            <?php $rows = $centralTransferExtra; require __DIR__.'/partials/p4-central-log.php'; ?>
                        </div></details>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p4-v2-central-stages" aria-label="Etapas generales de relación P4 y Central">
                <span>1 · Solicitud del P4</span><span>2 · Revisión y aprobación Central</span>
                <span>3 · Despacho hacia P4</span><span>4 · Recepción en P4</span>
            </div>
            <p class="p4-v2-central-caption">Solicitudes y transferencias se muestran por separado y con sus códigos originales; una transferencia no implica asociación automática con una solicitud determinada.</p>
        </section>
        <section class="p4-v2-panel">
            <div class="p4-v2-heading">
                <div><span class="eyebrow">EXISTENCIAS FÍSICAS</span><h2>Inventario por artículo y talla</h2></div>
                <small>Saldo de movimientos de inventario, no disponibilidad libre</small>
            </div>
            <div class="p4-v2-inventory">
            <?php foreach (($p4DashboardData ?? []) as $warehouseId => $data): ?>
                <div class="p4-v2-warehouse">
                    <h3><?= e($warehouses[(int)$warehouseId] ?? 'Almacén P4') ?></h3>
                    <?php if (!$data['stock']): ?>
                        <p class="p4-v2-note">No hay artículos con saldo físico positivo.</p>
                    <?php else: ?>
                        <div class="p4-v2-stock-row"><strong>Artículo</strong><strong>Talla</strong><strong>Unidades</strong></div>
                        <?php foreach ($data['stock'] as $line): ?>
                            <div class="p4-v2-stock-row">
                                <span><?= e($line['product_name']) ?></span>
                                <span><?= e($line['size_name']) ?></span>
                                <strong><?= e(\App\Services\P4QuantityDisplay::units((float)$line['physical_quantity'])) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($data['metrics']['stock_variants'] > count($data['stock'])): ?>
                        <p class="p4-v2-note">Se muestran las primeras 25 variantes de <?= e((int)$data['metrics']['stock_variants']) ?> con existencias. El saldo total incluye todas.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </section>
        <section class="p4-v2-panel">
            <div class="p4-v2-heading">
                <div><span class="eyebrow">SEGUIMIENTO DEL P4</span><h2>Últimos movimientos y trámites</h2></div>
                <small>Entregas al funcionario · 3 visibles y hasta 8 al desplegar</small>
            </div>
            <div class="p4-v2-activity">
            <?php foreach ($activityPreview as $record): ?>
                <div class="p4-v2-activity-row">
                    <div>
                        <?php if (sindesg_role_action_allowed($record['module'],'show','GET',(array)sindesg_user())): ?>
                            <a href="<?= e(sindesg_url('?module='.$record['module'].'&action=show&id='.(int)$record['id'])) ?>"><?= e($record['title']) ?></a>
                        <?php else: ?><strong><?= e($record['title']) ?></strong><?php endif; ?>
                        <small><?= e($record['warehouse_name']) ?></small>
                    </div>
                    <span><?= e($statusLabels[(string)$record['status']] ?? (string)$record['status']) ?></span>
                    <span><?= !empty($record['event_at']) ? e(date('d/m/Y H:i', strtotime((string)$record['event_at']))) : '—' ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($activityExtra): ?>
                <details class="p4-v2-activity-more">
                    <summary>
                        <span class="p4-v2-closed-label">Ver <?= e(count($activityExtra)) ?> movimientos más ↓</span>
                        <span class="p4-v2-open-label">Ocultar movimientos adicionales ↑</span>
                    </summary>
                    <div class="p4-v2-activity-overflow">
                        <?php foreach ($activityExtra as $record): ?>
                            <div class="p4-v2-activity-row">
                                <div>
                                    <?php if (sindesg_role_action_allowed($record['module'],'show','GET',(array)sindesg_user())): ?>
                                        <a href="<?= e(sindesg_url('?module='.$record['module'].'&action=show&id='.(int)$record['id'])) ?>"><?= e($record['title']) ?></a>
                                    <?php else: ?><strong><?= e($record['title']) ?></strong><?php endif; ?>
                                    <small><?= e($record['warehouse_name']) ?></small>
                                </div>
                                <span><?= e($statusLabels[(string)$record['status']] ?? (string)$record['status']) ?></span>
                                <span><?= !empty($record['event_at']) ? e(date('d/m/Y H:i', strtotime((string)$record['event_at']))) : '—' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (!$activityPreview): ?><p class="p4-v2-note">Aún no hay entregas individuales registradas para tus almacenes.</p><?php endif; ?>
            </div>
        </section>
        <section class="p4-v2-panel">
            <div class="p4-v2-heading">
                <div><span class="eyebrow">ENTREGA NOMINAL</span><h2>Del P4 al funcionario</h2></div>
            </div>
            <div class="p4-v2-steps">
                <span>1 · Verificar funcionario y dotación</span>
                <span>2 · Firma de recepción y entrega</span>
                <span>3 · Generar constancia individual</span>
            </div>
            <p class="p4-v2-signature-note">
                <a href="<?= e(sindesg_url('?module=profile&action=signature')) ?>">Mi firma institucional →</a>
                · Registra tu firma personal y autoriza su uso en cada nueva entrega.
                La firma del receptor continúa siendo obligatoria.
            </p>
        </section>
    <?php endif; ?>
</div>
