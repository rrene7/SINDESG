<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CentralRequestReview;
use App\Services\P4RequestTracking;

$rows = [
    ['id'=>1,'product_variant_id'=>10,'product_name'=>'Botas Táctica','size_name'=>'8.5',
     'variant_code'=>'SND-10','category_name'=>'Botas','central_stock'=>'0','available_to_approve'=>'0',
     'quantity_requested'=>1,'quantity_approved'=>0,'quantity_dispatched'=>0,
     'quantity_received'=>0,'quantity_delivered'=>0,'beneficiary_employee_id'=>3],
    ['id'=>2,'product_variant_id'=>11,'product_name'=>'Botas Táctica','size_name'=>'9',
     'variant_code'=>'SND-11','central_stock'=>'0','available_to_approve'=>'0',
     'quantity_requested'=>1,'quantity_approved'=>0,'quantity_dispatched'=>0,
     'quantity_received'=>0,'quantity_delivered'=>0,'beneficiary_employee_id'=>4],
    ['id'=>3,'product_variant_id'=>10,'product_name'=>'Botas Táctica','size_name'=>'8.5',
     'variant_code'=>'SND-10','central_stock'=>'0','available_to_approve'=>'0',
     'quantity_requested'=>1,'quantity_approved'=>0,'quantity_dispatched'=>0,
     'quantity_received'=>0,'quantity_delivered'=>0,'beneficiary_employee_id'=>5],
];
$g = CentralRequestReview::groups($rows);
if (count($g)!==2 || $g[0]['size_name']!=='8.5'
    || $g[0]['requested']!==2.0 || $g[0]['recipients']!==2
    || $g[0]['available_to_approve']!==0.0
    || $g[1]['requested']!==1.0) {
    throw new RuntimeException('El Central no agrupa correctamente sin inventar stock.');
}
$request = [
    'status'=>'draft','created_at'=>'2026-10-13 11:00:00',
    'submitted_at'=>null,'reviewed_at'=>null,
];
$totals = ['requested'=>722,'approved'=>0,'dispatched'=>0,'received'=>0,'delivered'=>0];
$draft = P4RequestTracking::build($request,$totals,[]);
if ($draft['steps'][0]['done']!==true || $draft['steps'][1]['done']!==false
    || $draft['steps'][3]['done']!==false
    || $draft['steps'][6]['done']!==false
    || $draft['steps'][7]['done']!==false) {
    throw new RuntimeException('Borrador no debe señalar envío, aprobación ni recepción.');
}
$sent = P4RequestTracking::build(array_merge($request,[
    'status'=>'submitted','submitted_at'=>'2026-10-14 09:00:00'
]),$totals,[]);
if (!$sent['steps'][1]['done'] || $sent['steps'][3]['done']) {
    throw new RuntimeException('Una solicitud enviada no ha sido aprobada.');
}
$approved = P4RequestTracking::build(array_merge($request,[
    'status'=>'approved','reviewed_at'=>'2026-10-14 10:00:00'
]),array_replace($totals,['approved'=>722]),[]);
if (!$approved['steps'][3]['done'] || $approved['steps'][4]['done']) {
    throw new RuntimeException('Aprobación lógica no implica preparación física.');
}
$progress = P4RequestTracking::build(array_merge($request,['status'=>'approved']),array_replace($totals,[
    'approved'=>722,'dispatched'=>100,'received'=>60,'delivered'=>15,
]),[
    'prepared_count'=>2,'authorized_count'=>1,
    'last_prepared_at'=>'2026-10-15 10:00:00',
    'last_dispatched_at'=>'2026-10-15 11:00:00',
    'last_received_at'=>'2026-10-16 11:00:00',
]);
if (!$progress['steps'][4]['done'] || !$progress['steps'][5]['done']
    || !$progress['steps'][6]['done'] || !$progress['steps'][7]['done']
    || !str_contains($progress['steps'][7]['detail'],'15')
    || $progress['steps'][6]['date']!=='2026-10-16 11:00:00') {
    throw new RuntimeException('El tracking debe reflejar solo movimientos registrados.');
}

$root = dirname(__DIR__);
$repo = (string) file_get_contents($root.'/app/Repositories/RequestRepository.php');
$ctl = (string) file_get_contents($root.'/app/Controllers/RequestsController.php');
$show = (string) file_get_contents($root.'/app/Views/requests/show.php');
$central = (string) file_get_contents($root.'/app/Views/requests/central-review.php');
$index = (string) file_get_contents($root.'/app/Views/requests/index.php');
$tracking = (string) file_get_contents($root.'/app/Views/requests/tracking.php');
$js = (string) file_get_contents($root.'/public/assets/js/requests.js');
$warehouseController = (string) file_get_contents($root.'/app/Controllers/WarehousesController.php');
$warehouseRepository = (string) file_get_contents($root.'/app/Repositories/WarehouseRepository.php');
$warehouseView = (string) file_get_contents($root.'/app/Views/warehouses/show.php');

foreach ([
    "public function transferProgress(",
    "FROM distributions",
    "WHERE request_id = :request_id",
    "last_dispatched_at",
    "last_received_at",
    "freeByVariant",
] as $piece) {
    if (!str_contains($repo,$piece)) {
        throw new RuntimeException('Falta consulta de estados o caché para 722 personas: '.$piece);
    }
}
if (!str_contains($ctl,"if (\$action === 'central-review')")
    || !str_contains($ctl,"P4RequestTracking::build(")
    || !str_contains($central,"Central")
    || !str_contains($central,"data-central-variant")
    || !str_contains($central,"data-central-item")
    || !str_contains($central,"approved_quantity[")
    || !str_contains($central,"action=decide")
    || !str_contains($central,"csrf_token()")
    || !str_contains($show,"tracking.php")
    || !str_contains($index,"#seguimiento-p4")
    || !str_contains($tracking,"Seguimiento del trámite, no ubicación GPS")
    || !str_contains($js,"data-central-apply")
    || !str_contains($warehouseRepository,"public function requestsForP4(")
    || !str_contains($warehouseController,"'p4RequestTracking' =>")
    || !str_contains($warehouseView,"#seguimiento-p4")
    || !str_contains($warehouseView,"Recepción confirmada en P4")) {
    throw new RuntimeException('Vista Central, aprobación segura o tracking no están conectados.');
}
if (preg_match('/(?:INSERT|UPDATE|DELETE)\s+(?:rrhh|inventory_movements)/i',$repo.$ctl.$central.$tracking)) {
    throw new RuntimeException('El tracking y la revisión no deben mover bienes ni modificar RRHH.');
}
echo "[OK] Central agrupa por talla sin N+1 consultas; tracking P4 refleja hitos reales sin stock ficticio.\n";
