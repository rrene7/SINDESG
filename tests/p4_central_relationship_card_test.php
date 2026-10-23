<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/P4DashboardActivitySections.php';
require dirname(__DIR__).'/app/Services/P4ActivityList.php';

use App\Services\P4ActivityList;
use App\Services\P4DashboardActivitySections as Sections;

$assert=static function(bool $yes,string $what):void {
    if (!$yes) throw new RuntimeException($what);
};
$records=[
 ['type'=>'deliveries','id'=>51,'event_at'=>'2026-10-23 15:45:00','warehouse_name'=>'P4 Don Bosco'],
 ['type'=>'requests','id'=>12,'event_at'=>'2026-10-22 13:34:00','warehouse_name'=>'P4 Don Bosco'],
 ['type'=>'transfers','id'=>20,'event_at'=>'2026-10-23 14:05:00','warehouse_name'=>'P4 Don Bosco'],
 ['type'=>'deliveries','id'=>52,'event_at'=>'2026-10-23 14:41:00','warehouse_name'=>'P4 Don Bosco'],
 ['type'=>'requests','id'=>13,'event_at'=>'2026-10-23 15:00:00','warehouse_name'=>'P4 Don Bosco'],
 ['type'=>'transfers','id'=>21,'event_at'=>'2026-10-21 11:00:00','warehouse_name'=>'P4 Don Bosco']
];
$groups=Sections::group($records);
$assert(array_column($groups['requests'],'id')===[13,12],
    'La tarjeta de Central mezcla entregas con solicitudes o altera el orden.');
$assert(array_column($groups['transfers'],'id')===[20,21],
    'El despacho y la recepción de Central deben mantenerse separados de la solicitud.');
$assert(array_column($groups['deliveries'],'id')===[51,52],
    'El seguimiento local debe mostrar únicamente entregas a funcionarios.');
$assert(count($groups['requests'])+count($groups['transfers'])+count($groups['deliveries'])===count($records),
    'La separación perdió o duplicó registros.');
$big=[];
for($i=0;$i<1000;$i++){
    $type=['requests','transfers','deliveries'][$i%3];
    $big[]=['type'=>$type,'id'=>$i+1,'event_at'=>sprintf('2026-10-%02d 13:00:00',1+($i%23))];
}
$bigGroups=Sections::group($big);
foreach($bigGroups as $type=>$rows){
    [$preview,$extra]=P4ActivityList::split($rows);
    $assert(count($preview)===3 && count($extra)===5,
        'Un circuito con miles de registros no debe aumentar el tamaño de la página: '.$type);
    $assert(count(array_unique(array_column(array_merge($preview,$extra),'id')))===8,
        'Registros duplicados en la tarjeta: '.$type);
    $assert(!array_diff(array_column(array_merge($preview,$extra),'type'),[$type]),
        'Tarjeta contiene movimientos de otro circuito: '.$type);
}
$root=dirname(__DIR__);
$view=(string)file_get_contents($root.'/app/Views/dashboard-p4.php');
$partial=(string)file_get_contents($root.'/app/Views/partials/p4-central-log.php');
$service=(string)file_get_contents($root.'/app/Services/P4OperationalSummary.php');
$router=(string)file_get_contents($root.'/public/index.php');
foreach ([
 'P4DashboardActivitySections::group($dashboardEntries)',
 '$activity = $flowSections[\'deliveries\']',
 '$centralRequestPreview, $centralRequestExtra',
 '$centralTransferPreview, $centralTransferExtra',
 'RELACIÓN LOGÍSTICA · P4 ↔ ALMACÉN CENTRAL',
 'Solicitudes al Almacén Central',
 'Solicitudes del P4 a Central',
 'Despachos de Central hacia el P4',
 'Nueva solicitud a Central',
 'De la solicitud',
 'foreach ($activityPreview as $record)',
 'foreach ($activityExtra as $record)',
 "require __DIR__.'/partials/p4-central-log.php'",
] as $piece){
    // The stages were moved to the dedicated relationship panel, not as a duplicate lower workflow.
    if ($piece==='De la solicitud') {
        $assert(!str_contains($view,$piece),'La tarjeta inferior duplica el flujo Central/P4.');
        continue;
    }
    $assert(str_contains($view,$piece),'Falta separación del circuito: '.$piece);
}
$assert(str_contains($view,"'requests' => ['title'") &&
        str_contains($view,"'transfers' => ['title'") &&
        str_contains($view,"'deliveries' => ['title'"),
        'Debe conservarse la clasificación de documentos reales.');
$assert(str_contains($view,"\$totals['requests_waiting_central']")
    && str_contains($view,"\$totals['transfers_to_receive']"),
    'Los indicadores deben seguir usando los agregados SQL históricos.');
$assert(str_contains($partial,'sindesg_role_action_allowed(')
    && str_contains($partial,'sindesg_can(')
    && str_contains($partial,"\$record['code']")
    && str_contains($partial,"\$record['warehouse_name']"),
    'Los documentos de Central deben conservar el control RBAC, el código y su P4.');
$assert(str_contains($service,'new P4AssignedWarehouses')
    && str_contains($service,'p4_warehouse_id = :warehouse_id')
    && str_contains($service,'destination_warehouse_id = :warehouse_id')
    && str_contains($router,'P4OperationalSummary'),
    'Los documentos deben llegar mediante SQL restringido al P4, nunca por consulta global.');
$assert(!str_contains($view,'inventory_movements')
    && !str_contains($view,'UPDATE individual_deliveries'),
    'El rediseño no debe escribir ni exponer inventario.');
echo "[OK] P4/Central: tarjeta independiente, solicitudes y transferencias separadas, entregas locales, RBAC y 3+5 máximos.\n";
