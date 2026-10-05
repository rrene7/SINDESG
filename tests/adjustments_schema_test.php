<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/014_inventory_adjustments.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 014_inventory_adjustments.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'CREATE TABLE IF NOT EXISTS inventory_adjustments',
    'source_delivery_item_id',
    'replacement_variant_id',
    'counted_quantity',
    'primary_stock_before',
    'primary_stock_after',
    'replacement_stock_before',
    'replacement_stock_after',
    "'cycle_count'",
    "VALUES ('014_inventory_adjustments')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 10: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de ajustes validado." . PHP_EOL;
