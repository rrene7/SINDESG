<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/010_distribution.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 010_distribution.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'quantity_dispatched',
    'quantity_received',
    "'TRANSIT-SG'",
    'CREATE TABLE IF NOT EXISTS distributions',
    'CREATE TABLE IF NOT EXISTS distribution_items',
    "'prepared', 'authorized', 'dispatched', 'received', 'cancelled'",
    "VALUES ('010_distribution')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 8: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de Distribución validado." . PHP_EOL;
