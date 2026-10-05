<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/AdjustmentRepository.php');
$service = file_get_contents($root . '/app/Services/AdjustmentService.php');
$controller = file_get_contents($root . '/app/Controllers/AdjustmentController.php');
$router = file_get_contents($root . '/public/index.php');
$deliveryView = file_get_contents($root . '/app/Views/deliveries/show.php');
$form = file_get_contents($root . '/app/Views/adjustments/form.php');

foreach (compact('repository','service','controller','router','deliveryView','form') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "sprintf('AJU-%06d'",
    'sourceDeliveryItem',
    'returnableQuantityForUpdate',
    'variantsForProduct',
    'cycle_count',
    'replacement',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta lógica Fase 10: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'findEligibleEmployee',
    'writeoff',
    'authorized_adjustment',
    'cycle_count',
    'return_condition',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta validación del servicio: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new AdjustmentController')) {
    fwrite(STDERR, "[ERROR] Ajustes no está conectado al router." . PHP_EOL);
    exit(1);
}

if (!str_contains($deliveryView, 'Devolver / cambiar')) {
    fwrite(STDERR, "[ERROR] La entrega confirmada no enlaza con Fase 10." . PHP_EOL);
    exit(1);
}

foreach (['Conteo cíclico', 'Baja autorizada', 'Funcionario responsable'] as $fragment) {
    if (!str_contains($form, $fragment)) {
        fwrite(STDERR, "[ERROR] Formulario incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Lógica de ajustes validada." . PHP_EOL;
