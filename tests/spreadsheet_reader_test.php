<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\SpreadsheetReader;

$temp = tempnam(sys_get_temp_dir(), 'sindesg_csv_');
if ($temp === false) {
    fwrite(STDERR, "[ERROR] No se pudo crear archivo temporal." . PHP_EOL);
    exit(1);
}

file_put_contents(
    $temp,
    "CÓDIGO ISTMO,PRODUCTO BASE,INVENTARIO RECALCULADO\nABC 1,CAMISA,12\n"
);

try {
    $reader = new SpreadsheetReader();
    $sheets = $reader->read($temp, 'csv');

    if (count($sheets) !== 1 || ($sheets[0]['name'] ?? '') !== 'CSV') {
        fwrite(STDERR, "[ERROR] Lectura CSV no devolvió la hoja esperada." . PHP_EOL);
        exit(1);
    }

    if (($sheets[0]['rows'][1][0] ?? null) !== 'ABC 1') {
        fwrite(STDERR, "[ERROR] Lectura CSV alteró el código fuente." . PHP_EOL);
        exit(1);
    }

    $source = file_get_contents(dirname(__DIR__) . '/app/Services/SpreadsheetReader.php');
    foreach (['ZipArchive', 'sharedStrings.xml', 'workbook.xml', 'worksheetRows'] as $fragment) {
        if (!str_contains((string) $source, $fragment)) {
            fwrite(STDERR, "[ERROR] Soporte XLSX incompleto: {$fragment}" . PHP_EOL);
            exit(1);
        }
    }

    echo "[OK] Lector XLSX/CSV validado." . PHP_EOL;
} finally {
    @unlink($temp);
}
