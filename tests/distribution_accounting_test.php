<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DistributionRepository.php');
$requestRepository = file_get_contents($root . '/app/Repositories/RequestRepository.php');

if ($repository === false || $requestRepository === false) {
    fwrite(STDERR, "[ERROR] No se pudieron leer repositorios." . PHP_EOL);
    exit(1);
}

foreach ([
    "'transfer_dispatch'",
    "'transfer_in_transit'",
    "'transfer_receive'",
    "'OUT'",
    "'IN'",
    "distribution:%d:dispatch:central:%d",
    "distribution:%d:dispatch:transit:%d",
    "distribution:%d:receive:transit:%d",
    "distribution:%d:receive:p4:%d",
    "ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)",
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla contable/idempotente: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($requestRepository, 'quantity_approved - i.quantity_dispatched')) {
    fwrite(STDERR, "[ERROR] La reserva de solicitudes no descuenta lo ya despachado." . PHP_EOL);
    exit(1);
}

echo "[OK] Contabilidad Central -> Tránsito -> P4 validada." . PHP_EOL;
