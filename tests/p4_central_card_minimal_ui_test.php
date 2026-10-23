<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/app/Services/P4QuantityDisplay.php';
require $root.'/app/Services/P4CentralCardSummary.php';
use App\Services\P4CentralCardSummary as Summary;

$assert=static function(bool $yes,string $message):void {if(!$yes)throw new RuntimeException($message);};
$one=['product_name'=>'Botas Tácticas','size_name'=>'9','total_quantity'=>1.000,'item_count'=>1];
$multi=['product_name'=>'Botas Tácticas','size_name'=>'9','total_quantity'=>516.000,'item_count'=>516];
$assert(Summary::item($one)==='Botas Tácticas · Talla 9 · 1 unid.', 'La fila individual omite datos esenciales.');
$assert(Summary::item($multi)==='516 renglones · 516 unid.', 'Un listado con muchos artículos no debe anunciar solo el primer producto.');
$assert(!str_contains(Summary::item($multi),'Botas Tácticas'), 'Sin comprobación de homogeneidad no se puede atribuir el total al primer artículo.');
$homogeneous = array_merge($multi, ['product_count'=>1]);
$assert(Summary::item($homogeneous)==='Botas Tácticas · 516 unidades',
    'El documento con 516 renglones del mismo artículo debe identificar Botas Tácticas.');
$mixed = array_merge($multi, ['product_count'=>2]);
$assert(Summary::item($mixed)==='2 artículos · 516 unidades',
    'Un documento mixto no debe aparecer incorrectamente como Botas Tácticas.');
$assert(Summary::item(array_merge($multi,['product_count'=>1,'product_name'=>'']))==='516 renglones · 516 unid.',
    'Sin nombre de artículo verificado se requiere un resumen neutro.');
$assert(Summary::item(['item_count'=>0,'total_quantity'=>0])==='Consultar detalle', 'El resumen nunca debe inventar artículos.');
$assert(Summary::status('in_review')==='En revisión'
    && Summary::status('dispatched')==='En camino'
    && Summary::status('received')==='Recibida'
    && Summary::status('fulfilled')==='Atendida'
    && Summary::status('custom_unknown')==='custom_unknown',
    'Los estados compactos deben conservar su sentido real, incluido estado desconocido.');

$view=(string)file_get_contents($root.'/app/Views/dashboard-p4.php');
$partial=(string)file_get_contents($root.'/app/Views/partials/p4-central-log.php');
foreach ([
    'Solicitudes al Almacén Central',
    'p4-v2-central-stats',
    '<span>En Central</span>',
    '<span>Por recibir</span>',
    '<h3>Solicitudes</h3>',
    '<h3>Despachos</h3>',
    'P4 → Central',
    'Central → P4',
    '+ Nueva solicitud',
    'p4-v2-central-columns',
    'max-height:240px',
    "require __DIR__.'/partials/p4-central-log.php'",
] as $fragment){
    $assert(str_contains($view,$fragment),'Control compacto faltante: '.$fragment);
}
foreach([
    'RELACIÓN LOGÍSTICA · P4 ↔ ALMACÉN CENTRAL',
    'Documentos que el P4 envió para revisión',
    'Preparación, despacho, traslado y recepción de mercancía.',
    'Etapas generales de relación P4 y Central',
    'Solicitudes y transferencias se muestran por separado',
    'Solicitudes enviadas o en revisión por Central',
    'Transferencias despachadas pendientes de recibir en P4',
] as $redundant){
    $assert(!str_contains($view,$redundant),'Texto excesivo no eliminado: '.$redundant);
}
$assert(str_contains($view,"\$totals['requests_waiting_central']")
    && str_contains($view,"\$totals['transfers_to_receive']"),
    'El resumen reducido debe mantener exactamente los indicadores institucionales.');
$assert(str_contains($partial,'P4CentralCardSummary::item($record)')
    && str_contains($partial,'P4CentralCardSummary::status(')
    && str_contains($partial,'sindesg_role_action_allowed(')
    && str_contains($partial,'sindesg_can(')
    && str_contains($partial,"\$record['code']")
    && str_contains($partial,'if (count($warehouses) > 1)')
    && str_contains($partial,"Detalle completo del documento "),
    'Código, detalle completo, RBAC o ámbito multi-P4 faltante.');
$assert(!str_contains($partial,'$record[\'person_label\']')
    && !str_contains($partial,'$record[\'title\'] ?></'),
    'La tarjeta no debe repetir el párrafo de personas y entregas del documento.');
$assert(str_contains($view,'[$centralRequestPreview, $centralRequestExtra]')
    && str_contains($view,'[$centralTransferPreview, $centralTransferExtra]')
    && str_contains($view,'$activity = $flowSections[\'deliveries\']'),
    'El límite 3+5 y la separación de entregas no deben alterarse.');
echo "[OK] Tarjeta Central-P4 simplificada: folio, estado, resumen y fecha; sin redundancias ni pérdida de permisos.\n";
