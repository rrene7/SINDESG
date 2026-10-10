<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/scripts/audit_historical_data.php');
if ($source === false) {
    fwrite(STDERR, "[ERROR] Falta auditoría local de históricos." . PHP_EOL);
    exit(1);
}

foreach ([
    "PHP_SAPI !== 'cli'",
    "connection('sindesg')",
    'historical_import_batches',
    'historical_import_rows',
    'historical_import_sheets',
    'historical_stock_snapshots',
    'inventory_movements',
    'v_inventory_stock',
    'individual_deliveries',
    'ready',
    'review',
    'imported',
    'skipped',
    'NO son existencias operativas',
] as $required) {
    if (!str_contains($source, $required)) {
        fwrite(STDERR, "[ERROR] Falta auditoría histórica: {$required}" . PHP_EOL);
        exit(1);
    }
}

if (preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+\w+|DELETE\s+FROM|TRUNCATE\s+TABLE|DROP\s+TABLE|ALTER\s+TABLE|REPLACE\s+INTO)\b/i', $source) === 1) {
    fwrite(STDERR, "[ERROR] Auditoría contiene sentencias de escritura SQL." . PHP_EOL);
    exit(1);
}
if (str_contains($source, "connection('rrhh')") || str_contains($source, 'rrhhRead(')) {
    fwrite(STDERR, "[ERROR] Auditoría no debe conectarse a RRHH2029." . PHP_EOL);
    exit(1);
}

echo "[OK] Auditoría de históricos de solo lectura validada." . PHP_EOL;
