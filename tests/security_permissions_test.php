<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$bootstrap = file_get_contents($root . '/app/bootstrap.php');
$router = file_get_contents($root . '/public/index.php');
$layout = file_get_contents($root . '/app/Views/layout.php');
$employeesApi = file_get_contents($root . '/public/api/employees.php');
$unitsApi = file_get_contents($root . '/public/api/units.php');

foreach (compact('bootstrap','router','layout','employeesApi','unitsApi') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "'inventory.view'",
    "'inventory.manage'",
    "'reports.view'",
    "'audit.view'",
    "'admin.manage'",
    'sindesg_permission_for',
] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) {
        fwrite(STDERR, "[ERROR] Mapeo de permisos incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['$requiredPermission', 'Acceso denegado', 'registerRequestAudit'] as $fragment) {
    if (!str_contains($router, $fragment)) {
        fwrite(STDERR, "[ERROR] Enrutamiento seguro incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($layout, 'sindesg_can') || !str_contains($layout, 'logout-button')) {
    fwrite(STDERR, "[ERROR] Menú o cierre de sesión no respeta seguridad." . PHP_EOL);
    exit(1);
}

if (!str_contains($employeesApi, 'sindesg_api_authorized(true)') || !str_contains($unitsApi, 'sindesg_api_authorized(false)')) {
    fwrite(STDERR, "[ERROR] APIs internas no están protegidas." . PHP_EOL);
    exit(1);
}

echo "[OK] Permisos y protección de rutas validados." . PHP_EOL;
