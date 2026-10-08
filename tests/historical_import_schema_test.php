<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/016_historical_imports.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 016_historical_imports.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'CREATE TABLE IF NOT EXISTS historical_import_batches',
    'CREATE TABLE IF NOT EXISTS historical_import_sheets',
    'CREATE TABLE IF NOT EXISTS historical_import_rows',
    'CREATE TABLE IF NOT EXISTS historical_stock_snapshots',
    'file_sha256',
    'source_row_number',
    'raw_json',
    'issues_json',
    "'migration.view'",
    "'migration.manage'",
    "VALUES ('016_historical_imports')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Esquema histórico incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($schema, 'inventory_movements')) {
    fwrite(STDERR, "[ERROR] Fase 13A no debe modificar el kardex vivo." . PHP_EOL);
    exit(1);
}

echo "[OK] Esquema de staging histórico validado." . PHP_EOL;
