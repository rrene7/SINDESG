<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schemaPath = $root . '/database/schema/017_historical_catalog_preparation.sql';
$seedPath = $root . '/database/seeds/historical_uniform_catalog_2026.json';

$schema = file_get_contents($schemaPath);
$seedRaw = file_get_contents($seedPath);
$repository = file_get_contents($root . '/app/Repositories/HistoricalImportRepository.php');
$service = file_get_contents($root . '/app/Services/HistoricalImportService.php');
$controller = file_get_contents($root . '/app/Controllers/HistoricalImportController.php');
$view = file_get_contents($root . '/app/Views/migration/show.php');

foreach (compact('schema', 'seedRaw', 'repository', 'service', 'controller', 'view') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 13B." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'CREATE TABLE IF NOT EXISTS historical_catalog_mappings',
    "entity_type IN ('category', 'subcategory', 'product', 'size', 'variant')",
    "VALUES ('017_historical_catalog_preparation')",
    "'0.13.0-b'",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Esquema 13B incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($schema, 'inventory_movements')) {
    fwrite(STDERR, "[ERROR] Fase 13B no debe crear movimientos de kardex." . PHP_EOL);
    exit(1);
}

$seed = json_decode($seedRaw, true);
if (!is_array($seed)) {
    fwrite(STDERR, "[ERROR] El catálogo normalizado 2026 no contiene JSON válido." . PHP_EOL);
    exit(1);
}

$expected = [
    'categories' => 5,
    'subcategories' => 16,
    'products' => 24,
    'sizes' => 41,
    'variants' => 182,
];

foreach ($expected as $key => $count) {
    if (!isset($seed[$key]) || !is_array($seed[$key]) || count($seed[$key]) !== $count) {
        fwrite(
            STDERR,
            "[ERROR] Conteo 13B incorrecto para {$key}; se esperaban {$count}." . PHP_EOL
        );
        exit(1);
    }
}

$variantIds = [];
$officialCodes = [];

foreach ($seed['variants'] as $variant) {
    $id = trim((string) ($variant['id'] ?? ''));
    $code = trim((string) ($variant['code'] ?? ''));
    $normalized = mb_strtoupper(str_replace(' ', '', $code), 'UTF-8');

    if ($id === '' || $normalized === '') {
        fwrite(STDERR, "[ERROR] Variante 13B sin ID o código ISTMO." . PHP_EOL);
        exit(1);
    }

    if (isset($variantIds[$id])) {
        fwrite(STDERR, "[ERROR] ID de variante repetido: {$id}" . PHP_EOL);
        exit(1);
    }

    if (isset($officialCodes[$normalized])) {
        fwrite(STDERR, "[ERROR] Código ISTMO repetido: {$code}" . PHP_EOL);
        exit(1);
    }

    foreach (['inventory', 'quantity', 'initial_quantity', 'entries', 'exits', 'stock'] as $forbidden) {
        if (array_key_exists($forbidden, $variant)) {
            fwrite(
                STDERR,
                "[ERROR] La semilla 13B contiene cantidad/stock prohibido: {$forbidden}" . PHP_EOL
            );
            exit(1);
        }
    }

    $variantIds[$id] = true;
    $officialCodes[$normalized] = true;
}

foreach ([
    'prepareHistoricalCatalog',
    'historicalCatalogStatus',
    'historical_catalog_mappings',
    'institutional_code',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Repositorio 13B incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'prepareCatalog',
    'historical_uniform_catalog_2026.json',
    'historical_catalog_prepared',
    "'variants' => 182",
    'live_inventory_changed',
    'kardex_changed',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Servicio 13B incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($controller, '$action === \'prepare-catalog\'')) {
    fwrite(STDERR, "[ERROR] El controlador no expone preparar catálogo 13B." . PHP_EOL);
    exit(1);
}

if (!str_contains($view, 'Preparar catálogo 13B')) {
    fwrite(STDERR, "[ERROR] La vista no ofrece el control de Fase 13B." . PHP_EOL);
    exit(1);
}

if (
    str_contains($repository, 'INSERT INTO inventory_movements')
    || str_contains($service, 'inventory_movements')
) {
    fwrite(STDERR, "[ERROR] Fase 13B no debe insertar movimientos vivos." . PHP_EOL);
    exit(1);
}

echo "[OK] Fase 13B de catálogo histórico validada." . PHP_EOL;
