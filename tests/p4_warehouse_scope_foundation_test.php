<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\P4WarehouseScope;

$cases = [
    [['P4'], [12], 12, true],
    [['P4'], [12], 13, false],
    [['P4'], [], 12, false],
    [['P4'], [12], 0, false],
    [['VIEWER'], [12], 12, true],
    [['CENTRAL'], [], 12, true],
    [['ADMIN'], [], 12, true],
];
foreach ($cases as [$roles, $scopes, $warehouse, $expected]) {
    if (P4WarehouseScope::authorized($roles, $scopes, $warehouse) !== $expected) {
        throw new RuntimeException('P4 scope evaluation mismatch');
    }
}
$schema = file_get_contents(dirname(__DIR__).'/database/schema/020_security_user_p4_scopes.sql');
if (!str_contains($schema, 'CREATE TABLE IF NOT EXISTS security_user_p4_scopes')
    || !str_contains($schema, 'FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)')
    || preg_match('/\b(?:UPDATE|DELETE|DROP|TRUNCATE)\b/i', preg_replace('/--[^\n]*/', '', $schema))) {
    throw new RuntimeException('P4 scope schema unsafe');
}
echo "[OK] Explicit P4 scope requires matching unit; cross-P4 access denied.\n";
