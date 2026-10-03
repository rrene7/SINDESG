<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DistributionRepository.php');
$service = file_get_contents($root . '/app/Services/DistributionService.php');
$router = file_get_contents($root . '/public/index.php');

foreach (compact('repository', 'service', 'router') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "sprintf('TRF-%06d'",
    'hasOpenDistribution',
    'authorize(',
    'dispatch(',
    'receive(',
    'quantity_dispatched = quantity_dispatched +',
    'quantity_received = quantity_received +',
    'status = "fulfilled"',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta lógica de distribución: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['prepared_by_employee_id', 'authorized_by_employee_id', 'delivered_by_employee_id', 'received_by_employee_id'] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta responsable RRHH: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new DistributionController')) {
    fwrite(STDERR, "[ERROR] Distribución no está conectada al router." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica de Distribución validada." . PHP_EOL;
