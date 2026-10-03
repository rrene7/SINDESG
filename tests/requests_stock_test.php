<?php
declare(strict_types=1);

$repository = file_get_contents(dirname(__DIR__) . '/app/Repositories/RequestRepository.php');

if ($repository === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer RequestRepository.php" . PHP_EOL);
    exit(1);
}

foreach ([
    'availableToApprove',
    "r.status IN ('approved', 'partially_approved')",
    'r.id <> :exclude_request_id',
    'v_inventory_stock',
    "w.code = 'CENTRAL-SG'",
    'Stock comprometible insuficiente',
    '$remainingByVariant',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta reserva/validación de stock: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($repository, "movement_kind = 'request'")) {
    fwrite(STDERR, "[ERROR] Aprobar una solicitud no debe mover inventario." . PHP_EOL);
    exit(1);
}

echo "[OK] Validación y reserva lógica de stock de Solicitudes validada." . PHP_EOL;
