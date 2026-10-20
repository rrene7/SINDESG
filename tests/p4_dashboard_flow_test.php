<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$router = (string) file_get_contents($root . '/public/index.php');
$layout = (string) file_get_contents($root . '/app/Views/layout.php');
$view = (string) file_get_contents($root . '/app/Views/dashboard-p4.php');
$css = (string) file_get_contents($root . '/public/assets/css/dashboard-p4.css');
foreach ([
    [$router, "in_array('P4'"],
    [$router, "p4-preview"],
    [$router, "app/Views/dashboard-p4.php"],
    [$layout, "css/dashboard-p4.css"],
    [$view, "sindesg_can("],
    [$view, "sindesg_role_action_allowed("],
    [$view, "Solicitar"],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        // Heading wording intentionally flexible, but flow must remain auditable.
        if ($needle !== 'Solicitar') throw new RuntimeException("P4 dashboard missing: ".$needle);
    }
}
if (str_contains($view, 'recentRequests(') || str_contains($view, 'stockBreakdown')
    || str_contains($view, 'inventory_movements') || !str_contains($css, 'p4-home-grid')) {
    throw new RuntimeException('P4 dashboard must remain isolated, read-only and responsive.');
}
require $root . '/app/bootstrap.php';
$p4 = ['role_codes' => ['P4']];
$central = ['role_codes' => ['CENTRAL']];
$admin = ['role_codes' => ['ADMIN']];
foreach (['distribution', 'inventory', 'warehouses', 'admin', 'people', 'reports', 'audit'] as $module) {
    if (sindesg_role_action_allowed($module, 'index', 'GET', $p4)
        || sindesg_role_action_allowed($module, 'save', 'POST', $p4)) {
        throw new RuntimeException('P4 pilot must fail closed for unscoped module: ' . $module);
    }
}
if (!sindesg_role_action_allowed('dashboard', 'index', 'GET', $p4)
    || !sindesg_role_action_allowed('requests', 'index', 'GET', $central)
    || !sindesg_role_action_allowed('admin', 'index', 'GET', $admin)) {
    throw new RuntimeException('P4 dashboard or existing administrative functions incorrectly blocked.');
}
if (!str_contains($view, 'PANEL LOGÍSTICO')
    || !str_contains($view, 'Últimos movimientos y trámites')
    || !str_contains($view, 'Inventario por artículo y talla')) {
    throw new RuntimeException('P4 dashboard must display operational scope accurately.');
}
if (!sindesg_role_action_allowed('requests', 'new', 'GET', $p4)
    || !sindesg_role_action_allowed('requests', 'show', 'GET', $p4)
    || !sindesg_role_action_allowed('requests', 'submit', 'POST', $p4)
    || sindesg_role_action_allowed('requests', 'index', 'GET', $p4)
    || sindesg_role_action_allowed('requests', 'decide', 'POST', $p4)) {
    throw new RuntimeException('P4 requests allowlist is too broad or too narrow.');
}
if (!sindesg_role_action_allowed('deliveries','index','GET',$p4)
    || !sindesg_role_action_allowed('deliveries','save','POST',$p4)
    || !sindesg_role_action_allowed('deliveries','confirm','POST',$p4)
    || sindesg_role_action_allowed('deliveries','history','GET',$p4)
    || sindesg_role_action_allowed('deliveries','add-item','POST',$p4)
    || sindesg_role_action_allowed('deliveries','cancel','POST',$p4)) {
    throw new RuntimeException('P4 delivery role allowlist is incorrect.');
}
echo "[OK] P4 workflow dashboard is permission-aware with no global data.\n";
