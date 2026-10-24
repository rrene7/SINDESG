<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require $root.'/app/Services/P4InventoryDigest.php';
require $root.'/app/Services/P4AssignedWarehouses.php';
require $root.'/app/Services/P4DashboardData.php';

use App\Services\P4InventoryDigest as Digest;
use App\Services\P4DashboardData;

$assert=static function(bool $condition,string $message):void {
    if (!$condition) throw new RuntimeException($message);
};

// One article with 15 real boot sizes: exactly 5 rows initially,
// 10 hidden behind a native details expander.
$products=[['product_id'=>1,'product_name'=>'Botas Tácticas','physical_quantity'=>'514','variant_count'=>15]];
$variants=[];
for($i=0;$i<15;$i++){
    $variants[]=[
        'product_id'=>1,
        'size_name'=>(string)(5+$i*0.5),
        'physical_quantity'=>(string)($i===0?500:1),
    ];
}
$groups=Digest::group($products,$variants);
$assert(count($groups)===1 && $groups[0]['name']==='Botas Tácticas',
    'Las tallas deben pertenecer a su artículo real.');
$assert((float)$groups[0]['total']===514.0,'No debe sustituirse el saldo completo por la muestra.');
$assert(count($groups[0]['first_sizes'])===5 && count($groups[0]['more_sizes'])===10,
    'Mostrar exactamente cinco tallas y desplegar el resto.');
$assert($groups[0]['complete']===true,'Las 15 tallas están cubiertas.');
$assert(count(Digest::group([],[]))===0,'Sin existencias no mostrar tarjetas.');

$repeated=Digest::group(
    [['product_id'=>1,'product_name'=>'Bota','physical_quantity'=>8,'variant_count'=>2]],
    [
        ['product_id'=>1,'size_name'=>'9','physical_quantity'=>3],
        ['product_id'=>1,'size_name'=>'9','physical_quantity'=>5],
    ]
);
$assert(count($repeated[0]['sizes'])===1
    && (float)$repeated[0]['sizes'][0]['quantity']===8.0,
    'Variantes de la misma talla deben agruparse sin duplicar la talla.');

$thousand=[];
for($i=0;$i<1000;$i++){
    $thousand[]=['product_id'=>1,'size_name'=>(string)$i,'physical_quantity'=>1];
}
$big=Digest::group([[
    'product_id'=>1,'product_name'=>'Botas Tácticas',
    'physical_quantity'=>1000,'variant_count'=>1000,
]],$thousand);
$assert(count($big[0]['first_sizes'])===5 && count($big[0]['more_sizes'])===20,
    'No se deben cargar centenares de tallas en el dashboard.');
$assert(!$big[0]['complete'] && $big[0]['shown_variants']===25
    && $big[0]['total']===1000.0,
    'Los totales históricos no deben calcularse desde 25 variantes.');
