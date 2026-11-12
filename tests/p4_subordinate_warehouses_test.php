<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Services/P4UnitHierarchy.php';

use App\Services\P4UnitHierarchy;

$units = [
    39 => ['id' => 39, 'parent_id' => null, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
    40 => ['id' => 40, 'parent_id' => 39, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
    41 => ['id' => 41, 'parent_id' => 40, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
    42 => ['id' => 42, 'parent_id' => 999, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
    43 => ['id' => 43, 'parent_id' => 39, 'is_active' => 0, 'lifecycle_status' => 'vigente'],
    44 => ['id' => 44, 'parent_id' => 39, 'is_active' => 1, 'lifecycle_status' => 'baja'],
    45 => ['id' => 45, 'parent_id' => 46, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
    46 => ['id' => 46, 'parent_id' => 45, 'is_active' => 1, 'lifecycle_status' => 'vigente'],
];
$find = static fn (int $id): ?array => $units[$id] ?? null;
$cases = [
    [40, 39, true, 'direct child'],
    [41, 39, true, 'deep descendant'],
    [41, 40, true, 'nested center'],
    [39, 39, false, 'head-only is not subordinate'],
    [39, 40, false, 'reversed ancestry'],
    [42, 39, false, 'other direction'],
    [43, 39, false, 'inactive child'],
    [44, 39, false, 'retired child'],
    [45, 39, false, 'hierarchy cycle'],
    [999, 39, false, 'missing child'],
];
foreach ($cases as [$child, $head, $expected, $label]) {
    if (P4UnitHierarchy::isDescendant($child, $head, $find) !== $expected) {
        throw new RuntimeException("Falló la validación de descendencia: $label");
    }
}
$units[40]['is_active'] = 0;
if (P4UnitHierarchy::isDescendant(41, 39, $find)) {
    throw new RuntimeException('No debe permitir un descendiente bajo una unidad intermedia inactiva.');
}

$files = [
    'repo' => 'app/Repositories/RrhhRepository.php',
    'api' => 'public/api/units.php',
    'service' => 'app/Services/WarehouseService.php',
    'warehouse' => 'app/Repositories/WarehouseRepository.php',
    'form' => 'app/Views/warehouses/form.php',
    'js' => 'public/assets/js/warehouses.js',
    'sql' => 'database/schema/024_p4_subordinate_warehouses.sql',
    'installer' => 'scripts/install_p4_subordinate_warehouses.php',
];
$source = [];
foreach ($files as $name => $file) {
    $source[$name] = file_get_contents($root . '/' . $file);
    if (!is_string($source[$name])) {
        throw new RuntimeException("Falta archivo requerido: $file");
    }
}

foreach ([
    ['repo', 'WITH RECURSIVE p4_tree'],
    ['repo', 'INNER JOIN p4_tree ON child.parent_id = p4_tree.id'],
    ['repo', 'isDescendantOf('],
    ['api', "sindesg_can('warehouses.manage')"],
    ['api', 'searchP4Subunits($headUnitId, $query, 50)'],
    ['service', 'P4UnitHierarchy::isCurrent'],
    ['service', 'isDescendantOf($subunitId, $organizationUnitId)'],
    ['service', 'existsForOrganizationUnit($targetUnitId)'],
    ['service', "'p4_head_unit_id' => $organizationUnitId"],
    ['warehouse', ':p4_head_unit_id'],
    ['form', 'name="use_p4_subunit"'],
    ['form', 'name="p4_subunit_id"'],
    ['js', 'getExtraParams: () => headId?.value ? { parent_id: headId.value } : null'],
    ['installer', "BackupSupport::manifest"],
    ['installer', "SELECT DATABASE()"],
    ['sql', "024_p4_subordinate_warehouses"],
    ['sql', 'ADD COLUMN IF NOT EXISTS p4_head_unit_id'],
] as [$name, $fragment]) {
    if (!str_contains($source[$name], $fragment)) {
        throw new RuntimeException("Falta protección P4 $name: $fragment");
    }
}
if (stripos($source['sql'], 'USE rrhh2029') !== false
    || stripos($source['repo'], 'UPDATE organization_units_current') !== false
    || stripos($source['repo'], 'DELETE FROM organization_units_current') !== false) {
    throw new RuntimeException('P4 no puede escribir en RRHH2029.');
}
echo "[OK] P4 cabecera y dependencias: jerarquía, API, aislamiento y migración aditiva." . PHP_EOL;
