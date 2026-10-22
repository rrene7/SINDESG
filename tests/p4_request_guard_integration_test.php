<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4RequestGuard.php';
use App\Services\P4RequestGuard;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY,p4_warehouse_id INTEGER);
INSERT INTO warehouses VALUES(6,'P4',1),(4,'P4',1),(7,'P4',0),(9,'CENTRAL',1);
INSERT INTO security_user_p4_scopes VALUES(10,6),(20,4),(10,7),(10,9);
INSERT INTO service_requests VALUES(101,6),(102,4),(103,7),(104,9);
SQL);
$guard = new P4RequestGuard($db);
if (!$guard->allowed(10,6) || !$guard->allowed(20,4) || $guard->allowed(10,4)
  || $guard->allowed(10,7) || $guard->allowed(10,9) || $guard->allowed(0,6)) {
    throw new RuntimeException('Incorrect P4 assignment access');
}
foreach ([[10,101],[20,102]] as [$user,$request]) $guard->assertRequest($user,$request);
foreach ([[10,102],[10,103],[10,104],[20,101],[0,101],[10,0]] as [$user,$request]) {
    try { $guard->assertRequest($user,$request); throw new LogicException('Cross-P4 access permitted'); }
    catch (RuntimeException $e) { /* expected */ }
}
$controller = (string)file_get_contents(dirname(__DIR__).'/app/Controllers/RequestsController.php');
foreach (['assertRequest($userId', "['save', 'add-item', 'remove-item', 'submit', 'preview-nominal-file']", '$post[\'requested_by_employee_id\']', '$guard->allowed($userId'] as $needle) {
    if (!str_contains($controller, $needle)) throw new RuntimeException('Request route scope missing: '.$needle);
}
echo "[OK] Pacora P4 draft submission cannot access another unit or inactive warehouse.\n";
