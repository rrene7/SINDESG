<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Vista previa del listado nominal de botas recibido por el P4.
 *
 * NO crea solicitudes ni movimientos. El número de requisición es una
 * referencia documental; NO prueba el ingreso de un lote de inventario.
 */
final class BootsP4WorkbookPreview
{
    public function analyze(array $sheets, string $sourceFilename = ''): array
    {
        $document = [
            'source_filename' => basename($sourceFilename),
            'zone' => '',
            'contract' => '',
            'requisition' => '',
            'date' => '',
            'declared_quantity' => null,
        ];
        $nominal = [];
        $issues = [];
        $summarySizes = [];
        $summaryTotal = null;
        $headerFound = false;
        $sheetName = '';

        foreach ($sheets as $sheet) {
            $rows = (array) ($sheet['rows'] ?? []);
            $name = (string) ($sheet['name'] ?? '');
            $headerIndex = null;

            foreach ($rows as $index => $row) {
                $cells = array_map(static fn ($v): string => trim((string) $v), (array) $row);
                $joined = implode(' ', $cells);
                if ($document['zone'] === '' && preg_match('/ZONA\s+DE\s+POLIC[ÍI]A\s+DE\s+COL[ÓO]N/ui', $joined)) {
                    $document['zone'] = '3.ª Zona de Policía de Colón';
                }
                if ($document['contract'] === '' && preg_match('/CONTRATO\s+([A-Z0-9-]+)/ui', $joined, $hit)) {
                    $document['contract'] = strtoupper($hit[1]);
                }
                if ($document['requisition'] === '' && preg_match('/REQUISI[CIÓON]*\s*(?:N[º°O.]*)?\s*(\d+)/ui', $joined, $hit)) {
                    $document['requisition'] = $hit[1];
                }
                if ($document['declared_quantity'] === null
                    && preg_match('/CANTIDAD\s+DE\s+UNIDADES\s+A\s+DOTAR\s*:\s*(\d+)/ui', $joined, $hit)) {
                    $document['declared_quantity'] = (int) $hit[1];
                }
                if ($document['date'] === '' && preg_match('/\b\d{1,2}\s+de\s+[[:alpha:]áéíóúñ]+\s+20\d{2}\b/ui', $joined, $hit)) {
                    $document['date'] = $hit[0];
                }

                $h = array_map(self::token(...), $cells);
                if (
                    ($h[1] ?? '') === 'RANGO'
                    && str_starts_with(($h[2] ?? ''), 'POSICION')
                    && ($h[3] ?? '') === 'NOMBRE'
                    && str_starts_with(($h[4] ?? ''), 'APELLIDO')
                    && ($h[5] ?? '') === 'TALLA'
                ) {
                    $headerIndex = (int) $index;
                    break;
                }
            }

            if ($headerIndex === null) {
                continue;
            }
            if ($headerFound) {
                throw new InvalidArgumentException('Hay más de una hoja nominal. Revise el archivo antes de importar.');
            }
            $headerFound = true;
            $sheetName = $name;

            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $row = array_map(static fn ($v): string => trim((string) $v), (array) $rows[$i]);
                $seq = $row[0] ?? '';
                // El lector entrega filas XLSX en orden de hoja. Para archivos
                // con filas vacías conservadas, el número coincide con Excel.
                $displayRow = $i + 1;

                if (self::token((string) ($row[2] ?? '')) === 'TALLAS') {
                    for ($j = $i + 1; $j < count($rows); $j++) {
                        $t = array_map(static fn ($v): string => trim((string) $v), (array) $rows[$j]);
                        $size = (string) ($t[2] ?? '');
                        if (self::token($size) === 'TOTAL') {
                            $summaryTotal = ctype_digit((string) ($t[3] ?? ''))
                                ? (int) $t[3] : null;
                            break;
                        }
                        if (self::validSize($size) && ctype_digit((string) ($t[3] ?? ''))) {
                            $key = self::canonicalSize($size);
                            $summarySizes[$key] = (int) $t[3];
                        }
                    }
                    break;
                }
                $position = (string) ($row[2] ?? '');
                $size = (string) ($row[5] ?? '');
                $first = (string) ($row[3] ?? '');
                $last = (string) ($row[4] ?? '');
                $rank = (string) ($row[1] ?? '');

                // La numeración N° es opcional en la plantilla. No ignorar a
                // los funcionarios si el P4 pegó únicamente los datos reales.
                // Omitir únicamente las filas completamente vacías.
                if (trim($position . $size . $first . $last . $rank . $seq) === '') {
                    continue;
                }
                if ($seq !== '' && !ctype_digit($seq)) {
                    $issues[] = [
                        'excel_row' => $displayRow,
                        'type' => 'sequence',
                        'message' => 'N° de línea inválido; revíselo o déjelo vacío.',
                    ];
                }
                if (preg_match('/^\\d{1,2}\\s+5$/u', $size) && self::validSize($size)) {
                    $issues[] = [
                        'excel_row' => $displayRow,
                        'type' => 'size_format',
                        'message' => 'La talla escrita como "' . $size
                            . '" se interpretó como ' . self::canonicalSize($size)
                            . '. Confirme el formato en el Excel antes de aprobar la dotación.',
                    ];
                }
                $record = [
                    'excel_row' => $displayRow,
                    'number' => ctype_digit($seq) ? (int) $seq : count($nominal) + 1,
                    'rank' => $rank,
                    'position' => $position,
                    'name' => trim($first . ' ' . $last),
                    'raw_size' => $size,
                    'size' => self::validSize($size) ? self::canonicalSize($size) : null,
                    'signature_present' => trim((string) ($row[6] ?? '')) !== '',
                ];
                $nominal[] = $record;

                if ($position === '' || !ctype_digit($position)) {
                    $issues[] = [
                        'excel_row' => $displayRow,
                        'type' => 'position',
                        'message' => 'Posición ausente o no numérica.',
                    ];
                }
                if ($first === '' || $last === '') {
                    $issues[] = [
                        'excel_row' => $displayRow,
                        'type' => 'name',
                        'message' => 'Nombre o apellido incompleto.',
                    ];
                }
                if ($record['size'] === null) {
                    $issues[] = [
                        'excel_row' => $displayRow,
                        'type' => 'size',
                        'message' => 'Talla no válida: "' . $size . '". Confirmarla antes de asignar.',
                    ];
                }

                if (count($nominal) > 5000) {
                    throw new InvalidArgumentException('El piloto admite hasta 5,000 asignaciones nominales.');
                }
            }
        }

