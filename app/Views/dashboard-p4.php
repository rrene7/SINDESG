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
$activity = [];
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
            $activity[] = array_merge($record, $info, [
                'warehouse_name' => $warehouses[(int)$warehouseId] ?? 'Almacén P4',
            ]);
        }
    }
}
usort($activity, static fn(array $a,array $b):int =>
    strcmp((string)($b['event_at']??''),(string)($a['event_at']??'')));
$activity = array_slice($activity, 0, 8);
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
.p4-dashboard-v2 .p4-v2-metrics{display:grid;grid-template-columns:repeat(5,minmax(145px,1fr));gap:10px}
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
.p4-dashboard-v2 .p4-v2-activity-row{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr) minmax(115px,.6fr);gap:12px;align-items:center;border:1px solid #204b6a;border-radius:8px;padding:10px 12px}
.p4-dashboard-v2 .p4-v2-activity-row a{color:#8bdcff;text-decoration:none;font-weight:700}
.p4-dashboard-v2 .p4-v2-activity-row div{font-size:.84rem}
.p4-dashboard-v2 .p4-v2-activity-row small{display:block;font-size:.72rem;color:#9bc1d8;margin-top:3px}
.p4-dashboard-v2 .p4-v2-activity-row>span{font-size:.8rem;color:#d0e3ed}
.p4-dashboard-v2 .p4-v2-steps{display:flex;flex-wrap:wrap;gap:8px}
.p4-dashboard-v2 .p4-v2-steps span{padding:9px 11px;border:1px solid #285776;border-radius:8px;color:#c5e1f0;font-size:.78rem}
.p4-dashboard-v2 .p4-v2-signature-note{margin-top:10px;color:#9ac1d6;font-size:.75rem}
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
                <a href="<?= e(sindesg_url('?module=requests&action=new')) ?>">+ Nueva solicitud</a>
            <?php endif; ?>
            <?php if ($ready && sindesg_can('deliveries.view')
                && sindesg_role_action_allowed('deliveries','index','GET',(array)sindesg_user())): ?>
                <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Entregar dotación →</a>
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
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['requests_waiting_central'])) ?></strong><span>Solicitudes en Central</span><small>Enviadas o en revisión</small></article>
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['transfers_to_receive'])) ?></strong><span>Transferencias por recibir</span><small>Despachadas, aún sin recepción confirmada</small></article>
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['beneficiaries_pending'])) ?></strong><span>Funcionarios pendientes</span><small>Con prendas recibidas aún sin entregar</small></article>
            <article class="p4-v2-metric"><strong><?= e(sindesg_units((int)$totals['deliveries_confirmed'])) ?></strong><span>Entregas confirmadas</span><small>Total histórico de mis almacenes</small></article>
            <article class="p4-v2-metric"><strong><?= e(\App\Services\P4QuantityDisplay::units((float)$totals['physical_units'])) ?></strong><span>Unidades físicas</span><small>Unidades, no miles · Incluye dotación reservada</small></article>
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
                <div><span class="eyebrow">SEGUIMIENTO</span><h2>Últimos movimientos y trámites</h2></div>
                <small>Hasta 8 registros recientes · referencias para auditoría</small>
            </div>
            <div class="p4-v2-activity">
            <?php foreach ($activity as $record): ?>
                <div class="p4-v2-activity-row">
                    <div>
                        <?php if (sindesg_role_action_allowed($record['module'],'show','GET',(array)sindesg_user())): ?>
                            <a href="<?= e(sindesg_url('?module='.$record['module'].'&action=show&id='.(int)$record['id'])) ?>"><?= e($record['title']) ?></a>
                        <?php else: ?><strong><?= e($record['title']) ?></strong><?php endif; ?>
                        <small><?= e($record['warehouse_name']) ?></small>
                    </div>
                    <span><?= e($statusLabels[(string)$record['status']] ?? (string)$record['status']) ?></span>
                    <span><?= e((string)($record['event_at']??'')) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$activity): ?><p class="p4-v2-note">Aún no hay trámites registrados para tus almacenes.</p><?php endif; ?>
            </div>
        </section>
        <section class="p4-v2-panel">
            <div class="p4-v2-heading">
                <div><span class="eyebrow">PROCESO DE DOTACIÓN</span><h2>De la solicitud a la entrega</h2></div>
            </div>
            <div class="p4-v2-steps">
                <span>1 · Solicitar a Central</span>
                <span>2 · Esperar aprobación</span>
                <span>3 · Recibir en P4</span>
                <span>4 · Entregar y firmar</span>
            </div>
            <p class="p4-v2-signature-note">
                <a href="<?= e(sindesg_url('?module=profile&action=signature')) ?>">Mi firma institucional →</a>
                · Registra tu firma personal y autoriza su uso en cada nueva entrega.
                La firma del receptor continúa siendo obligatoria.
            </p>
        </section>
    <?php endif; ?>
</div>
