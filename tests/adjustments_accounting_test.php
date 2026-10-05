<?php
declare(strict_types=1);

$repository = file_get_contents(dirname(__DIR__) . '/app/Repositories/AdjustmentRepository.php');

if ($repository === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer AdjustmentRepository.php" . PHP_EOL);
    exit(1);
}

foreach ([
    "'inventory_adjustment'",
    'inventory-adjustment:%d:%s',
    'ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)',
    "'return_reusable'",
    "'exchange_return'",
    "'exchange_replacement'",
    "'replacement'",
    "'surplus'",
    "'authorized_adjustment'",
    "'cycle_count'",
    'physicalStockForUpdate',
    'La salida dejaría el inventario en negativo',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Regla contable ausente: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($repository, "return_condition'] === 'reusable'")) {
    fwrite(STDERR, "[ERROR] La devolución no distingue reutilizable/no reutilizable." . PHP_EOL);
    exit(1);
}

echo "[OK] Contabilidad de ajustes validada." . PHP_EOL;
