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
CREATE TABLE service_requests(id INTEGER,request_code TEXT,p4_warehouse_id INTEGER, status TEXT,requested_at TEXT);
CREATE TABLE distributions(id INTEGER,transfer_code TEXT,destination_warehouse_id INTEGER,status TEXT,prepared_at TEXT);
CREATE TABLE individual_deliveries(id INTEGER,delivery_code TEXT,p4_warehouse_id INTEGER,status TEXT,delivered_at TEXT);
INSERT INTO warehouses VALUES(6,'P4-000006','Pacora','16 Zona Policial - Pacora','P4',1),(4,'P4-000004','Colon','Colón','P4',1);
INSERT INTO security_user_p4_scopes VALUES(10,6),(20,4);
INSERT INTO service_requests VALUES(1,'SOL-P',6,'draft','2026-01-01'),(2,'SOL-C',4,'approved','2026-01-02'),(3,'SOL-P2',6,'submitted','2026-01-03');
INSERT INTO distributions VALUES(10,'DP-P',6,'dispatched','2026-01-01'),(11,'DP-C',4,'received','2026-01-02');
INSERT INTO individual_deliveries VALUES(20,'DI-P',6,'draft','2026-01-01'),(21,'DI-C',4,'confirmed','2026-01-02');
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
foreach ([[10,4],[20,6],[10,0]] as [$user,$warehouse]) {
    try {
        $service->forWarehouse($user,$warehouse);
        throw new RuntimeException('Unauthorized scope unexpectedly permitted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Unauthorized scope unexpectedly permitted') throw $e;
    }
}
echo "[OK] SQL isolates requests, transfers, and deliveries for Pacora and Colon.\n";
