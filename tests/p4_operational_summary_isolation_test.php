<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4AssignedWarehouses.php';
require dirname(__DIR__) . '/app/Services/P4OperationalSummary.php';
use App\Services\P4OperationalSummary;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('sqlite driver required');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER, code TEXT, name TEXT, organization_unit_name_snapshot TEXT, warehouse_type TEXT, is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER, warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER,request_code TEXT,p4_warehouse_id INTEGER, status TEXT,requested_at TEXT,requested_by_label_snapshot TEXT);
CREATE TABLE distributions(id INTEGER,transfer_code TEXT,destination_warehouse_id INTEGER,status TEXT,prepared_at TEXT,dispatched_at TEXT,received_at TEXT,received_by_label_snapshot TEXT);
CREATE TABLE individual_deliveries(id INTEGER,delivery_code TEXT,p4_warehouse_id INTEGER,status TEXT,delivered_at TEXT,recipient_label_snapshot TEXT);
CREATE TABLE products(id INTEGER,name TEXT);
CREATE TABLE sizes(id INTEGER,name TEXT);
CREATE TABLE product_variants(id INTEGER,product_id INTEGER,size_id INTEGER);
CREATE TABLE service_request_items(id INTEGER,request_id INTEGER,product_variant_id INTEGER,quantity_requested REAL);
CREATE TABLE distribution_items(id INTEGER,distribution_id INTEGER,product_variant_id INTEGER,quantity REAL);
CREATE TABLE individual_delivery_items(id INTEGER,delivery_id INTEGER,product_variant_id INTEGER,quantity REAL);
INSERT INTO warehouses VALUES(6,'P4-000006','Pacora','16 Zona Policial - Pacora','P4',1),(4,'P4-000004','Colon','Colón','P4',1);
INSERT INTO security_user_p4_scopes VALUES(10,6),(20,4);
INSERT INTO products VALUES(1,'Botas Tácticas');
INSERT INTO sizes VALUES(1,'9.5');
INSERT INTO product_variants VALUES(1,1,1);
INSERT INTO service_requests VALUES(1,'SOL-P',6,'draft','2026-01-01','Funcionario de Pacora'),(2,'SOL-C',4,'approved','2026-01-02','Funcionario de Colón'),(3,'SOL-P2',6,'submitted','2026-01-03','Funcionario de Pacora');
INSERT INTO distributions VALUES(10,'DP-P',6,'dispatched','2026-01-01','2026-01-04',NULL,NULL),(11,'DP-C',4,'received','2026-01-02','2026-01-02','2026-01-05','Receptor Colón');
INSERT INTO individual_deliveries VALUES(20,'DI-P',6,'draft','2026-01-01','Receptor Pacora'),(21,'DI-C',4,'confirmed','2026-01-02','Receptor Colón');
INSERT INTO service_request_items VALUES(101,3,1,1.000),(102,2,1,2.000);
INSERT INTO distribution_items VALUES(201,10,1,1.000),(202,11,1,2.000);
INSERT INTO individual_delivery_items VALUES(301,20,1,1.000),(302,21,1,2.000);
SQL);
$service = new P4OperationalSummary($pdo);
$p = $service->forWarehouse(10,6,20);
$c = $service->forWarehouse(20,4,20);
if (array_column($p['requests'],'code') !== ['SOL-P2','SOL-P']
    || array_column($c['requests'],'code') !== ['SOL-C']
    || array_column($p['transfers'],'code') !== ['DP-P']
    || array_column($p['deliveries'],'code') !== ['DI-P']
    || array_column($c['deliveries'],'code') !== ['DI-C']) {
    throw new RuntimeException('SQL unit isolation failed');
}
if ($p['deliveries'][0]['person_label'] !== 'Receptor Pacora'
    || (float)$p['deliveries'][0]['total_quantity'] !== 1.0
    || $p['deliveries'][0]['product_name'] !== 'Botas Tácticas'
    || $p['deliveries'][0]['size_name'] !== '9.5'
    || $p['requests'][0]['person_label'] !== 'Funcionario de Pacora'
    || (float)$p['requests'][0]['total_quantity'] !== 1.0
    || $c['deliveries'][0]['person_label'] !== 'Receptor Colón'
    || $c['transfers'][0]['person_label'] !== 'Receptor Colón'
    || (float)$c['transfers'][0]['total_quantity'] !== 2.0
    || !empty($p['transfers'][0]['person_label'])) {
    throw new RuntimeException('Operational descriptions mix warehouses or omit real details.');
}
foreach ([[10,4],[20,6],[10,0]] as [$user,$warehouse]) {
    try {
        $service->forWarehouse($user,$warehouse);
        throw new RuntimeException('Unauthorized scope unexpectedly permitted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Unauthorized scope unexpectedly permitted') throw $e;
    }
}
echo "[OK] SQL isolates requests, transfers, and deliveries for Pacora and Colon.\n";
