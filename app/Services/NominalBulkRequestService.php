<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RequestRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Captura nominal masiva en borradores P4. La confirmación escribe solo
 * service_request_items: NO aprueba, reserva, transfiere ni despacha stock.
 */
final class NominalBulkRequestService
{
    public function __construct(
        private RequestRepository $requests,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    /**
     * Selección múltiple manual: un mismo producto/talla para todos.
     * @param array<int|string,mixed> $employeeIds
     */
    public function addSelected(int $requestId, int $variantId, array $employeeIds): int
    {
        if ($variantId <= 0 || !$this->requests->variantIsActive($variantId)) {
            throw new InvalidArgumentException('Seleccione un artículo/talla válido.');
        }
        $ids = $this->ids($employeeIds);
        $this->editableRequest($requestId);
        $resolved = $this->employees->resolveBulk('employee_id', $ids);
        $people = $this->uniqueMatches($resolved, $ids, 'employee_id');
        $rows = [];
        foreach ($ids as $id) {
            $employee = $people[$id];
            // Personal prestado puede ser atendido por el P4 responsable, sin bloqueo por adscripción.
            $rows[] = $this->nominalLine($employee, $variantId, 'Selección múltiple P4');
        }
        return $this->requests->addNominalBatch($requestId, $rows);
    }

    /**
     * Revisión previa, íntegramente solo lectura. El P4 ve las diferencias
     * de posición/nombre antes de decidir si corrige el Excel o autoriza
     * una importación parcial de filas realmente verificables.
     *
     * @return array<string,mixed>
     */
    public function previewWorkbook(
        int $requestId,
        int $productId,
        string $path,
        string $extension,
        string $filename
    ): array {
        $report = $this->reconcileWorkbook($requestId, $productId, $path, $extension, $filename);
        unset($report['_batch']);
        return $report;
    }

    /**
     * Importa únicamente destinatarios sin bloqueos; no crea movimientos.
     * Advertencias documentales (totales) se muestran sin invalidar
     * por sí solas a un funcionario inequívocamente identificado.
     *
     * @return array{added:int,skipped:int,issues:list<string>,requisition:string}
     */
    public function importWorkbook(
        int $requestId,
        int $productId,
        string $path,
        string $extension,
        string $filename,
        bool $allowPartial
    ): array {
        $report = $this->reconcileWorkbook($requestId, $productId, $path, $extension, $filename);
        $blockers = $report['blocked_count'];
        if ($blockers > 0 && !$allowPartial) {
            throw new InvalidArgumentException(
                'La solicitud NO se importó: ' . $blockers . ' funcionarios requieren revisión. '
                . 'Use "Prevalidar y ver incidencias" para consultar cada fila del Excel frente a RRHH. '
                . 'Corrija posiciones o nombres discrepantes; la importación parcial solo incluye registros comprobados.'
            );
        }
        if ($report['_batch'] === []) {
            throw new InvalidArgumentException('No se encontró ningún destinatario seguro para agregar.');
        }
        $added = $this->requests->addNominalBatch($requestId, $report['_batch']);
        return [
            'added' => $added,
            'skipped' => $blockers,
            'issues' => array_map(
                static fn (array $issue): string => 'Fila ' . $issue['excel_row']
                    . ' / posición ' . ($issue['position'] ?: '—') . ': ' . $issue['message'],
                $report['issues']
            ),
            'requisition' => $report['requisition'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function reconcileWorkbook(
        int $requestId,
        int $productId,
        string $path,
        string $extension,
        string $filename
    ): array {
        $this->editableRequest($requestId);
        if ($productId <= 0) {
            throw new InvalidArgumentException('Seleccione el artículo del catálogo.');
        }
        $variants = $this->requests->sizeVariantsForProduct($productId);
        if ($variants === []) {
            throw new InvalidArgumentException('El artículo seleccionado no tiene tallas activas.');
        }
        $book = (new BootsP4WorkbookPreview())->analyze(
            (new SpreadsheetReader())->read($path, $extension),
            $filename
        );
        $nominal = (array) ($book['nominal'] ?? []);
        if (count($nominal) > 1000) {
            throw new InvalidArgumentException('Máximo 1,000 destinatarios por archivo.');
        }
        $requisition = trim((string) ($book['document']['requisition'] ?? ''));
        if ($requisition === '') {
            throw new InvalidArgumentException('El archivo no contiene una requisición verificable.');
        }

        $positions = [];
        $occurrences = [];
        foreach ($nominal as $row) {
            $value = trim((string) ($row['position'] ?? ''));
            if (ctype_digit($value) && (int) $value > 0) {
                $position = (int) $value;
                $positions[$position] = $position;
                $occurrences[$position] = ($occurrences[$position] ?? 0) + 1;
            }
        }

        $byPosition = [];
        $employees = $this->employees->resolveBulk('position_number', array_values($positions));
        foreach ($employees as $person) {
            $position = (int) ($person['position_number'] ?? 0);
            $id = (int) ($person['employee_id'] ?? 0);
            if ($position > 0 && $id > 0) {
                // La consulta RRHH puede repetir filas por adscripciones
                // concurrentes del MISMO funcionario. No confundir con dos
                // funcionarios diferentes ocupando una misma posición.
                $byPosition[$position][$id] = $person;
            }
        }
        $already = $this->requests->nominalEmployeeIdsForProduct($requestId, $productId);
        $seenEmployees = [];
        $rows = [];
        $issues = [];
        $batch = [];
        $blocked = 0;
        $sourceHash = hash_file('sha256', $path);
        if (!is_string($sourceHash)) {
            throw new RuntimeException('No se pudo verificar el archivo de origen.');
        }

        foreach ($nominal as $row) {
            $rawPosition = trim((string) ($row['position'] ?? ''));
            $position = ctype_digit($rawPosition) ? (int) $rawPosition : 0;
            $excelRow = (int) ($row['excel_row'] ?? 0);
            $size = $row['size'] ?? null;
            $matches = array_values($byPosition[$position] ?? []);
            $person = count($matches) === 1 ? $matches[0] : null;
            $cause = null;
            if ($position <= 0) {
                $cause = 'Posición faltante o inválida.';
            } elseif (($occurrences[$position] ?? 0) > 1) {
                $cause = 'Posición repetida dentro del Excel; verifique a quién corresponde cada talla.';
            } elseif (count($matches) === 0) {
                $cause = 'Posición no encontrada como asignación actual de funcionario elegible en RRHH2029. No se puede identificar automáticamente.';
            } elseif (count($matches) > 1) {
                $cause = 'La posición corresponde a varios funcionarios distintos en RRHH2029; requiere aclaración.';
            } elseif (!is_string($size) || !isset($variants[$size])) {
                $cause = 'Talla no válida o sin variante activa para este artículo.';
            } elseif (isset($already[(int) $person['employee_id']])) {
                $cause = 'Este funcionario y artículo ya figuran en la solicitud.';
            } elseif (isset($seenEmployees[(int) $person['employee_id']])) {
                $cause = 'El funcionario aparece dos veces en el archivo, posiblemente con otra posición.';
            } elseif (!$this->namesCompatible(
                (string) ($row['name'] ?? ''),
                (string) $person['first_name'],
                (string) $person['last_name']
            )) {
                $cause = 'Nombres del Excel y del expediente RRHH no coinciden suficientemente. Revisar antes de asociar.';
            }
            if ($cause !== null) {
                $blocked++;
                $issues[] = [
                    'excel_row' => $excelRow,
                    'position' => $rawPosition,
                    'excel_name' => (string) ($row['name'] ?? ''),
                    'rrhh_name' => $person !== null
                        ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                        : null,
                    'size' => (string) ($row['raw_size'] ?? ''),
                    'severity' => 'blocking',
                    'message' => $cause,
                ];
            } else {
                $seenEmployees[(int) $person['employee_id']] = true;
                $notes = 'REQ ' . $requisition . ' · ' . basename($filename)
                    . ' · Fila ' . $excelRow . ' · SHA256 ' . $sourceHash;
                $batch[] = $this->nominalLine($person, (int) $variants[$size], $notes);
            }
            $rows[] = [
                'excel_row' => $excelRow,
                'position' => $rawPosition,
                'excel_name' => (string) ($row['name'] ?? ''),
                'rrhh_name' => $person !== null
                    ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                    : null,
                'size' => (string) ($row['raw_size'] ?? ''),
                'status' => $cause === null ? 'verified' : 'review',
                'reason' => $cause ?? '',
            ];
        }

        foreach ((array) ($book['issues'] ?? []) as $issue) {
            if (in_array($issue['type'] ?? '', ['quantity', 'summary', 'size_summary'], true)) {
                $issues[] = [
                    'excel_row' => 0,
                    'position' => '',
                    'excel_name' => '',
                    'rrhh_name' => null,
                    'size' => '',
                    'severity' => 'notice',
                    'message' => (string) $issue['message'],
                ];
            }
        }

        return [
            'requisition' => $requisition,
            'filename' => basename($filename),
            'total' => count($nominal),
            'valid_count' => count($batch),
            'blocked_count' => $blocked,
            'issues' => $issues,
            'rows' => $rows,
            'status' => 'preview_only',
            '_batch' => $batch,
        ];
    }

    private function editableRequest(int $requestId): array
    {
        $request = $this->requests->find($requestId);
        if (!$request || $request['status'] !== 'draft') {
            throw new InvalidArgumentException('Seleccione una solicitud P4 en estado borrador.');
        }
        if ((int) ($request['organization_unit_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('El P4 debe estar vinculado a una unidad RRHH.');
        }
        return $request;
    }

    private function nominalLine(array $employee, int $variantId, string $notes): array
    {
        return [
            'employee_id' => (int) $employee['employee_id'],
            'display_name' => (string) $employee['display_name'],
            'document_number' => (string) $employee['document_number'],
            'position_number' => $employee['position_number'],
            'rank_name' => $employee['rank_name'],
            'organization_unit_id' => $employee['organization_unit_id'],
            'organization_unit_name' => $employee['organization_unit_name'],
            'product_variant_id' => $variantId,
            'notes' => $notes,
        ];
    }

    /** @param array<int|string,mixed> $raw @return list<int> */
    private function ids(array $raw): array
    {
        $result = [];
        foreach ($raw as $value) {
            $value = trim((string) $value);
            if (!ctype_digit($value) || (int) $value <= 0) {
                throw new InvalidArgumentException('La selección contiene un funcionario inválido.');
            }
            $result[(int) $value] = (int) $value;
        }
        if (!$result || count($result) > 500) {
            throw new InvalidArgumentException('Seleccione entre 1 y 500 funcionarios por operación.');
        }
        return array_values($result);
    }

    private function uniqueMatches(array $resolved, array $values, string $field): array
    {
        $matches = [];
        foreach ($resolved as $item) {
            $key = (int) $item[$field];
            $matches[$key][] = $item;
        }
        $unique = [];
        foreach ($values as $id) {
            if (count($matches[$id] ?? []) !== 1) {
                throw new InvalidArgumentException(
                    'Funcionario #' . $id . ' no encontrado o ambiguo en RRHH.'
                );
            }
            $unique[$id] = $matches[$id][0];
        }
        return $unique;
    }

    private function namesCompatible(string $excelName, string $first, string $last): bool
    {
        // RRHH puede separar el segundo nombre/apellido en campos distintos
        // al Excel. No obligar que el primer apellido esté en last_name.
        // La posición numérica debe coincidir de forma exacta y única ANTES
        // de usar esta comparación de identidad auxiliar.
        $normalize = static function (string $value): array {
            $value = mb_strtoupper($value, 'UTF-8');
            $value = strtr($value, [
                'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ü'=>'U', 'Ñ'=>'N',
            ]);
            $value = preg_replace('/[^A-Z ]/u', ' ', $value) ?? '';
            $parts = preg_split('/\s+/', trim($value)) ?: [];
            return array_values(array_filter(
                $parts,
                static fn (string $part): bool => strlen($part) >= 2
                    && !in_array($part, ['DE','DEL','LA','LAS','LOS'], true)
            ));
        };
        $source = $normalize($excelName);
        $reference = $normalize(trim($first . ' ' . $last));
        if (count($source) < 2 || count($reference) < 2) {
            return false;
        }
        $remaining = array_count_values($reference);
        $matched = 0;
        foreach ($source as $token) {
            if (($remaining[$token] ?? 0) > 0) {
                $remaining[$token]--;
                $matched++;
            }
        }
        // Se exigen al menos dos nombres o apellidos compartidos.
        // Nombres realmente distintos siguen pendientes de revisión.
        return $matched >= max(2, (int) ceil(count($source) * 0.6));
    }

}
