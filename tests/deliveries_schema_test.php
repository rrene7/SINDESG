<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/012_individual_deliveries.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 012_individual_deliveries.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'quantity_delivered',
    'CREATE TABLE IF NOT EXISTS individual_deliveries',
    'CREATE TABLE IF NOT EXISTS individual_delivery_items',
    'recipient_employee_id',
    'delivered_by_employee_id',
    "CHECK (status IN ('draft', 'confirmed', 'cancelled'))",
    "VALUES ('012_individual_deliveries')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 9: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de despacho individual validado." . PHP_EOL;
