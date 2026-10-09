<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use App\Services\HistoricalImportService;

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
    'inventory_control_2026',
    'historical_entries_2024_2026',
    'historical_exits_2024_2026',
    'Cantidad almacenada como texto en el archivo fuente',
    'Totales de salidas no coinciden con SALIDAS',
    'historical_batch_reprocessed',
    'resetStaging',
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
    'rowsForProfile',
    'movementTotalsByCode',
    'markRowForReview',
    'resetStaging',
    "IN ('inventory_normalized_2026', 'inventory_control_2026')",
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

if (!str_contains($controller, "$action === 'reprocess'")) {
    fwrite(STDERR, "[ERROR] El lote histórico existente no puede reprocesarse de forma segura." . PHP_EOL);
    exit(1);
}

foreach (["'migration' => 'migration.view'", "'migration' => 'migration.manage'"] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta permiso histórico: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

$reflection = new ReflectionClass(HistoricalImportService::class);
$serviceObject = $reflection->newInstanceWithoutConstructor();
$detectProfile = $reflection->getMethod('detectProfile');
$detectProfile->setAccessible(true);

$controlRows = [
    ['REPUBLICA DE PANAMA'],
    ['ODC','CÓDIGO ISTMO','SHORT CODE','NOMBRE','TIPO','INVENTARIO INICIAL','ENTRADAS','SALIDAS','INVENTARIO','Conteo Ciclico','DIF'],
];
[$profile] = $detectProfile->invoke($serviceObject, 'CONTROL DE INVENTARIO', $controlRows);
if ($profile !== 'inventory_control_2026') {
    fwrite(STDERR, "[ERROR] No se reconoció CONTROL DE INVENTARIO real." . PHP_EOL);
    exit(1);
}

$entryRows = [
    ['ENTRADAS'],
    ['FECHA','ODC','DEPENDENCIA','CODIGO ISTMO','CODIGO CORTO E','PRODUCTO','CANTIDAD'],
];
[$profile] = $detectProfile->invoke($serviceObject, 'ENTRADAS', $entryRows);
if ($profile !== 'historical_entries_2024_2026') {
    fwrite(STDERR, "[ERROR] No se reconoció la hoja ENTRADAS real." . PHP_EOL);
    exit(1);
}

$exitRows = [
    ['SALIDAS'],
    ['08/29/2025','DEPENDENCIA','FECHA','CODIGO ISTMO','CODIGO CORTO ','PRODUCTO','CANTIDAD'],
];
[$profile] = $detectProfile->invoke($serviceObject, 'SALIDAS', $exitRows);
if ($profile !== 'historical_exits_2024_2026') {
    fwrite(STDERR, "[ERROR] No se reconoció la hoja SALIDAS real." . PHP_EOL);
    exit(1);
}

[$profile] = $detectProfile->invoke(
    $serviceObject,
    'rendicion 2025 (2)',
    [['ALMACÉN REGIONAL DE GAMBOA'], ['RENDICIÓN DE 01-05-2025 AL 07-05-2025']]
);
if ($profile !== 'rendition_report') {
    fwrite(STDERR, "[ERROR] No se reconoció una rendición histórica." . PHP_EOL);
    exit(1);
}

[$profile] = $detectProfile->invoke(
    $serviceObject,
    'Hoja3',
    [[null, 34], [null, 38]]
);
if ($profile !== 'unclassified') {
    fwrite(STDERR, "[ERROR] Una hoja sin estructura fue clasificada de forma insegura." . PHP_EOL);
    exit(1);
}

echo "[OK] Migración histórica controlada validada." . PHP_EOL;
