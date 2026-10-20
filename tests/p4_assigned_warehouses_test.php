<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4AssignedWarehouses.php';
use App\Services\P4AssignedWarehouses;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required for isolated P4 warehouse test.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, organization_unit_name_snapshot TEXT, warehouse_type TEXT, is_active INTEGER);
CREATE TABLE security_user_p4_scopes (user_id INTEGER, warehouse_id INTEGER);
INSERT INTO warehouses VALUES
(16,'PACORA16','Zona 16','Zona Policial 16 de Pacora','P4',1),
(24,'COLON','Colón','Colón','P4',1),
(30,'CENTRAL','Central','Central','CENTRAL',1),
(40,'INACTIVE','Inactivo','Inactivo','P4',0);
INSERT INTO security_user_p4_scopes VALUES (101,16),(202,24),(101,30),(101,40);");
$svc = new P4AssignedWarehouses($pdo);
$a = $svc->listForUser(101);
$b = $svc->listForUser(202);
if (count($a) !== 1 || (int) $a[0]['id'] !== 16
    || count($b) !== 1 || (int) $b[0]['id'] !== 24
    || $svc->listForUser(0) !== []
    || $svc->listForUser(300) !== []) {
    throw new RuntimeException('Assigned P4 lookup leaked an unrelated or inactive warehouse.');
}
echo "[OK] P4 assignments are explicit, active and user-specific.\n";
