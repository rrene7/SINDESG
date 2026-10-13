<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Plantilla XLSX nativa (no CSV regional) compatible con SpreadsheetReader
 * y BootsP4WorkbookPreview. No contiene datos de funcionarios ni stock.
 *
 * Sin dependencias externas: usa la extensión ZipArchive ya exigida por el
 * lector XLSX actual. Acomodada para hasta 1,000 destinatarios por archivo.
 */
final class P4NominalXlsxTemplate
{
    public function write(string $filename): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Habilite la extensión ZIP de PHP para descargar Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar la plantilla Excel.');
        }

        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML,
            '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML,
            'xl/workbook.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Dotacion P4" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML,
            'xl/_rels/workbook.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML,
            'xl/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="4">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
<font><sz val="10"/><color rgb="FF446176"/><name val="Calibri"/></font>
</fonts>
<fills count="5">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF092B50"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF1267A4"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFEAF5FF"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="2">
<border><left/><right/><top/><bottom/><diagonal/></border>
<border><left style="thin"><color rgb="FFBED8E7"/></left>
<right style="thin"><color rgb="FFBED8E7"/></right>
<top style="thin"><color rgb="FFBED8E7"/></top>
<bottom style="thin"><color rgb="FFBED8E7"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="6">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="left" indent="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
</cellXfs>
</styleSheet>
XML,
            'xl/worksheets/sheet1.xml' => $this->worksheetXml(),
        ];

        try {
            foreach ($parts as $path => $content) {
                if (!$zip->addFromString($path, $content)) {
                    throw new RuntimeException('No se pudo generar una parte del Excel: ' . $path);
                }
            }
        } finally {
            if (!$zip->close()) {
                throw new RuntimeException('No se pudo finalizar la plantilla Excel.');
            }
        }
    }

    private function worksheetXml(): string
    {
        $rows = [];
        $rows[] = '<row r="1" ht="32" customHeight="1">'
            . self::cell('A1', 'SINDESG · PLANTILLA DE DOTACIÓN NOMINAL P4', 1)
            . '</row>';
        foreach ([
            2 => ['REQUISICION Nº', ''],
            3 => ['CONTRATO', ''],
            4 => ['CANTIDAD DE UNIDADES A DOTAR:', ''],
            5 => ['ZONA / P4 RESPONSABLE', ''],
        ] as $n => $values) {
            $rows[] = '<row r="' . $n . '" ht="26" customHeight="1">'
                . self::cell('A' . $n, $values[0], 2)
                . self::cell('B' . $n, $values[1], 2)
                . '</row>';
        }
        $rows[] = '<row r="6" ht="27" customHeight="1">'
            . self::cell(
                'A6',
                'Llene la requisición y una línea por funcionario: posición, nombre, apellido y talla. Nº es opcional. No agregue stock aquí.',
                3
            ) . '</row>';
        $headers = ['N°', 'RANGO', 'POSICIÓN', 'NOMBRE', 'APELLIDO', 'TALLA', 'FIRMA'];
        $headerRow = '<row r="7" ht="28" customHeight="1">';
        foreach ($headers as $i => $label) {
            $headerRow .= self::cell(chr(65 + $i) . '7', $label, 4);
        }
        $rows[] = $headerRow . '</row>';

        // Formato listo para 1,000 registros, sin números prellenados:
        // de ese modo, las filas vacías NO se interpretan como funcionarios.
        for ($row = 8; $row <= 1007; $row++) {
            $cells = '';
            for ($column = 0; $column < 7; $column++) {
                $cells .= '<c r="' . chr(65 + $column) . $row . '" s="5"/>';
            }
            $rows[] = '<row r="' . $row . '" ht="21" customHeight="1">'
                . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:G1007"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols>'
            . '<col min="1" max="1" width="9" customWidth="1"/>'
            . '<col min="2" max="2" width="25" customWidth="1"/>'
            . '<col min="3" max="3" width="16" customWidth="1" style="5"/>'
            . '<col min="4" max="4" width="29" customWidth="1"/>'
            . '<col min="5" max="5" width="29" customWidth="1"/>'
            . '<col min="6" max="6" width="15" customWidth="1"/>'
            . '<col min="7" max="7" width="24" customWidth="1"/>'
            . '</cols><sheetData>' . implode('', $rows) . '</sheetData>'
            // SpreadsheetML (ISO/IEC 29500 CT_Worksheet) requires the
            // autoFilter element BEFORE mergeCells. Reversing these two
            // elements made Excel report "problem with some content", even
            // though our permissive XLSX reader could parse the file.
            . '<autoFilter ref="A7:G1007"/>'
            . '<mergeCells count="2"><mergeCell ref="A1:G1"/><mergeCell ref="A6:G6"/></mergeCells>'
            . '<pageMargins left="0.5" right="0.5" top="0.7" bottom="0.7" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    private static function cell(string $address, string $value, int $style): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return '<c r="' . $address . '" s="' . $style
            . '" t="inlineStr"><is><t xml:space="preserve">' . $escaped
            . '</t></is></c>';
    }
}
