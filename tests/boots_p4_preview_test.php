<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\BootsP4WorkbookPreview;
use App\Services\SpreadsheetReader;

$temp = tempnam(sys_get_temp_dir(), 'sindesg_boots_');
if ($temp === false) {
    throw new RuntimeException('No se pudo crear archivo de prueba.');
}

$csv = implode("\n", [
    '3 ZONA DE POLICIA DE COLON,,,,,,',
    'CONTRATO DA-038-2025,,,,,,',
    'REQUISICION Nº 1122965,,,,,,',
    'CANTIDAD DE UNIDADES A DOTAR: 2,,,,,,',
    'N°,RANGO,POSICIÓN,NOMBRE,APELLIDO,TALLA,FIRMA',
    '1,AGENTE,11828,PERSONA,PRUEBA,8.5,',
    '2,AGENTE,11828,PERSONA,PRUEBA,8,',
    '3,AGENTE,12345,OTRA,PRUEBA,8 5,',
    ',,,,,,',
    ',,TALLAS,CANTIDADES,,,',
    ',,8,1,,,',
    ',,8.5,2,,,',
    ',,TOTAL,3,,,',
    '',
]);
file_put_contents($temp, $csv);
try {
    $preview = new BootsP4WorkbookPreview();
    $sheets = (new SpreadsheetReader())->read($temp, 'csv');
    $result = $preview->analyze($sheets, 'piloto.csv');
    if ($result['nominal_count'] !== 3 || $result['validated_size_count'] !== 2) {
        throw new RuntimeException('No se contó correctamente el listado nominal.');
    }
    if ($result['document']['requisition'] !== '1122965'
        || $result['document']['contract'] !== 'DA-038-2025') {
        throw new RuntimeException('Falta contrato o requisición.');
    }
    $issues = array_column($result['issues'], 'type');
    foreach (['duplicate', 'size', 'quantity', 'size_summary'] as $type) {
        if (!in_array($type, $issues, true)) {
            throw new RuntimeException('No se reportó la incidencia: ' . $type);
        }
    }
    if ($result['status'] !== 'preview_only' || $result['summary_total'] !== 3) {
        throw new RuntimeException('No es una vista previa válida.');
    }
    $source = file_get_contents(dirname(__DIR__) . '/app/Services/BootsP4WorkbookPreview.php');
    if (!is_string($source) || preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+\w+|DELETE\s+FROM|TRUNCATE|DROP\s+TABLE)\b/i', $source)) {
        throw new RuntimeException('El parser de vista previa no debe modificar datos.');
    }
    $controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/RequestsController.php');
    foreach (['import-boots', 'preview-boots', 'boots_workbook', 'UPLOAD_ERR_OK'] as $token) {
        if (!str_contains($controller, $token)) {
            throw new RuntimeException('Falta control seguro de carga de botas: ' . $token);
        }
    }
    echo "[OK] Vista previa de botas para P4: conciliación, duplicados y sin movimientos." . PHP_EOL;
} finally {
    @unlink($temp);
}
