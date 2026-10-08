<?php
declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class SpreadsheetReader
{
    private int $maxRows = 50000;
    private int $maxColumns = 200;

    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'csv' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new InvalidArgumentException(
                'Solo se permiten archivos XLSX o CSV.'
            ),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $rows = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber > $this->maxRows) {
                    throw new InvalidArgumentException(
                        'El archivo supera el límite de ' . $this->maxRows . ' filas.'
                    );
                }

                if (count($row) > $this->maxColumns) {
                    throw new InvalidArgumentException(
                        'El archivo contiene demasiadas columnas.'
                    );
                }

                if ($rowNumber === 1 && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
                }

                $rows[] = array_map(
                    static fn ($value): string => trim((string) $value),
                    $row
                );
            }
        } finally {
            fclose($handle);
        }

        return [[
            'name' => 'CSV',
            'rows' => $rows,
        ]];
    }

    private function readXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'La extensión ZIP de PHP no está habilitada. Habilite php_zip para leer XLSX.'
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('El archivo XLSX no es válido o está dañado.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheets = $this->sheetTargets($zip);
            $result = [];
            $totalRows = 0;

            foreach ($sheets as $sheet) {
                $xml = $zip->getFromName($sheet['target']);
                if ($xml === false) {
                    throw new RuntimeException(
                        'No se pudo leer la hoja "' . $sheet['name'] . '".'
                    );
                }

                $rows = $this->worksheetRows($xml, $sharedStrings);
                $totalRows += count($rows);

                if ($totalRows > $this->maxRows) {
                    throw new InvalidArgumentException(
                        'El libro supera el límite de ' . $this->maxRows . ' filas.'
                    );
                }

                $result[] = [
                    'name' => $sheet['name'],
                    'rows' => $rows,
                ];
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;

        if (!@$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('sharedStrings.xml no es válido.');
        }

        $xpath = new DOMXPath($document);
        $strings = [];

        foreach ($xpath->query('//*[local-name()="si"]') ?: [] as $node) {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $node) ?: [] as $textNode) {
                $text .= $textNode->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function sheetTargets(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new InvalidArgumentException('El archivo XLSX no contiene la estructura esperada.');
        }

        $relsDocument = new DOMDocument();
        if (!@$relsDocument->loadXML($relsXml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('Las relaciones del XLSX no son válidas.');
        }

        $relsXpath = new DOMXPath($relsDocument);
        $targets = [];

        foreach ($relsXpath->query('//*[local-name()="Relationship"]') ?: [] as $relation) {
            if (!$relation instanceof DOMElement) {
                continue;
            }

            $id = $relation->getAttribute('Id');
            $target = $relation->getAttribute('Target');

            if ($id === '' || $target === '') {
                continue;
            }

            $target = ltrim($target, '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . $target;
            }

            $targets[$id] = $this->normalizeZipPath($target);
        }

        $workbookDocument = new DOMDocument();
        if (!@$workbookDocument->loadXML($workbookXml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('workbook.xml no es válido.');
        }

        $xpath = new DOMXPath($workbookDocument);
        $result = [];

        foreach ($xpath->query('//*[local-name()="sheet"]') ?: [] as $sheetNode) {
            if (!$sheetNode instanceof DOMElement) {
                continue;
            }

            $name = $sheetNode->getAttribute('name');
            $relationshipId = $sheetNode->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );

            if ($relationshipId === '') {
                $relationshipId = $sheetNode->getAttribute('r:id');
            }

            if ($name === '' || !isset($targets[$relationshipId])) {
                continue;
            }

            $result[] = [
                'name' => mb_substr($name, 0, 180),
                'target' => $targets[$relationshipId],
            ];
        }

        if ($result === []) {
            throw new InvalidArgumentException('El XLSX no contiene hojas legibles.');
        }

        return $result;
    }

    private function worksheetRows(string $xml, array $sharedStrings): array
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;

        if (!@$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('Una hoja del XLSX contiene XML inválido.');
        }

        $xpath = new DOMXPath($document);
        $rows = [];

        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $rowNode) {
            if (!$rowNode instanceof DOMElement) {
                continue;
            }

            $row = [];
            $maxIndex = -1;

            foreach ($xpath->query('./*[local-name()="c"]', $rowNode) ?: [] as $cellNode) {
                if (!$cellNode instanceof DOMElement) {
                    continue;
                }

                $reference = $cellNode->getAttribute('r');
                $columnIndex = $this->columnIndexFromReference($reference);

                if ($columnIndex < 0 || $columnIndex >= $this->maxColumns) {
                    continue;
                }

                $row[$columnIndex] = $this->cellValue(
                    $cellNode,
                    $xpath,
                    $sharedStrings
                );
                $maxIndex = max($maxIndex, $columnIndex);
            }

            if ($maxIndex < 0) {
                $rows[] = [];
                continue;
            }

            $normalized = [];
            for ($index = 0; $index <= $maxIndex; $index++) {
                $normalized[] = isset($row[$index])
                    ? trim((string) $row[$index])
                    : '';
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    private function cellValue(
        DOMElement $cell,
        DOMXPath $xpath,
        array $sharedStrings
    ): string {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="is"]//*[local-name()="t"]', $cell) ?: [] as $node) {
                $text .= $node->textContent;
            }
            return $text;
        }

        $valueNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
        $value = $valueNode?->textContent ?? '';

        if ($type === 's' && $value !== '') {
            $index = (int) $value;
            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $value;
    }

    private function columnIndexFromReference(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeZipPath(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
