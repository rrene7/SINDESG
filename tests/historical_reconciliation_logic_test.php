<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/database/schema/018_historical_reconciliation.sql');
$seedRaw = file_get_contents($root . '/database/seeds/historical_uniform_reconciliation_2026.json');
$repository = file_get_contents($root . '/app/Repositories/HistoricalImportRepository.php');
$service = file_get_contents($root . '/app/Services/HistoricalImportService.php');
$controller = file_get_contents($root . '/app/Controllers/HistoricalImportController.php');
$view = file_get_contents($root . '/app/Views/migration/show.php');

foreach (compact('schema', 'seedRaw', 'repository', 'service', 'controller', 'view') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 13C." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'CREATE TABLE IF NOT EXISTS historical_import_resolutions',
    "'normalized_source'",
    "'0.13.0-c'",
    "VALUES ('018_historical_reconciliation')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Esquema 13C incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($schema, 'inventory_movements')) {
    fwrite(STDERR, "[ERROR] Fase 13C no debe tocar kardex." . PHP_EOL);
    exit(1);
}

$seed = json_decode($seedRaw, true);
if (!is_array($seed) || count($seed['reconciliations'] ?? []) !== 1) {
    fwrite(STDERR, "[ERROR] La referencia de conciliación 13C no es válida." . PHP_EOL);
    exit(1);
}

$item = $seed['reconciliations'][0];
$expected = [
    'official_code' => '999015TL38',
    'initial_quantity' => 493,
    'entries_quantity' => 3,
    'exits_quantity' => 255,
    'recalculated_quantity' => 241,
];

foreach ($expected as $key => $value) {
    if (($item[$key] ?? null) != $value) {
        fwrite(STDERR, "[ERROR] Referencia 13C incorrecta para {$key}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'historicalResolutionSchemaReady',
    'controlDiscrepancyRows',
    'resolveControlRow',
    'historical_import_resolutions',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Repositorio 13C incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'reconciliationPreview',
    'reconcileControlRow',
    'historical_uniform_reconciliation_2026.json',
    'historical_control_reconciled',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Servicio 13C incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($controller, "$action === 'reconcile-control'")) {
    fwrite(STDERR, "[ERROR] El controlador no expone conciliación 13C." . PHP_EOL);
    exit(1);
}

if (!str_contains($view, 'Conciliar incidencia')) {
    fwrite(STDERR, "[ERROR] La vista no expone la conciliación 13C." . PHP_EOL);
    exit(1);
}

foreach ([$repository, $service, $controller] as $content) {
    if (str_contains($content, 'INSERT INTO inventory_movements')) {
        fwrite(STDERR, "[ERROR] Fase 13C intenta crear movimientos vivos." . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Fase 13C de conciliación histórica validada." . PHP_EOL;
