<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/002_catalog.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 002_catalog.sql" . PHP_EOL);
    exit(1);
}

$requiredTables = ['categories', 'sizes', 'brands', 'products', 'product_variants', 'product_images'];

foreach ($requiredTables as $table) {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s*\(/i', $schema)) {
        fwrite(STDERR, "[ERROR] Falta tabla requerida: {$table}" . PHP_EOL);
        exit(1);
    }
}

$requiredCategories = ['Suéter', 'Camisa', 'Pantalón', 'Falda', 'Camisa MNJ', 'Botas', 'Gorras', 'Kepis'];

foreach ($requiredCategories as $category) {
    if (!str_contains($schema, $category)) {
        fwrite(STDERR, "[ERROR] Falta categoría inicial: {$category}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de catálogo contiene tablas y categorías esperadas." . PHP_EOL;
