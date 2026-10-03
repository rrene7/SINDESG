<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/WarehouseRepository.php');
$service = file_get_contents($root . '/app/Services/WarehouseService.php');
$controller = file_get_contents($root . '/app/Controllers/WarehousesController.php');
$router = file_get_contents($root . '/public/index.php');

foreach (compact('repository', 'service', 'controller', 'router') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "sprintf('P4-%06d'",
    "'P4'",
    'existsForOrganizationUnit',
    'v_inventory_stock',
    'El Almacén Central no puede inactivarse',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta lógica P4: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['findUnit', 'findEligibleEmployee', 'organization_unit_id', 'responsible_employee_id'] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta validación de P4: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new WarehousesController')) {
    fwrite(STDERR, "[ERROR] Almacenes y P4 no está conectado al router." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica de Almacenes y P4 validada." . PHP_EOL;
