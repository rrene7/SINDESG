<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4ActivityList.php';

use App\Services\P4ActivityList;

$assert = static function(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

foreach ([0,1,3,4,8,9,1000] as $n) {
    $items = [];
    for($i=0;$i<$n;$i++) $items[]=['id'=>$i+1,'type'=>'deliveries'];
    [$preview,$extra] = P4ActivityList::split($items);
    $assert(count($preview)===min($n,3),'Panel P4 muestra mas de 3 movimientos.');
    $assert(count($extra)===min(max($n-3,0),5),'El desplegable supera 5 movimientos.');
    $expected=array_slice($items,0,8);
    $assert(array_merge($preview,$extra)===$expected,
        'El recorte cambia el orden de movimientos o duplica registros.');
}
$root=dirname(__DIR__);
$view=(string)file_get_contents($root.'/app/Views/dashboard-p4.php');
$css=(string)file_get_contents($root.'/public/assets/css/dashboard-p4.css');
$controller=(string)file_get_contents($root.'/public/index.php');
foreach ([
    'P4ActivityList::split($activity)',
    'foreach ($activityPreview as $record)',
    'foreach ($activityExtra as $record)',
    '<details class="p4-v2-activity-more">',
    'class="p4-v2-activity-overflow"',
    'Ver <?= e(count($activityExtra)) ?> movimientos más',
    '$statusLabels[(string)$record[\'status\']]',
    "sindesg_role_action_allowed(\$record['module'],'show','GET'",
] as $part) {
    $assert(str_contains($view,$part),'P4 perdió control de vista, estado o permisos: '.$part);
}
$assert(str_contains($view,'usort($activity,') && str_contains($view,'P4ActivityLabel::title('),
    'Las entradas deben conservar orden cronológico y descripción real.');
$assert(!str_contains($view,'array_slice($activity, 0, 8)'),
    'No se debe duplicar el límite ya centralizado en P4ActivityList.');
$assert(str_contains($view,'max-height:320px')
    && str_contains($view,'overflow-y:auto')
    && str_contains($view,'summary:focus-visible'),
    'La expansión necesita altura limitada y controles de accesibilidad.');
$assert(str_contains($controller,'P4OperationalSummary')
    && !str_contains($view,'inventory_movements'),
    'La lista P4 debe conservar la consulta por ámbito sin cargar kardex completo.');
echo "[OK] Seguimiento P4 compacto: 3 visibles, 5 opcionales, altura controlada y trazabilidad intacta.\n";
