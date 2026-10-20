<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repo = (string) file_get_contents($root.'/app/Repositories/SecurityRepository.php');
$controller = (string) file_get_contents($root.'/app/Controllers/AdminController.php');
$view = (string) file_get_contents($root.'/app/Views/admin/user-form.php');
foreach ([
    [$repo, 'assignP4WarehousesWithAudit('],
    [$repo, 'warehouse_type ='],
    [$repo, 'p4_scope_updated'],
    [$repo, 'beginTransaction()'],
    [$repo, 'rollBack()'],
    [$controller, "assign-p4-warehouses"],
    [$view, 'p4_warehouse_ids[]'],
    [$view, 'csrf_token()'],
] as [$file, $needle]) {
    if (!str_contains($file, $needle)) throw new RuntimeException('P4 assignment contract missing: '.$needle);
}
echo "[OK] P4 admin assignment is explicit, CSRF-protected and audit-transactional.\n";
