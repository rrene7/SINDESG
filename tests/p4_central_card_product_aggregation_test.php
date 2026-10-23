<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/app/Services/P4AssignedWarehouses.php';
require $root.'/app/Services/P4OperationalSummary.php';
require $root.'/app/Services/P4QuantityDisplay.php';
require $root.'/app/Services/P4CentralCardSummary.php';

use App\Services\P4OperationalSummary;
use App\Services\P4CentralCardSummary;

$check=static function(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException($message);
};
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    throw new RuntimeException('SQLite is required for the SQL-scope integration test.');
}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER,code TEXT,name TEXT,organization_unit_name_snapshot TEXT,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE products(id INTEGER,name TEXT);
CREATE TABLE sizes(id INTEGER,name TEXT);
CREATE TABLE product_variants(id INTEGER,product_id INTEGER,size_id INTEGER);
CREATE TABLE service_requests(id INTEGER,request_code TEXT,p4_warehouse_id INTEGER,status TEXT,requested_at TEXT,requested_by_label_snapshot TEXT);
CREATE TABLE service_request_items(id INTEGER,request_id INTEGER,product_variant_id INTEGER,quantity_requested REAL);
CREATE TABLE distributions(id INTEGER,transfer_code TEXT,destination_warehouse_id INTEGER,status TEXT,prepared_at TEXT,dispatched_at TEXT,received_at TEXT,received_by_label_snapshot TEXT);
CREATE TABLE distribution_items(id INTEGER,distribution_id INTEGER,product_variant_id INTEGER,quantity REAL);
CREATE TABLE individual_deliveries(id INTEGER,delivery_code TEXT,p4_warehouse_id INTEGER,status TEXT,delivered_at TEXT,recipient_label_snapshot TEXT);
CREATE TABLE individual_delivery_items(id INTEGER,delivery_id INTEGER,product_variant_id INTEGER,quantity REAL);
INSERT INTO warehouses VALUES (6,'P4-6','P4 autorizado','Zona Don Bosco','P4',1),
 (7,'P4-7','P4 diferente','Zona diferente','P4',1);
INSERT INTO security_user_p4_scopes VALUES (10,6),(20,7);
INSERT INTO products VALUES (1,'Botas Tácticas'),(2,'Camisa Uniforme');
INSERT INTO sizes VALUES (1,'9'),(2,'9.5');
INSERT INTO product_variants VALUES (101,1,1),(102,1,2),(201,2,NULL);
INSERT INTO service_requests VALUES
 (10,'SOL-BOTAS',6,'fulfilled','2026-10-23 12:00:00','Operador P4'),
 (11,'SOL-MIXTA',6,'approved','2026-10-23 13:00:00','Operador P4'),
 (12,'SOL-AJENA',7,'fulfilled','2026-10-23 14:00:00','Otro P4');
INSERT INTO distributions VALUES
 (20,'TRF-BOTAS',6,'received','2026-10-23 12:00:00','2026-10-23 12:30:00','2026-10-23 13:00:00','Operador P4'),
 (21,'TRF-MIXTA',6,'dispatched','2026-10-23 13:00:00','2026-10-23 13:30:00',NULL,NULL),
 (22,'TRF-AJENA',7,'received','2026-10-23 14:00:00','2026-10-23 14:10:00','2026-10-23 14:20:00','Otro P4');
SQL);
$db->beginTransaction();
$request=$db->prepare('INSERT INTO service_request_items(id,request_id,product_variant_id,quantity_requested) VALUES(?,?,?,?)');
$transfer=$db->prepare('INSERT INTO distribution_items(id,distribution_id,product_variant_id,quantity) VALUES(?,?,?,?)');
for($i=1;$i<=516;$i++){
    // Distinct boot sizes are still the same product, and must say Botas.
    $variant=$i%2===0?101:102;
    $request->execute([$i,10,$variant,1]);
    $transfer->execute([$i,20,$variant,1]);
}
$request->execute([600,11,101,1]);
$request->execute([601,11,201,2]);
$transfer->execute([600,21,101,1]);
$transfer->execute([601,21,201,2]);
$request->execute([700,12,201,10]);
$transfer->execute([700,22,201,10]);
$db->commit();

$repository=new P4OperationalSummary($db);
$scoped=$repository->forWarehouse(10,6);
$check(array_column($scoped['requests'],'code')===['SOL-MIXTA','SOL-BOTAS'],
    'Se filtró una solicitud de otro P4 o se mezclaron expedientes.');
$check(array_column($scoped['transfers'],'code')===['TRF-MIXTA','TRF-BOTAS'],
    'Se filtró un despacho de otro P4.');
$bootsRequest=$scoped['requests'][1];
$bootsTransfer=$scoped['transfers'][1];
foreach([$bootsRequest,$bootsTransfer] as $record){
    $check((int)$record['item_count']===516 && (int)$record['product_count']===1
        && (float)$record['total_quantity']===516.0
        && $record['product_name']==='Botas Tácticas',
        'Las 516 líneas de distintas tallas deben identificarse como un artículo homogéneo.');
    $check(P4CentralCardSummary::item($record)==='Botas Tácticas · 516 unidades',
        'La tarjeta debe decir Botas Tácticas y no solo 516 renglones.');
}
foreach([$scoped['requests'][0],$scoped['transfers'][0]] as $record){
    $check((int)$record['product_count']===2
        && (float)$record['total_quantity']===3.0
        && P4CentralCardSummary::item($record)==='2 artículos · 3 unidades',
        'No atribuir el total de un pedido mixto al primer producto.');
}
$other=$repository->forWarehouse(20,7);
$check(array_column($other['requests'],'code')===['SOL-AJENA']
    && array_column($other['transfers'],'code')===['TRF-AJENA'],
    'Una cuenta de otro P4 no debe acceder a Don Bosco.');
echo "[OK] SQL por P4: 516 botas de varias tallas identificadas como Botas Tácticas; pedidos mixtos nunca se atribuyen al primer producto.\n";
