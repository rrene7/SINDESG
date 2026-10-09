<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requestRepository = file_get_contents($root . '/app/Repositories/RequestRepository.php');
$requestController = file_get_contents($root . '/app/Controllers/RequestsController.php');
$requestView = file_get_contents($root . '/app/Views/requests/show.php');
$distributionRepository = file_get_contents($root . '/app/Repositories/DistributionRepository.php');
$distributionController = file_get_contents($root . '/app/Controllers/DistributionController.php');
$distributionView = file_get_contents($root . '/app/Views/distribution/show.php');

foreach (compact(
    'requestRepository',
    'requestController',
    'requestView',
    'distributionRepository',
    'distributionController',
    'distributionView'
) as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'public function commitmentEvidence',
    "im.reference_type = 'request'",
    "'direct_movements'",
    'quantity_approved - i.quantity_dispatched',
] as $fragment) {
    if (!str_contains($requestRepository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta control de compromiso: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'requestCommitmentEvidence',
    'Solicitud → reserva lógica → distribución',
    '0 movimientos directos',
    'Comprometido',
    'Despachado',
    'Recibido P4',
] as $fragment) {
    if (!str_contains($requestController . $requestView, $fragment)) {
        fwrite(STDERR, "[ERROR] Solicitud no muestra verificación funcional: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'public function movementEvidence',
    "distribution:%d:dispatch:central:%d",
    "distribution:%d:dispatch:transit:%d",
    "distribution:%d:receive:transit:%d",
    "distribution:%d:receive:p4:%d",
    'dispatchBalanced',
    'receiveBalanced',
    'central_stock',
    'transit_stock',
    'p4_stock',
] as $fragment) {
    if (!str_contains($distributionRepository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta evidencia contable de distribución: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'distributionVerification',
    'Central → Tránsito → P4',
    'Central OUT',
    'Tránsito IN',
    'Tránsito OUT',
    'P4 IN',
    'En tránsito verificado',
    'P4 verificado',
    'Ver artículo →',
] as $fragment) {
    if (!str_contains($distributionController . $distributionView . $distributionRepository, $fragment)) {
        fwrite(STDERR, "[ERROR] Distribución no muestra verificación funcional: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Solicitud → compromiso → distribución → P4 validado visual y contablemente." . PHP_EOL;
