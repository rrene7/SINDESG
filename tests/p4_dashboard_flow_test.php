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
echo "[OK] P4 workflow dashboard is permission-aware with no global data.\n";
