<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ReportsController.php');
$print = file_get_contents($root . '/app/Views/reports/print.php');
$css = file_get_contents($root . '/public/assets/css/reports.css');

foreach (compact('controller','print','css') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['application/vnd.ms-excel', '.xls', 'Content-Disposition', 'excelCell'] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        fwrite(STDERR, "[ERROR] Exportación Excel incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['window.print()', 'Guardar PDF', 'report-print-table'] as $fragment) {
    if (!str_contains($print, $fragment)) {
        fwrite(STDERR, "[ERROR] Vista PDF/impresión incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($css, '@media print')) {
    fwrite(STDERR, "[ERROR] Falta estilo de impresión." . PHP_EOL);
    exit(1);
}

echo "[OK] Exportación Excel y PDF/impresión validada." . PHP_EOL;
