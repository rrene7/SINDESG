<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\BootsP4WorkbookPreview;
use App\Services\P4NominalXlsxTemplate;
use App\Services\SpreadsheetReader;

$path = tempnam(sys_get_temp_dir(), 'sindesg_nominal_test_');
if ($path === false) {
    throw new RuntimeException('No hay almacenamiento temporal.');
}

try {
    (new P4NominalXlsxTemplate())->write($path);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('La plantilla descargada no es un XLSX válido.');
    }
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!is_string($xml)) {
        throw new RuntimeException('La plantilla no tiene hoja Excel.');
    }

    // El importador interno interpreta XML con flexibilidad, pero Microsoft
    // Excel requiere el orden estricto de hijos del CT_Worksheet. Esta
    // regresión evita el diálogo "Encontramos un problema con el contenido".
    $sheetDocument = new DOMDocument();
    if (!@$sheetDocument->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
        throw new RuntimeException('sheet1.xml está mal formado.');
    }
    $actualOrder = [];
    foreach ($sheetDocument->documentElement->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $actualOrder[] = $node->localName;
        }
    }
    $expectedOrder = [
        'dimension', 'sheetViews', 'sheetFormatPr', 'cols',
        'sheetData', 'autoFilter', 'mergeCells', 'pageMargins',
    ];
    if ($actualOrder !== $expectedOrder) {
        throw new RuntimeException(
            'Orden SpreadsheetML inválido: ' . implode(', ', $actualOrder)
        );
    }
    foreach (['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml'] as $part) {
        $partXml = $zip->getFromName($part);
        $dom = new DOMDocument();
        if (!is_string($partXml)
            || !@$dom->loadXML($partXml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Parte XLSX inválida: ' . $part);
        }
    }

    // Simulamos que RRHH llenó dos filas SIN completar el N° automático,
    // como suele pasar al pegar varias posiciones desde otros listados.
    $filled = [
        'B2' => '1122965',
        'B3' => 'DA-038-2025',
        'B4' => '2',
        'B8' => 'AGENTE',
        'C8' => '10688',
        'D8' => 'LUIS',
        'E8' => 'GARCIA',
        'F8' => '8.5',
        'B9' => 'AGENTE',
        'C9' => '10689',
        'D9' => 'MARIA',
        'E9' => 'LOPEZ',
        'F9' => '9',
    ];
    foreach ($filled as $cell => $value) {
        $replacement = '<c r="' . $cell . '" s="5" t="inlineStr"><is><t>'
            . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</t></is></c>';
        if (str_starts_with($cell, 'B') && (int) substr($cell, 1) < 7) {
            $before = '<c r="' . $cell . '" s="2" t="inlineStr"><is>'
                . '<t xml:space="preserve"></t></is></c>';
            $replacement = '<c r="' . $cell . '" s="2" t="inlineStr"><is>'
                . '<t xml:space="preserve">' . $value . '</t></is></c>';
        } else {
            $before = '<c r="' . $cell . '" s="5"/>';
        }
        if (!str_contains($xml, $before)) {
            throw new RuntimeException('No se encontró la celda ' . $cell . ' en el archivo generado.');
        }
        $xml = str_replace($before, $replacement, $xml);
    }
    if (!$zip->addFromString('xl/worksheets/sheet1.xml', $xml) || !$zip->close()) {
        throw new RuntimeException('No se pudo completar el archivo XLSX de prueba.');
    }

    $sheet = (new SpreadsheetReader())->read($path, 'xlsx');
    if (($sheet[0]['rows'][6][2] ?? null) !== 'POSICIÓN') {
        throw new RuntimeException('La plantilla no separa correctamente las columnas en Excel.');
    }
    $preview = (new BootsP4WorkbookPreview())->analyze(
        $sheet, 'SINDESG_Plantilla_Dotacion_P4.xlsx'
    );
    if ($preview['nominal_count'] !== 2
        || $preview['document']['requisition'] !== '1122965'
        || $preview['document']['contract'] !== 'DA-038-2025'
        || $preview['document']['declared_quantity'] !== 2) {
        throw new RuntimeException(
            'La plantilla XLSX rellenada no se importa correctamente: '
            . json_encode($preview['document'])
        );
    }
    if ($preview['nominal'][0]['position'] !== '10688'
        || $preview['nominal'][0]['size'] !== '8.5'
        || $preview['nominal'][1]['position'] !== '10689'
        || $preview['nominal'][1]['size'] !== '9') {
        throw new RuntimeException('Se perdió una posición o talla durante importación XLSX.');
    }
    if ($preview['issues'] !== []) {
        throw new RuntimeException('El archivo de ejemplo está arrojando falsas incidencias.');
    }

    $controller = (string) file_get_contents(
        dirname(__DIR__) . '/app/Controllers/RequestsController.php'
    );
    $view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/requests/show.php');
    if (!str_contains($controller, 'new P4NominalXlsxTemplate()')
        || !str_contains($controller, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        || !str_contains($view, 'Descargar plantilla Excel (.xlsx)')) {
        throw new RuntimeException('La descarga web no usa XLSX real.');
    }
    echo "[OK] Plantilla XLSX con 7 columnas: lectura/importación nominal con N° opcional." . PHP_EOL;
} finally {
    @unlink($path);
}