        if (!$headerFound || $nominal === []) {
            throw new InvalidArgumentException(
                'No se encontró el listado nominal (RANGO, POSICIÓN, NOMBRE, APELLIDO, TALLA).'
            );
        }

        $positions = [];
        $sizes = [];
        foreach ($nominal as $person) {
            if ($person['position'] !== '') {
                $positions[$person['position']][] = $person['excel_row'];
            }
            if ($person['size'] !== null) {
                $sizes[$person['size']] = ($sizes[$person['size']] ?? 0) + 1;
            }
        }
        foreach ($positions as $position => $excelRows) {
            if (count($excelRows) > 1) {
                $issues[] = [
                    'excel_row' => $excelRows[0],
                    'type' => 'duplicate',
                    'message' => 'Posición ' . $position . ' repetida en filas '
                        . implode(', ', $excelRows) . '. Verificar destinatario y talla.',
                ];
            }
        }

        $total = count($nominal);
        if ($document['declared_quantity'] !== null && $document['declared_quantity'] !== $total) {
            $issues[] = [
                'excel_row' => 0,
                'type' => 'quantity',
                'message' => 'Encabezado: ' . $document['declared_quantity']
                    . '; filas nominales: ' . $total . '. Diferencia: '
                    . ($total - $document['declared_quantity']) . '.',
            ];
        }
        if ($summaryTotal !== null && $summaryTotal !== $total) {
            $issues[] = [
                'excel_row' => 0,
                'type' => 'summary',
                'message' => 'Resumen por tallas: ' . $summaryTotal
                    . '; filas nominales: ' . $total . '.',
            ];
        }
        foreach ($summarySizes as $size => $summaryQuantity) {
            $actual = $sizes[$size] ?? 0;
            if ($actual !== $summaryQuantity) {
                $issues[] = [
                    'excel_row' => 0,
                    'type' => 'size_summary',
                    'message' => 'Talla ' . $size . ': resumen ' . $summaryQuantity
                        . ', listado validado ' . $actual . '.',
                ];
            }
        }
        uksort($sizes, static fn (string $a, string $b): int => (float) $a <=> (float) $b);
        usort($issues, static fn (array $a, array $b): int => $a['excel_row'] <=> $b['excel_row']);

        return [
            'document' => $document,
            'sheet' => $sheetName,
            'nominal_count' => $total,
            'validated_size_count' => array_sum($sizes),
            'size_counts' => $sizes,
            'summary_sizes' => $summarySizes,
            'summary_total' => $summaryTotal,
            'issues' => $issues,
            'nominal' => $nominal,
            'preview' => array_slice($nominal, 0, 12),
            'signature_rows' => count(array_filter(
                $nominal, static fn (array $r): bool => $r['signature_present']
            )),
            'status' => 'preview_only',
        ];
    }

    private static function token(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O',
            'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    private static function validSize(string $size): bool
    {
        $size = self::normalizeSizeInput($size);
        if (!preg_match('/^\d{1,2}(?:\.5)?$/', $size)) {
            return false;
        }
        $numeric = (float) $size;
        return $numeric >= 3.0 && $numeric <= 20.0;
    }

    private static function canonicalSize(string $size): string
    {
        return rtrim(rtrim(number_format((float) self::normalizeSizeInput($size), 1, '.', ''), '0'), '.');
    }

    /**
     * Una única celda de talla "8 5" (frecuente al copiar archivos)
     * equivale a talla 8.5, nunca a dos renglones diferentes.
     * La variante activa 8.5 sigue siendo obligatoria.
     */
    private static function normalizeSizeInput(string $size): string
    {
        $size = str_replace(',', '.', trim($size));
        return preg_replace('/^(\\d{1,2})\\s+5$/u', '$1.5', $size) ?? $size;
    }
}
