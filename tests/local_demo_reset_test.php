<?php
declare(strict_types=1);

$script = file_get_contents(dirname(__DIR__) . '/scripts/reset_local_demo.php');
if (!is_string($script)) {
    fwrite(STDERR, "[ERROR] Falta script de mantenimiento local." . PHP_EOL);
    exit(1);
}

foreach ([
    "PHP_SAPI !== 'cli'",
    "(\$app['environment'] ?? '') === 'local'",
    "127.0.0.1",
    "RESET-FICTICIOS-LOCAL",
    "--backup-path=",
    "BACKUP_COMPLETE",
    "manifest.json",
    "hash_file('sha256'",
    "beginTransaction()",
    "rollBack()",
    "commit()",
    "historical_import_batches",
    "historical_import_rows",
    "historical_stock_snapshots",
    "inventory_movements",
    "individual_deliveries",
    "audit_logs",
    "DEMO_TRANSACTION_TABLES",
    "expectedDemo",
    "if (!\$apply)",
    "DELETE FROM ",
] as $check) {
    if (!str_contains($script, $check)) {
        fwrite(STDERR, "[ERROR] Falta protección: {$check}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($script, 'SET FOREIGN_KEY_CHECKS=0')
    || str_contains($script, "connection('rrhh')")
    || str_contains($script, 'rrhhRead(')) {
    fwrite(STDERR, "[ERROR] El reinicio no debe evitar FKs ni acceder a RRHH." . PHP_EOL);
    exit(1);
}

// Solo tablas transaccionales explícitamente enumeradas.
$allowed = [
    'inventory_movements',
    'inventory_adjustments',
    'individual_delivery_items',
    'individual_deliveries',
    'distribution_items',
    'distributions',
    'service_request_items',
    'service_requests',
    'production_inputs',
    'production_outputs',
    'production_orders',
    'receipt_items',
    'lots',
    'receipts',
];
preg_match('/const DEMO_TRANSACTION_TABLES\s*=\s*\[(.*?)\];/s', $script, $match);
if (!isset($match[1])) {
    fwrite(STDERR, "[ERROR] Falta lista explícita de tablas autorizadas." . PHP_EOL);
    exit(1);
}
preg_match_all("/'([a-z_]+)'/", $match[1], $hits);
if ($hits[1] !== $allowed) {
    fwrite(STDERR, "[ERROR] Lista de tablas de reinicio modificada sin revisión." . PHP_EOL);
    exit(1);
}
if (preg_match('/\b(?:TRUNCATE|DROP\s+TABLE|ALTER\s+TABLE)\b/i', $script)) {
    fwrite(STDERR, "[ERROR] Reinicio intenta modificar el esquema." . PHP_EOL);
    exit(1);
}

echo "[OK] Reinicio local protegido y limitado a transacciones demo." . PHP_EOL;
