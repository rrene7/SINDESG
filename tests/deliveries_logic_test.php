<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DeliveryRepository.php');
$service = file_get_contents($root . '/app/Services/DeliveryService.php');
$controller = file_get_contents($root . '/app/Controllers/DeliveryController.php');
$router = file_get_contents($root . '/public/index.php');
$view = file_get_contents($root . '/app/Views/deliveries/show.php');
$requestView = file_get_contents($root . '/app/Views/requests/show.php');
$rrhhDirectory = file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');

foreach (compact('repository', 'service', 'controller', 'router', 'view', 'requestView', 'rrhhDirectory') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "sprintf('DSP-%06d'",
    'pendingNominal',
    'historyForEmployee',
    'hasDuplicateVariant',
    'quantity_delivered',
    'manualAvailable',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta lógica de despacho: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['findEligibleEmployee', 'belongsToOrganizationUnit', 'recipient_employee_id', 'delivered_by_employee_id', 'linked_quantity', 'p4OrganizationUnitId', 'scopeOverrideEnabled'] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta validación RRHH: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new DeliveryController')) {
    fwrite(STDERR, "[ERROR] Despacho individual no está conectado al router." . PHP_EOL);
    exit(1);
}

foreach (['Imprimir comprobante', 'Historial del funcionario', 'Confirmar despacho'] as $fragment) {
    if (!str_contains($view, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta interfaz de cierre: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($requestView, 'Entregado funcionario') || !str_contains($requestView, 'request_item_id=')) {
    fwrite(STDERR, "[ERROR] La solicitud no expone el cierre P4 -> funcionario." . PHP_EOL);
    exit(1);
}

if (!str_contains($rrhhDirectory, 'belongsToOrganizationUnit')) {
    fwrite(STDERR, "[ERROR] Falta validar pertenencia territorial del receptor al P4." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica de despacho individual validada." . PHP_EOL;
