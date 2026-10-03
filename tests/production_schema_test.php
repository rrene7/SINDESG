<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/006_production.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 006_production.sql" . PHP_EOL);
    exit(1);
}

$required = [
    'CREATE TABLE IF NOT EXISTS production_orders',
    'CREATE TABLE IF NOT EXISTS production_outputs',
    'CREATE TABLE IF NOT EXISTS production_inputs',
    'UNIQUE KEY uq_production_orders_code',
    'UNIQUE KEY uq_production_orders_lot',
    'UNIQUE KEY uq_production_outputs_variant',
    'UNIQUE KEY uq_production_inputs_variant',
    "CHECK (status IN ('draft', 'confirmed', 'cancelled'))",
    'CHECK (quantity > 0)',
    "VALUES ('006_production')",
];

foreach ($required as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 4: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de producción interna validado." . PHP_EOL;
