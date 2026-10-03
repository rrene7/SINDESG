<?php
declare(strict_types=1);

$repository = file_get_contents(dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php');

if ($repository === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer DeliveryRepository.php" . PHP_EOL);
    exit(1);
}

foreach ([
    "'individual_delivery'",
    "'OUT'",
    "individual-delivery:%d:item:%d",
    'ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)',
    'physicalStockForUpdate',
    'nominalReserved',
    'manualAvailableForUpdate',
    'quantity_received - i.quantity_delivered',
    'quantity_delivered = quantity_delivered +',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla contable o de reserva: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($repository, "'IN',\n                        :quantity")) {
    fwrite(STDERR, "[ERROR] La entrega final no debe generar entrada en otro almacén." . PHP_EOL);
    exit(1);
}

echo "[OK] Contabilidad P4 -> funcionario validada." . PHP_EOL;