$manyProducts=[];
for($i=1;$i<=100;$i++)$manyProducts[]=[
    'product_id'=>$i,'product_name'=>'Artículo '.$i,
    'physical_quantity'=>1,'variant_count'=>1
];
$assert(count(Digest::group($manyProducts,[]))===12,
    'El dashboard limita la cantidad de artículos a doce.');

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    throw new RuntimeException('La prueba requiere PDO SQLite.');
}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER,code TEXT,name TEXT,warehouse_type TEXT,is_active INTEGER,organization_unit_name_snapshot TEXT);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE service_request_items(id INTEGER,request_id INTEGER,beneficiary_employee_id INTEGER,quantity_received REAL,quantity_delivered REAL);
CREATE TABLE distributions(id INTEGER,destination_warehouse_id INTEGER,status TEXT);
CREATE TABLE individual_deliveries(id INTEGER,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE products(id INTEGER,name TEXT);
CREATE TABLE sizes(id INTEGER,name TEXT,sort_order INTEGER);
CREATE TABLE product_variants(id INTEGER,product_id INTEGER,size_id INTEGER);
CREATE TABLE v_inventory_stock(warehouse_id INTEGER,product_variant_id INTEGER,quantity_on_hand REAL);
INSERT INTO warehouses VALUES(6,'P4-6','Don Bosco','P4',1,'15 Zona Policial - Don Bosco'),
(7,'P4-7','Otra Zona','P4',1,'Otra Zona');
INSERT INTO security_user_p4_scopes VALUES(10,6),(20,7);
INSERT INTO products VALUES(1,'Botas Tácticas'),(2,'Camisa Regular');
SQL);
for($i=1;$i<=28;$i++){
    $pdo->exec("INSERT INTO sizes VALUES ($i,'Talla $i',$i)");
    $pdo->exec("INSERT INTO product_variants VALUES ($i,1,$i)");
    $pdo->exec("INSERT INTO v_inventory_stock VALUES (6,$i,1)");
}
$pdo->exec("INSERT INTO product_variants VALUES(50,2,NULL)");
$pdo->exec("INSERT INTO v_inventory_stock VALUES(6,50,7)");
$pdo->exec("INSERT INTO v_inventory_stock VALUES(7,50,100)");
$data=(new P4DashboardData($pdo))->forWarehouse(10,6);
$assert((int)$data['metrics']['stock_variants']===29
    && (int)$data['metrics']['stock_products']===2
    && (float)$data['metrics']['physical_units']===35.0,
    'Las métricas deben incluir las 29 variantes autorizadas.');
$assert(count($data['stock'])===25,'El listado de tallas debe seguir limitado a 25 variantes.');
$summary=Digest::group($data['stock_products'],$data['stock']);
$assert(count($summary)===2
    && $summary[0]['name']==='Botas Tácticas'
    && $summary[0]['total']===28.0
    && $summary[0]['shown_variants']===25
    && !$summary[0]['complete']
    && $summary[1]['name']==='Camisa Regular'
    && $summary[1]['total']===7.0
    && $summary[1]['first_sizes']===[],
    'Los totales por artículo deben ser completos aun cuando sus tallas no caben en el límite.');
$other=(new P4DashboardData($pdo))->forWarehouse(20,7);
$assert($other['metrics']['physical_units']===100.0
    && count($other['stock_products'])===1
    && $other['stock_products'][0]['product_name']==='Camisa Regular',
    'Nunca se deben mezclar existencias de otro P4.');
try {
    (new P4DashboardData($pdo))->forWarehouse(10,7);
    throw new LogicException('Se permitió consultar otro P4.');
} catch(RuntimeException $expected) {}

// Prevent a future change from undoing native accessible expansion.
$view=(string)file_get_contents($root.'/app/Views/dashboard-p4.php');
$part=(string)file_get_contents($root.'/app/Views/partials/p4-inventory-product.php');
foreach([
    'P4InventoryDigest::group(',
    'class="p4-v2-product-list"',
    'p4-v2-inventory-more',
    'p4-v2-inventory-extra',
    "require __DIR__.'/partials/p4-inventory-product.php'",
    'max-height:220px','max-height:320px',
    'Saldo físico · no disponible libre',
] as $check){
    $assert(str_contains($view,$check),'Falta UI de inventario compacto: '.$check);
}
foreach(['<details class="p4-v2-product"','<summary>','p4-v2-stock-more',
    'p4-v2-stock-extra','foreach ($product[\'first_sizes\']',
    'foreach ($product[\'more_sizes\']','if (!$product[\'complete\'])',
    'El total del artículo sí incluye todas.'] as $check){
    $assert(str_contains($part,$check),'Falta detalle escalable por artículo y talla: '.$check);
}
$assert(!str_contains($view,'foreach ($data[\'stock\'] as $line)')
    && !str_contains($view,'inventory_movements'),
    'El dashboard no debe seguir imprimiendo todas las variantes ni cargar kardex.');
echo "[OK] Dashboard P4: totales completos por artículo, 5 tallas iniciales, detalle acotado y aislamiento por almacén.\n";
