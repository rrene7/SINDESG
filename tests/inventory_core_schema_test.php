<?php
declare(strict_types=1);

$schemaPath = dirname(__DIR__) . '/database/schema/003_inventory_core.sql';
$schema = file_get_contents($schemaPath);

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 003_inventory_core.sql" . PHP_EOL);
    exit(1);
}

$requiredFragments = [
    'CREATE TABLE IF NOT EXISTS warehouses',
    'CREATE TABLE IF NOT EXISTS inventory_movements',
    "VALUES ('CENTRAL-SG', 'Almacén Central de Servicios Generales', 'CENTRAL'",
    'CREATE OR REPLACE VIEW v_inventory_stock',
    'CREATE OR REPLACE VIEW v_inventory_product_stock',
    "CHECK (direction IN ('IN', 'OUT'))",
    'CHECK (quantity > 0)',
    "VALUES ('003_inventory_core')",
];

foreach ($requiredFragments as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en esquema: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

$forbiddenFragments = [
    'UPDATE rrhh2029.',
    'DELETE FROM rrhh2029.',
    'INSERT INTO rrhh2029.',
];

foreach ($forbiddenFragments as $fragment) {
    if (stripos($schema, $fragment) !== false) {
        fwrite(STDERR, "[ERROR] El esquema intenta escribir sobre RRHH2029." . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de inventario Fase 2B validado." . PHP_EOL;
