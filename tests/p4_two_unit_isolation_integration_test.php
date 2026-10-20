<?php
declare(strict_types=1);
// Use the modern in-memory two-unit scenario: it checks both P4 users,
// inactive units, manual deliveries, nominal rows, and direct URL guards.
$root = dirname(__DIR__);
require $root.'/tests/p4_nominal_delivery_isolation_test.php';
$src = (string)file_get_contents($root.'/app/Services/P4AssignedWarehouses.php');
foreach ([
    'security_user_p4_scopes', 'user_id', "w.warehouse_type = 'P4'", 'w.is_active = 1'
] as $token) {
    if (!str_contains($src, $token)) {
        throw new RuntimeException('Assigned warehouse isolation lost: '.$token);
    }
}
$source = (string)file_get_contents($root.'/app/Services/P4DeliveryGuard.php');
if (!str_contains($source, 'assertNominal(')
    || !str_contains($source, 'assertDelivery(')) {
    throw new RuntimeException('Two-unit direct record guard lost.');
}
echo "[OK] Two-unit isolation preserved with strict P4-only workflows.\n";
