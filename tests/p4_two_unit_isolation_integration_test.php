<?php
declare(strict_types=1);

/**
 * Independent in-memory two-unit isolation regression.
 * Runs without SINDESG production credentials and never accesses RRHH2029.
 */
require dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php';
require dirname(__DIR__) . '/app/Services/P4WarehouseScope.php';

use App\Repositories\DeliveryRepository;
use App\Services\P4WarehouseScope;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('SQLite PDO driver is required for P4 scope integration tests.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('GREATEST', static fn (...$args) => max($args), -1);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, warehouse_type TEXT,
 is_active INTEGER, organization_unit_name_snapshot TEXT);
CREATE TABLE security_user_p4_scopes (user_id INTEGER, warehouse_id INTEGER);
CREATE TABLE individual_deliveries (id INTEGER PRIMARY KEY, delivery_code TEXT, p4_warehouse_id INTEGER,
 delivered_at TEXT, status TEXT, recipient_employee_id INTEGER, recipient_label_snapshot TEXT,
 recipient_document_snapshot TEXT);
CREATE TABLE individual_delivery_items (id INTEGER PRIMARY KEY, delivery_id INTEGER,
 product_variant_id INTEGER, quantity REAL);
CREATE TABLE service_requests (id INTEGER PRIMARY KEY, p4_warehouse_id INTEGER,
 request_code TEXT, requested_at TEXT, status TEXT);
CREATE TABLE service_request_items (id INTEGER PRIMARY KEY, request_id INTEGER,
 product_variant_id INTEGER, beneficiary_employee_id INTEGER, beneficiary_label_snapshot TEXT,
 beneficiary_document_snapshot TEXT, beneficiary_position_snapshot TEXT,
 beneficiary_rank_snapshot TEXT, quantity_received REAL, quantity_delivered REAL);
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category_id INTEGER);
CREATE TABLE product_variants (id INTEGER PRIMARY KEY, product_id INTEGER, internal_code TEXT,
 size_id INTEGER, brand_id INTEGER);
CREATE TABLE sizes (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE brands (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE distributions (id INTEGER PRIMARY KEY, transfer_code TEXT, status TEXT, received_at TEXT);
CREATE TABLE distribution_items (id INTEGER PRIMARY KEY, distribution_id INTEGER, request_item_id INTEGER);
INSERT INTO warehouses VALUES
 (10,'P4-A','Unidad A','P4',1,'P4 A'),
 (20,'P4-B','Unidad B','P4',1,'P4 B'),
 (30,'CENTRAL','Central','CENTRAL',1,'Almacén Central');
INSERT INTO security_user_p4_scopes VALUES (101,10),(202,20),(303,30);
INSERT INTO individual_deliveries VALUES
 (1,'DI-A-1',10,'2026-01-01','confirmed',1,'Funcionario 1','CED1'),
 (2,'DI-B-1',20,'2026-06-01','confirmed',1,'Funcionario 1','CED1'),
 (3,'DI-B-2',20,'2026-06-02','confirmed',2,'Funcionario 2','CED2'),
 (4,'DI-A-2',10,'2026-01-02','confirmed',1,'Funcionario 1','CED1');
INSERT INTO individual_delivery_items VALUES (1,1,1,1),(2,2,1,1),(3,3,1,1),(4,4,1,1);
INSERT INTO service_requests VALUES
 (101,10,'SOL-A','2026-01-01','approved'),
 (201,20,'SOL-B','2026-02-01','approved');
INSERT INTO service_request_items VALUES
 (110,101,1,1,'Funcionario A','CED1','1','Agente',1,0),
 (210,201,1,2,'Funcionario B','CED2','2','Agente',1,0),
 (211,201,1,3,'Funcionario C','CED3','3','Agente',1,0);
INSERT INTO categories VALUES (1,'Calzado');
INSERT INTO products VALUES (1,'Botas',1);
INSERT INTO product_variants VALUES (1,1,'B-8',NULL,NULL);
SQL);

$scope = new P4WarehouseScope($pdo);
if ($scope->warehouseIdsForUser(101) !== [10]
    || $scope->warehouseIdsForUser(202) !== [20]
    || $scope->warehouseIdsForUser(303) !== []) {
    throw new RuntimeException('P4 warehouse assignments leaked across users or accepted Central.');
}
$repo = new DeliveryRepository($pdo);
$a = $repo->recent(2, [10]);
$b = $repo->recent(2, [20]);
if (count($a) !== 2 || count($b) !== 2
    || array_column($a, 'p4_warehouse_id') !== [10,10]
    || array_column($b, 'p4_warehouse_id') !== [20,20]
    || $repo->recent(2, []) !== []) {
    throw new RuntimeException('Delivery listing is not SQL-scoped before LIMIT.');
}
$nominalA = $repo->pendingNominal(1, [10]);
$nominalB = $repo->pendingNominal(2, [20]);
if (count($nominalA) !== 1 || (int) $nominalA[0]['p4_warehouse_id'] !== 10
    || count($nominalB) !== 2
    || array_column($nominalB, 'p4_warehouse_id') !== [20,20]
    || $repo->pendingNominal(2, []) !== []) {
    throw new RuntimeException('Nominal list leaked another P4 or paginated before scope.');
}
$datesA = $repo->lastConfirmedDatesForProduct(1, [1], [10]);
$datesB = $repo->lastConfirmedDatesForProduct(1, [1], [20]);
if (($datesA[1] ?? null) !== '2026-01-02'
    || ($datesB[1] ?? null) !== '2026-06-01'
    || $repo->lastConfirmedDatesForProduct(1, [1], []) !== []) {
    throw new RuntimeException('Cross-P4 prior-delivery dates leaked.');
}
echo "[OK] Two distinct P4s isolated for assignments, deliveries, nominal lines and previous dates.\n";
