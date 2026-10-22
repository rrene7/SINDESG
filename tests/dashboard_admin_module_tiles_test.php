<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/DashboardModuleTiles.php';

use App\Services\DashboardModuleTiles;

$cases = [
    [['role_codes' => ['ADMIN']], true],
    [['role_codes' => ['ADMIN','CENTRAL']], true],
    [['role_codes' => ['CENTRAL']], false],
    [['role_codes' => ['WAREHOUSE']], false],
    [['role_codes' => ['P4']], false],
    [['role_codes' => ['VIEWER']], false],
    [['role_codes' => []], false],
    [[], false],
    [['role_codes' => 'ADMIN'], true],
];
foreach ($cases as [$user, $expected]) {
    if (DashboardModuleTiles::visibleFor($user) !== $expected) {
        throw new RuntimeException('Módulos inferiores visibles para un rol equivocado.');
    }
}

$root = dirname(__DIR__);
$dashboard = (string) file_get_contents($root . '/app/Views/dashboard.php');
$layout = (string) file_get_contents($root . '/app/Views/layout.php');
$router = (string) file_get_contents($root . '/public/index.php');
$dashboard = str_replace(["\r\n","\r"], "\n", $dashboard);

$guard = "<?php if (\\App\\Services\\DashboardModuleTiles::visibleFor((array) sindesg_user())): ?>";
$grid = '<section class="module-grid">';
$operation = '<section class="operations-grid">';
$footer = '<section class="system-strip">';

$guardAt = strpos($dashboard, $guard);
$gridAt = strpos($dashboard, $grid);
$operationsAt = strpos($dashboard, $operation);
$footerAt = strpos($dashboard, $footer);
if ($guardAt === false || $gridAt === false || $operationsAt === false || $footerAt === false
    || !($operationsAt < $guardAt && $guardAt < $gridAt && $gridAt < $footerAt)) {
    throw new RuntimeException('El guard solo debe envolver tarjetas inferiores, no la operación del almacenista.');
}
$closing = strpos($dashboard, '<?php endif; ?>', $gridAt);
if ($closing === false || $closing > $footerAt) {
    throw new RuntimeException('El directorio administrativo no está delimitado correctamente.');
}
if (substr_count($dashboard, $grid) !== 1) {
    throw new RuntimeException('La vista presenta un directorio de módulos duplicado.');
}
foreach ([
    'Lotes recientes',
    'Solicitudes y distribución',
    'data-dashboard-product-id',
    'data-digital-turntable',
    'data-feature-available',
    'data-feature-sizes',
] as $needle) {
    if (!str_contains($dashboard, $needle)) {
        throw new RuntimeException('Se perdió una función del Dashboard operativo: '.$needle);
    }
}
if (!str_contains($dashboard, "sindesg_can(sindesg_permission_for(\$key, 'index', 'GET')")
    || !str_contains($dashboard, "sindesg_role_action_allowed(\$key, 'index', 'GET'")) {
    throw new RuntimeException('Las tarjetas administrativas no respetan permisos RBAC.');
}
if (!str_contains($layout, 'sindesg_permission_for($navKey')
    || !str_contains($layout, 'sindesg_role_action_allowed($navKey')
    || !str_contains($router, "elseif (\$currentModule === 'dashboard')")) {
    throw new RuntimeException('Se perdió la navegación operativa o el Dashboard.');
}

echo "[OK] Directorio de módulos exclusivo ADMIN, almacenista conserva operaciones y navegación RBAC.\n";
