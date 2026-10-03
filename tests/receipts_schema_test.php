<?php
declare(strict_types=1);

$schemaPath = dirname(__DIR__) . '/database/schema/005_receipts.sql';
$schema = file_get_contents($schemaPath);

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 005_receipts.sql" . PHP_EOL);
    exit(1);
}

$required = [
    'CREATE TABLE IF NOT EXISTS receipts',
    'CREATE TABLE IF NOT EXISTS lots',
    'CREATE TABLE IF NOT EXISTS receipt_items',
    'UNIQUE KEY uq_receipts_code',
    'UNIQUE KEY uq_lots_code',
    'UNIQUE KEY uq_receipt_items_variant',
    "CHECK (status IN ('draft', 'confirmed', 'cancelled'))",
    "CHECK (source_type IN ('purchase', 'donation', 'transfer', 'return', 'other'))",
    'CHECK (quantity > 0)',
    "VALUES ('005_receipts')",
];

foreach ($required as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 3: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de recepción de lotes validado." . PHP_EOL;
