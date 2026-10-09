<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/HistoricalImportRepository.php');
$service = file_get_contents($root . '/app/Services/HistoricalImportService.php');
$controller = file_get_contents($root . '/app/Controllers/HistoricalImportController.php');
$view = file_get_contents($root . '/app/Views/migration/show.php');

foreach (compact('repository','service','controller','view') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 13D." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'referenceReviewSummary',
    'finalizeHistoricalReferences',
    'SET row_status = "skipped"',
    'SET status = "imported"',
    'Primero archive todas las filas del Control de inventario',
    "r.row_status = 'skipped'",
    'AS skipped_rows',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Repositorio 13D incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'finalizeReferences',
    'historical_batch_closed',
    'reference_profiles',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Servicio 13D incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($controller, '$action === \'finalize-references\'')) {
    fwrite(STDERR, "[ERROR] El controlador no expone cierre 13D." . PHP_EOL);
    exit(1);
}

foreach ([
    'CIERRE CONTROLADO · FASE 13D',
    'Cerrar lote histórico',
    'Conservar trazabilidad; excluir de snapshot y kardex',
    'Contenido del lote archivado',
    'Detalle histórico del lote',
    'Referencias conservadas',
    'Referencia significa conservada para consulta',
    "rowStatusLabels['skipped'] = 'Referencia'",
    "Observación",
    'Conservada como referencia histórica; no afecta stock ni kardex.',
] as $fragment) {
    if (!str_contains($view, $fragment)) {
        fwrite(STDERR, "[ERROR] Vista 13D incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([$repository,$service,$controller] as $content) {
    if (str_contains($content, 'INSERT INTO inventory_movements')) {
        fwrite(STDERR, "[ERROR] Fase 13D intenta crear movimientos vivos." . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Fase 13D de cierre histórico validada." . PHP_EOL;
