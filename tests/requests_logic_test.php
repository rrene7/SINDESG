<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/RequestRepository.php');
$service = file_get_contents($root . '/app/Services/RequestService.php');
$controller = file_get_contents($root . '/app/Controllers/RequestsController.php');
$router = file_get_contents($root . '/public/index.php');

foreach (compact('repository', 'service', 'controller', 'router') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "sprintf('SOL-%06d'",
    'hasDuplicateItem',
    'startReview',
    'quantity_approved',
    "'approved'",
    "'partially_approved'",
    "'rejected'",
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta lógica de solicitud: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['findEligibleEmployee', 'p4IsActive', 'variantIsActive', 'beneficiary_employee_id'] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta validación de solicitud: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new RequestsController')) {
    fwrite(STDERR, "[ERROR] Solicitudes no está conectado al router." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica de Solicitudes validada." . PHP_EOL;
