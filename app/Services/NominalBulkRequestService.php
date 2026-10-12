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
        $request = $this->editableRequest($requestId);
        $resolved = $this->employees->resolveBulk('employee_id', $ids);
        $people = $this->uniqueMatches($resolved, $ids, 'employee_id');
        $rows = [];
        foreach ($ids as $id) {
            $employee = $people[$id];
            $this->requireP4Member($employee, $request);
            $rows[] = $this->nominalLine($employee, $variantId, 'Selección múltiple P4');
        }
        return $this->requests->addNominalBatch($requestId, $rows);
    }

    /**
     * Carga desde plantilla XLSX/CSV: un destinatario por fila y talla.
     * Exige confirmar expresamente saltar las filas con incidencias.
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
        $request = $this->editableRequest($requestId);
        if ($productId <= 0) {
            throw new InvalidArgumentException('Seleccione el producto de botas del catálogo.');
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
            throw new InvalidArgumentException('Máximo de 1,000 destinatarios por archivo.');
        }
        $requisition = (string) ($book['document']['requisition'] ?? '');
        if ($requisition === '') {
            throw new InvalidArgumentException('El archivo no contiene un número de requisición verificable.');
        }
        $positions = [];
        $occurrences = [];
        foreach ($nominal as $row) {
            $position = trim((string) ($row['position'] ?? ''));
            if (ctype_digit($position) && (int) $position > 0) {
                $positions[(int) $position] = (int) $position;
                $occurrences[(int) $position] = ($occurrences[(int) $position] ?? 0) + 1;
            }
        }
        $resolved = $this->employees->resolveBulk('position_number', array_values($positions));
        $byPosition = [];
        foreach ($resolved as $employee) {
            $position = (int) ($employee['position_number'] ?? 0);
            $byPosition[$position][] = $employee;
        }
        $already = $this->requests->nominalEmployeeIdsForProduct($requestId, $productId);
        $issues = [];
        $batch = [];
        $skipped = 0;
        $sourceHash = hash_file('sha256', $path);
        if (!is_string($sourceHash)) {
            throw new RuntimeException('No fue posible verificar la huella SHA del archivo.');
        }

        foreach ($nominal as $row) {
            $position = (int) ($row['position'] ?? 0);
            $size = $row['size'] ?? null;
            $excelRow = (int) ($row['excel_row'] ?? 0);
            $cause = null;

            if ($position <= 0) {
                $cause = 'posición ausente o inválida';
            } elseif (($occurrences[$position] ?? 0) > 1) {
                $cause = 'posición repetida en el documento';
            } elseif (!is_string($size) || !isset($variants[$size])) {
                $cause = 'talla no reconocida o sin variante activa de producto';
            } elseif (count($byPosition[$position] ?? []) !== 1) {
                $cause = 'posición no encontrada o ambigua en RRHH2029';
            } elseif (isset($already[(int) $byPosition[$position][0]['employee_id']])) {
                $cause = 'funcionario ya incluido en esta solicitud para el producto';
            } else {
                $person = $byPosition[$position][0];
                if (!$this->isP4Member($person, $request)) {
                    $cause = 'funcionario fuera de la estructura del P4';
                } elseif (!$this->namesCompatible(
                    (string) $row['name'],
                    (string) $person['first_name'],
                    (string) $person['last_name']
                )) {
                    $cause = 'nombre Excel no coincide suficientemente con RRHH';
                } else {
                    $notes = 'REQ ' . $requisition . ' · ' . basename($filename)
                        . ' · Fila ' . $excelRow . ' · SHA256 ' . $sourceHash;
                    $batch[] = $this->nominalLine($person, (int) $variants[$size], $notes);
                }
            }

            if ($cause !== null) {
                $issues[] = 'Fila ' . $excelRow . ' / posición ' . ($position ?: '—') . ': ' . $cause;
                $skipped++;
            }
        }
        foreach ((array) ($book['issues'] ?? []) as $issue) {
            if (in_array($issue['type'] ?? '', ['quantity', 'summary', 'size_summary'], true)) {
                $issues[] = (string) $issue['message'];
            }
        }
        if ($issues !== [] && !$allowPartial) {
            throw new InvalidArgumentException(
                'El Excel requiere conciliación; no se importó nada. '
                . count($issues) . ' observaciones. '
                . implode(' | ', array_slice($issues, 0, 5))
                . '. Para cargar solo destinatarios verificables, marque la autorización de importación parcial.'
            );
        }
        if ($batch === []) {
            throw new InvalidArgumentException('No hay destinatarios verificables para agregar.');
        }

        // Todo o nada por transacción, incluso con confirmación de importación parcial.
        $added = $this->requests->addNominalBatch($requestId, $batch);

        return [
            'added' => $added,
            'skipped' => $skipped,
            'issues' => $issues,
            'requisition' => $requisition,
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

    private function isP4Member(array $employee, array $request): bool
    {
        return in_array(
            (int) $request['organization_unit_id'],
            array_map('intval', (array) ($employee['unit_path_ids'] ?? [])),
            true
        );
    }

    private function requireP4Member(array $employee, array $request): void
    {
        if (!$this->isP4Member($employee, $request)) {
            throw new InvalidArgumentException(
                'Funcionario posición ' . ($employee['position_number'] ?? '—')
                . ' no pertenece al P4 solicitante.'
            );
        }
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
        $normalize = static function (string $v): array {
            $v = mb_strtoupper($v, 'UTF-8');
            $v = strtr($v, [
                'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
            ]);
            $v = preg_replace('/[^A-Z ]/', ' ', $v) ?? '';
            return array_values(array_filter(preg_split('/\s+/', trim($v)) ?: []));
        };
        $source = $normalize($excelName);
        $firstTokens = $normalize($first);
        $lastTokens = $normalize($last);
        if (!$source || !$firstTokens || !$lastTokens) {
            return false;
        }
        return in_array($firstTokens[0], $source, true)
            && in_array($lastTokens[0], $source, true);
    }
}
