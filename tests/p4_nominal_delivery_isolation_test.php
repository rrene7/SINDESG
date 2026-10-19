<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/P4DeliveryGuard.php';
require dirname(__DIR__).'/app/Repositories/DeliveryRepository.php';
use App\Services\P4DeliveryGuard;
use App\Repositories\DeliveryRepository;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('GREATEST', static fn (...$n) => max($n), -1);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER,request_code TEXT,p4_warehouse_id INTEGER,status TEXT,requested_at TEXT);
CREATE TABLE service_request_items(id INTEGER,request_id INTEGER,product_variant_id INTEGER,beneficiary_employee_id INTEGER,beneficiary_label_snapshot TEXT,beneficiary_document_snapshot TEXT,quantity_received REAL,quantity_delivered REAL);
CREATE TABLE individual_deliveries(id INTEGER,p4_warehouse_id INTEGER,recipient_employee_id INTEGER);
CREATE TABLE individual_delivery_items(id INTEGER,delivery_id INTEGER,request_item_id INTEGER);
CREATE TABLE product_variants(id INTEGER,product_id INTEGER,internal_code TEXT,size_id INTEGER);
CREATE TABLE products(id INTEGER,name TEXT);
CREATE TABLE sizes(id INTEGER,name TEXT);
INSERT INTO warehouses VALUES(6,'P4',1),(4,'P4',1),(9,'P4',0);
INSERT INTO security_user_p4_scopes VALUES(101,6),(202,4),(101,9);
INSERT INTO service_requests VALUES(1,'PACORA',6,'fulfilled','2026-10-14'),(2,'COLON',4,'fulfilled','2026-10-14'),(3,'INACTIVE',9,'fulfilled','2026-10-14');
INSERT INTO service_request_items VALUES(11,1,1,321,'Receptor P','P',1,0),(12,2,1,654,'Receptor C','C',1,0),(13,3,1,987,'Inactivo','X',1,0);
INSERT INTO individual_deliveries VALUES(41,6,321),(42,4,654),(43,6,321);
INSERT INTO individual_delivery_items VALUES(1,41,11),(2,42,12),(3,43,NULL);
INSERT INTO products VALUES(1,'Botas');
INSERT INTO product_variants VALUES(1,1,'B-8',NULL);
SQL);
$guard=new P4DeliveryGuard($db);
$guard->assertNominal(101,11);
$guard->assertNominal(202,12);
$guard->assertDelivery(101,41);
foreach ([[101,12],[101,13],[202,11],[0,11]] as [$u,$id]) {
    try { $guard->assertNominal($u,$id); throw new LogicException('Cross-unit nominal permitted'); }
    catch(RuntimeException $expected) {}
}
foreach ([[101,42],[202,41],[101,43],[0,41]] as [$u,$id]) {
    try { $guard->assertDelivery($u,$id); throw new LogicException('Cross-unit/manual delivery permitted'); }
    catch(RuntimeException $expected) {}
}
$rows=(new DeliveryRepository($db))->pendingNominalForUser(101,1);
if(count($rows)!==1 || (int)$rows[0]['request_item_id']!==11) throw new RuntimeException('P4 SQL queue not properly restricted');
$src=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/DeliveryController.php');
foreach (['assertNominal(', 'assertDelivery(', 'pendingNominalForUser(', "\$post['allow_out_of_scope'] = '0'", "\$post['delivered_by_employee_id']"] as $word)
 if(!str_contains($src,$word)) throw new RuntimeException('Delivery guard wiring missing: '.$word);
echo "[OK] P4 Pacora nominal delivery isolated, manual draft denied and queue scoped in SQL.\n";
