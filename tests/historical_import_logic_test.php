<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/HistoricalImportService.php');
$repository = file_get_contents($root . '/app/Repositories/HistoricalImportRepository.php');
$controller = file_get_contents($root . '/app/Controllers/HistoricalImportController.php');
$router = file_get_contents($root . '/public/index.php');
$bootstrap = file_get_contents($root . '/app/bootstrap.php');

foreach (compact('service','repository','controller','router','bootstrap') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "hash_file('sha256'",
    'inventory_normalized_2026',
    'Código no encontrado en el catálogo actual',
    'Código ambiguo',
    'Inventario recalculado no numérico',
    'Perfil de hoja no reconocido',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Lógica de staging incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'historical_stock_snapshots',
    'row_status = "imported"',
    'matchVariantsByOfficialCode',
    'archiveReadySnapshots',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Repositorio histórico incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($repository, 'INSERT INTO inventory_movements') || str_contains($service, 'inventory_movements')) {
    fwrite(STDERR, "[ERROR] La migración histórica no debe insertar movimientos vivos." . PHP_EOL);
    exit(1);
}

if (!str_contains($router, 'new HistoricalImportController')) {
    fwrite(STDERR, "[ERROR] El centro de históricos no está conectado al router." . PHP_EOL);
    exit(1);
}

foreach (["'migration' => 'migration.view'", "'migration' => 'migration.manage'"] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta permiso histórico: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Migración histórica controlada validada." . PHP_EOL;
