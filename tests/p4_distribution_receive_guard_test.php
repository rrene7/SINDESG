<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/P4DistributionGuard.php';
use App\Services\P4DistributionGuard;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('pdo_sqlite is required');
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE distributions(id INTEGER PRIMARY KEY,destination_warehouse_id INTEGER);
INSERT INTO warehouses VALUES(6,'P4',1),(4,'P4',1),(9,'P4',0),(10,'CENTRAL',1);
INSERT INTO security_user_p4_scopes VALUES(101,6),(202,4),(101,9),(101,10);
INSERT INTO distributions VALUES(1,6),(2,4),(3,9),(4,10);
SQL);
$guard=new P4DistributionGuard($db);
$guard->assertAssigned(101,1);$guard->assertAssigned(202,2);
foreach ([[101,2],[202,1],[101,3],[101,4],[101,0],[0,1]] as [$u,$d]) {
    try {$guard->assertAssigned($u,$d);throw new LogicException('Unauthorized transfer accepted');}
    catch(RuntimeException $expected) {}
}
$controller=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/DistributionController.php');
$policy=(string)file_get_contents(dirname(__DIR__).'/app/bootstrap.php');
if(!str_contains($controller,'__construct(private Database $database)')
    || !str_contains($controller,'assertAssigned(')
    || !str_contains($controller,"\$post['received_by_employee_id'] = (int) (\$user['employee_id'] ?? 0)")
    || !str_contains($policy,"\$action === 'receive'"))
    throw new RuntimeException('P4 receive security wiring incomplete');
echo "[OK] P4 receive limits access to own active unit and binds receiver identity.\n";
