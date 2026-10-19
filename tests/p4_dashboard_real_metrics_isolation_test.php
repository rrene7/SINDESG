<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4AssignedWarehouses.php';
require dirname(__DIR__) . '/app/Services/P4DashboardData.php';

use App\Services\P4DashboardData;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite is required for warehouse-scope tests');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER,code TEXT,name TEXT,warehouse_type TEXT,is_active INTEGER,organization_unit_name_snapshot TEXT);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER, p4_warehouse_id INTEGER, status TEXT);
CREATE TABLE service_request_items(id INTEGER,request_id INTEGER,beneficiary_employee_id INTEGER, quantity_received REAL,quantity_delivered REAL);
CREATE TABLE distributions(id INTEGER,destination_warehouse_id INTEGER,status TEXT);
CREATE TABLE individual_deliveries(id INTEGER,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE products(id INTEGER,name TEXT);
CREATE TABLE sizes(id INTEGER,name TEXT,sort_order INTEGER);
CREATE TABLE product_variants(id INTEGER,product_id INTEGER,size_id INTEGER);
CREATE TABLE v_inventory_stock(warehouse_id INTEGER,product_variant_id INTEGER,quantity_on_hand REAL);
INSERT INTO warehouses VALUES(6,'P4-000006','Pacora','P4',1,'16 Zona Pacora'),(4,'P4-000004','Colón','P4',1,'Colón'),(7,'P4-000007','Inactivo','P4',0,'Inactivo'),(8,'CENTRAL','Central','CENTRAL',1,'Central');
INSERT INTO security_user_p4_scopes VALUES(10,6),(20,4),(10,7),(10,8);
INSERT INTO service_requests VALUES(1,6,'in_review'),(2,6,'submitted'),(3,6,'fulfilled'),(4,4,'in_review'),(5,7,'submitted');
INSERT INTO service_request_items VALUES(11,3,91,1,0),(12,3,91,1,0),(13,3,92,1,1);
INSERT INTO distributions VALUES(1,6,'dispatched'),(2,6,'received'),(3,4,'dispatched');
INSERT INTO individual_deliveries VALUES(1,6,'confirmed'),(2,6,'draft'),(3,4,'confirmed');
INSERT INTO products VALUES(1,'Botas');
INSERT INTO sizes VALUES(1,'8.5',1);
SQL);
for ($i=1;$i<=29;$i++) {
    $pdo->exec("INSERT INTO product_variants VALUES ($i,1,1)");
    $wh = $i===29 ? 4 : 6;
    $pdo->exec("INSERT INTO v_inventory_stock VALUES ($wh,$i,1)");
}
$service=new P4DashboardData($pdo);
$p=$service->forWarehouse(10,6);
$c=$service->forWarehouse(20,4);
$m=$p['metrics'];
foreach ([
    'requests_waiting_central'=>2,
    'transfers_to_receive'=>1,
    'beneficiaries_pending'=>1,
    'deliveries_confirmed'=>1,
    'physical_units'=>28,
    'stock_variants'=>28,
] as $key=>$expected) {
    if ($m[$key] != $expected) throw new RuntimeException("$key count is incorrect.");
}
if(count($p['stock'])!==25 || $c['metrics']['physical_units']!==1.0
    || $c['metrics']['requests_waiting_central']!==1) {
    throw new RuntimeException('Stock pagination or cross-unit count is incorrect.');
}
foreach ([[10,4],[20,6],[10,7],[10,8],[0,6]] as [$user,$warehouse]) {
    try {
        $service->forWarehouse($user,$warehouse);
        throw new LogicException('Unassigned warehouse read permitted');
    } catch (RuntimeException $expected) {}
}
$view = (string)file_get_contents(dirname(__DIR__).'/app/Views/dashboard-p4.php');
$router = (string)file_get_contents(dirname(__DIR__).'/public/index.php');
if (!str_contains($view, 'Inventario por artículo y talla')
    || !str_contains($view, 'Funcionarios pendientes')
    || !str_contains($view, 'Mi firma institucional: función pendiente de activación')
    || !str_contains($router, 'P4DashboardData')
    || str_contains($view, 'Solicitudes recientes')) {
    throw new RuntimeException('Dashboard UI/SQL wiring is incomplete.');
}
echo "[OK] P4 dashboard counts are full aggregates, stock limited for display and SQL scope is isolated.\n";
