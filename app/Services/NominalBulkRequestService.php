<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RequestRepository;
use InvalidArgumentException;
use App\Repositories\P4NominalValidationRepository;
use RuntimeException;

/**
 * Captura nominal masiva en borradores P4. La confirmación escribe solo
 * service_request_items: NO aprueba, reserva, transfiere ni despacha stock.
 */
final class NominalBulkRequestService
{
    public function __construct(
        private RequestRepository $requests,
        private RrhhEmployeeDirectory $employees,
        private ?P4NominalValidationRepository $validations = null
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
            if (empty($employee['is_rrhh_eligible'])
                || !in_array((string) ($employee['position_source'] ?? ''), ['current', 'assignment'], true)) {
                throw new InvalidArgumentException(
                    'Funcionario RRHH #' . $id
                    . ': ' . ($employee['p4_rejection_reason']
                        ?? 'la identidad o posición vigente no pudo verificarse.')
                );
            }
            // Personal prestado puede ser atendido por el P4 responsable, sin bloqueo por adscripción.
            $rows[] = $this->nominalLine($employee, $variantId, 'Selección múltiple P4');
        }
        return $this->requests->addNominalBatch($requestId, $rows);
    }

    /**
     * Decisión EXPRESA de un P4 para una diferencia de nombres, no la
     * aprobación de una entrega. Solo para la fila previamente mostrada.
     * El servidor vuelve a comprobar cédula, posición y estado RRHH.
     *
     * @param array<string,mixed> $preview
     * @param array<string,mixed> $user
     * @return int Número de evidencia de validación en SINDESG.
     */
    public function validatePendingRow(
        array $preview,
        int $excelRow,
        int $employeeId,
        string $reason,
        array $user
    ): int {
        $requestId = (int) ($preview['request_id'] ?? 0);
        $productId = (int) ($preview['product_id'] ?? 0);
        $hash = (string) ($preview['source_sha256'] ?? '');
        $this->editableRequest($requestId);
        if ($this->validations === null
            || !preg_match('/^[a-f0-9]{64}$/', $hash)
            || $productId <= 0 || $excelRow <= 0 || $employeeId <= 0
            || (int) ($user['id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('La sesión de conciliación no es válida. Prevalide de nuevo.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 20 || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('Explique la validación en 20 a 1000 caracteres.');
        }

        $issue = null;
        foreach ((array) ($preview['issues'] ?? []) as $item) {
            if ((int) ($item['excel_row'] ?? 0) === $excelRow
                && (string) ($item['severity'] ?? '') === 'blocking') {
                $issue = $item;
                break;
            }
        }
        if ($issue === null
            || !self::isManuallyValidatable($issue)
            || $employeeId !== (int) ($issue['candidate_employee_id'] ?? 0)) {
            throw new InvalidArgumentException(
                'Esta incidencia requiere corregir el archivo o consultar RRHH: no se puede validar desde aquí.'
            );
        }

        $matches = $this->employees->resolveBulk('employee_id', [$employeeId]);
        $people = [];
        foreach ($matches as $candidate) {
            if ((int) ($candidate['employee_id'] ?? 0) === $employeeId) {
                $people[$employeeId] = $candidate;
            }
        }
        $person = $people[$employeeId] ?? null;
        if ($person === null
            || empty($person['is_rrhh_eligible'])
            || !in_array((string) ($person['position_source'] ?? ''), ['current','assignment'], true)
            || (string) ($person['position_number'] ?? '') !== (string) ($issue['position'] ?? '')
            || trim((string) ($person['document_number'] ?? '')) === '') {
            throw new InvalidArgumentException('La identidad, la posición o el estado RRHH cambió. Prevalide otra vez.');
        }
        // El P4 NO necesita volver a escribir la cédula. La validación
        // vincula exclusivamente al candidato institucional seleccionado
        // en la vista previa y vuelve a contrastar en servidor la cédula
        // obtenida mediante SELECT de RRHH2029.
        if (!hash_equals(
            (string) ($issue['candidate_document'] ?? ''),
            (string) $person['document_number']
        )) {
            throw new InvalidArgumentException(
                'La identidad RRHH cambió desde la prevalidación. Vuelva a prevalorar el Excel.'
            );
        }
        $size = (string) ($issue['normalized_size'] ?? '');
        if (!isset($this->requests->sizeVariantsForProduct($productId)[$size])) {
            throw new InvalidArgumentException('Talla inválida o variante no disponible. Corrija el archivo.');
        }
        if (isset($this->requests->nominalEmployeeIdsForProduct($requestId, $productId)[$employeeId])) {
            throw new InvalidArgumentException('El funcionario ya figura en el borrador para este producto.');
        }

        return $this->validations->record([
            'request_id' => $requestId,
            'product_id' => $productId,
            'source_sha256' => $hash,
            'requisition' => (string) $preview['requisition'],
            'excel_row' => $excelRow,
            'excel_position' => (string) $issue['position'],
            'excel_name' => (string) $issue['excel_name'],
            'excel_size' => (string) ($issue['size'] ?? ''),
            'rrhh_employee_id' => $employeeId,
            'rrhh_document_snapshot' => (string) $person['document_number'],
            'rrhh_position_snapshot' => (string) $person['position_number'],
            'rrhh_name_snapshot' => trim((string) $person['first_name'] . ' ' . (string) $person['last_name']),
            'justification' => $reason,
            'validated_by_user_id' => (int) $user['id'],
            'validated_by_label_snapshot' => (string) ($user['display_name'] ?? 'P4'),
        ]);
    }

    /**
     * No reutilizar una decisión si el Excel, la identidad, la posición
     * vigente o el estado RRHH cambió desde que el P4 confirmó la fila.
     * El hash del archivo y el producto son filtrados en byFile().
     *
     * @param ?array<string,mixed> $saved
     * @param array<string,mixed> $row
     * @param ?array<string,mixed> $person
     */
    public static function isSavedValidationApplicable(
        ?array $saved,
        array $row,
        ?array $person
    ): bool {
        return $saved !== null
            && $person !== null
            && (int) ($saved['rrhh_employee_id'] ?? 0) > 0
            && (int) $saved['rrhh_employee_id'] === (int) ($person['employee_id'] ?? 0)
            && (string) ($saved['rrhh_document_snapshot'] ?? '') !== ''
            && hash_equals(
                (string) $saved['rrhh_document_snapshot'],
                (string) ($person['document_number'] ?? '')
            )
            && (string) ($saved['rrhh_position_snapshot'] ?? '') === (string) ($person['position_number'] ?? '')
            && (string) ($saved['excel_position'] ?? '') === (string) ($row['position'] ?? '')
            && (string) ($saved['excel_name'] ?? '') === (string) ($row['name'] ?? '')
            && (string) ($saved['excel_size'] ?? '') === (string) ($row['raw_size'] ?? '')
            && in_array((string) ($person['position_source'] ?? ''), ['current','assignment'], true)
            && !empty($person['is_rrhh_eligible']);
    }

    /**
     * Diferencia exclusivamente documental; NO crea existencias ni
     * destinatarios. Null significa encabezado sin cantidad declarada.
     */
    public static function documentGap(?int $declared, int $listed): int
    {
        return $declared === null ? 0 : $declared - $listed;
    }

    /** Solo discrepancias nominales con candidato RRHH inequívoco y vigente. */
    public static function isManuallyValidatable(array $issue): bool
    {
        return (string) ($issue['severity'] ?? '') === 'blocking'
            && str_starts_with(
                (string) ($issue['message'] ?? ''),
                'Nombres del Excel y del expediente RRHH no coinciden'
            )
            && (int) ($issue['candidate_employee_id'] ?? 0) > 0
            && (string) ($issue['candidate_document'] ?? '') !== ''
            && in_array(
                (string) ($issue['position_source'] ?? ''),
                ['current','assignment'],
                true
            );
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
        bool $allowPartial,
        bool $acceptDocumentGap = false,
        ?array $approvingUser = null
    ): array {
        $report = $this->reconcileWorkbook($requestId, $productId, $path, $extension, $filename);
        $blockers = $report['blocked_count'];
        $declared = $report['declared_quantity'] ?? null;
        $gap = self::documentGap(
            $declared === null ? null : (int) $declared,
            (int) $report['total']
        );
        $fullyVerified = $blockers === 0
            && count($report['_batch']) + (int) $report['duplicate_count'] === (int) $report['total'];
        // El exceso declarado NO corresponde a un beneficiario ni se
        // debe crear como pedido o existencias. Permitir cargar todas
        // las filas nominales verificadas, con declaración expresa y
        // registro auditable del remanente documental.
        if ($gap !== 0 && (!$allowPartial || $fullyVerified)) {
            if ($gap < 0) {
                throw new InvalidArgumentException(
                    'El listado contiene más destinatarios que unidades declaradas. '
                    . 'Corrija el documento antes de cargar todas las filas.'
                );
            }
            if (!$acceptDocumentGap) {
                throw new InvalidArgumentException(
                    'La requisición declara ' . (int) $declared . ' unidades, '
                    . 'pero tiene ' . (int) $report['total'] . ' destinatarios. '
                    . 'Marque la autorización para registrar la diferencia de '
                    . $gap . ' unidad(es) pendiente(s) SIN asignarlas.'
                );
            }
        }
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
        $gapEvidence = null;
        if ($gap > 0 && $fullyVerified && $acceptDocumentGap) {
            if ((int) ($approvingUser['id'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Se requiere usuario P4 autenticado para registrar el descuadre.');
            }
            $gapEvidence = [
                'user_id' => (int) $approvingUser['id'],
                'user_label_snapshot' => (string) ($approvingUser['display_name'] ?? 'P4'),
                'requisition' => (string) $report['requisition'],
                'source_sha256' => (string) $report['source_sha256'],
                'declared_quantity' => (int) $declared,
                'listed_quantity' => (int) $report['total'],
                'not_assigned' => $gap,
            ];
        }
        $added = $this->requests->addNominalBatch($requestId, $report['_batch'], $gapEvidence);
        return [
            'added' => $added,
            'skipped' => $blockers,
            'document_gap' => $gapEvidence !== null ? $gap : 0,
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
        // Identificar duplicados por posición solo DESPUÉS de comprobar
        // identidad, artículo y talla. Los duplicados plenamente idénticos
        // se omiten con advertencia, no generan una segunda dotación.
        // Si las filas difieren en talla o identidad, se bloquean TODAS.
        $duplicates = $this->classifyNominalDuplicates($nominal, $byPosition, $variants);
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
        $savedValidations = $this->validations !== null
            ? $this->validations->byFile($requestId, $productId, $sourceHash)
            : [];

        foreach ($nominal as $row) {
            $rawPosition = trim((string) ($row['position'] ?? ''));
            $position = ctype_digit($rawPosition) ? (int) $rawPosition : 0;
            $excelRow = (int) ($row['excel_row'] ?? 0);
            $size = $row['size'] ?? null;
            $matches = array_values($byPosition[$position] ?? []);
            $person = count($matches) === 1 ? $matches[0] : null;
            $saved = $savedValidations[$excelRow] ?? null;
            $manualApproved = self::isSavedValidationApplicable($saved, $row, $person);
            $redundantFirstRow = $duplicates['redundant'][$excelRow] ?? null;
            if ($redundantFirstRow !== null) {
                $issues[] = [
                    'excel_row' => $excelRow,
                    'position' => $rawPosition,
                    'excel_name' => (string) ($row['name'] ?? ''),
                    'rrhh_name' => $person !== null
                        ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                        : null,
                    'position_source' => (string) ($person['position_source'] ?? ''),
                    'rrhh_status' => (string) ($person['status_name'] ?? ''),
                    'size' => (string) ($row['raw_size'] ?? ''),
                    'severity' => 'notice',
                    'message' => 'Renglón repetido del mismo funcionario, artículo y talla. Se usa solo fila '
                        . $redundantFirstRow . '; esta fila NO se importará.',
                ];
                $rows[] = [
                    'excel_row' => $excelRow,
                    'position' => $rawPosition,
                    'excel_name' => (string) ($row['name'] ?? ''),
                    'rrhh_name' => $person !== null
                        ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                        : null,
                    'position_source' => (string) ($person['position_source'] ?? ''),
                    'rrhh_status' => (string) ($person['status_name'] ?? ''),
                    'size' => (string) ($row['raw_size'] ?? ''),
                    'status' => 'duplicate_omitted',
                    'reason' => 'Duplicado idéntico: no crea segunda asignación.',
                ];
                continue;
            }

            $cause = null;
            if ($position <= 0) {
                $cause = 'Posición faltante o inválida.';
            } elseif (isset($duplicates['conflicts'][$position])) {
                $cause = $duplicates['conflicts'][$position];
            } elseif (count($matches) === 0) {
                $cause = 'Posición no encontrada como asignación actual de funcionario elegible en RRHH2029. No se puede identificar automáticamente.';
            } elseif (count($matches) > 1) {
                $cause = 'La posición corresponde a varios funcionarios distintos en RRHH2029; requiere aclaración.';
            } elseif (!in_array((string) ($person['position_source'] ?? ''), ['current', 'assignment'], true)) {
                $cause = (string) ($person['position_source'] ?? '') === 'historical'
                    ? 'RRHH reconoce esta posición como anterior, no vigente. Confirme la posición actual antes de asignar.'
                    : 'La asignación abierta difiere de la posición actual del expediente RRHH; conciliar antes de asignar.';
            } elseif (empty($person['is_rrhh_eligible'])) {
                $cause = (string) ($person['p4_rejection_reason']
                    ?? 'No puede verificarse el documento o estado RRHH del funcionario.');
            } elseif (!is_string($size) || !isset($variants[$size])) {
                $cause = 'Talla no válida o sin variante activa para este artículo.';
            } elseif (isset($already[(int) $person['employee_id']])) {
                $cause = 'Este funcionario y artículo ya figuran en la solicitud.';
            } elseif (isset($seenEmployees[(int) $person['employee_id']])) {
                $cause = 'El funcionario aparece dos veces en el archivo, posiblemente con otra posición.';
            } elseif (!$manualApproved && !$this->namesCompatible(
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
                        : (count($matches) > 1
                            ? implode(' | ', array_map(
                                static fn (array $candidate): string => trim(
                                    (string) $candidate['first_name'] . ' ' . (string) $candidate['last_name']
                                ),
                                array_slice($matches, 0, 3)
                            ))
                            : null),
                    'position_source' => (string) ($person['position_source'] ?? ''),
                    'rrhh_status' => (string) ($person['status_name'] ?? ''),
                    'candidate_employee_id' => (int) ($person['employee_id'] ?? 0),
                    'candidate_document' => (string) ($person['document_number'] ?? ''),
                    'normalized_size' => (string) ($row['size'] ?? ''),
                    'size' => (string) ($row['raw_size'] ?? ''),
                    'severity' => 'blocking',
                    'message' => $cause,
                ];
            } else {
                $seenEmployees[(int) $person['employee_id']] = true;
                $notes = 'REQ ' . $requisition . ' · ' . basename($filename)
                    . ' · Fila ' . $excelRow . ' · SHA256 ' . $sourceHash;
                if ($manualApproved) {
                    // Evidencia completa en tabla auditada; no sobrepasar
                    // límite VARCHAR(255) de notas del borrador.
                    $notes = 'REQ ' . $requisition . ' · Fila ' . $excelRow
                        . ' · SHA256 ' . $sourceHash
                        . ' · Validación P4 #' . (int) $saved['id'];
                }
                $batch[] = $this->nominalLine($person, (int) $variants[$size], $notes);
            }
            $rows[] = [
                'excel_row' => $excelRow,
                'position' => $rawPosition,
                'excel_name' => (string) ($row['name'] ?? ''),
                'rrhh_name' => $person !== null
                    ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                    : null,
                'position_source' => (string) ($person['position_source'] ?? ''),
                'rrhh_status' => (string) ($person['status_name'] ?? ''),
                'size' => (string) ($row['raw_size'] ?? ''),
                'status' => $cause === null
                    ? ($manualApproved ? 'validated_manually' : 'verified')
                    : 'review',
                'reason' => $cause ?? '',
            ];
        }

        $sourceRows = [];
        foreach ($nominal as $sourceRow) {
            $sourceRows[(int) ($sourceRow['excel_row'] ?? 0)] = $sourceRow;
        }
        foreach ((array) ($book['issues'] ?? []) as $issue) {
            if (in_array($issue['type'] ?? '', ['quantity', 'summary', 'size_summary', 'size_format'], true)) {
                $excelRow = (int) ($issue['excel_row'] ?? 0);
                $origin = $sourceRows[$excelRow] ?? [];
                $position = (int) ($origin['position'] ?? 0);
                $candidates = array_values($byPosition[$position] ?? []);
                $person = count($candidates) === 1 ? $candidates[0] : null;
                $issues[] = [
                    'excel_row' => $excelRow,
                    'position' => (string) ($origin['position'] ?? ''),
                    'excel_name' => (string) ($origin['name'] ?? ''),
                    'rrhh_name' => $person !== null
                        ? trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                        : null,
                    'position_source' => (string) ($person['position_source'] ?? ''),
                    'rrhh_status' => (string) ($person['status_name'] ?? ''),
                    'size' => (string) ($origin['raw_size'] ?? ''),
                    'severity' => 'notice',
                    'message' => (string) $issue['message'],
                ];
            }
        }

        return [
            'request_id' => $requestId,
            'product_id' => $productId,
            'source_sha256' => $sourceHash,
            'requisition' => $requisition,
            'declared_quantity' => $book['document']['declared_quantity'] ?? null,
            'filename' => basename($filename),
            'total' => count($nominal),
            'valid_count' => count($batch),
            'duplicate_count' => count($duplicates['redundant']),
            'blocked_count' => $blocked,
            'issues' => $issues,
            'rows' => $rows,
            'status' => 'preview_only',
            '_batch' => $batch,
        ];
    }

    /**
     * Solo omite copias completamente conciliadas al mismo funcionario,
     * mismo producto/talla y posición vigente en RRHH. Nunca elige entre
     * dos tallas distintas para la misma persona sin decisión del P4.
     *
     * @param list<array<string,mixed>> $nominal
     * @param array<int,array<int,array<string,mixed>>> $byPosition
     * @param array<string,int> $variants
     * @return array{redundant:array<int,int>,conflicts:array<int,string>}
     */
    private function classifyNominalDuplicates(array $nominal, array $byPosition, array $variants): array
    {
        $groups = [];
        foreach ($nominal as $row) {
            $rawPosition = trim((string) ($row['position'] ?? ''));
            if (ctype_digit($rawPosition) && (int) $rawPosition > 0) {
                $groups[(int) $rawPosition][] = $row;
            }
        }
        $redundant = [];
        $conflicts = [];
        foreach ($groups as $position => $group) {
            if (count($group) < 2) {
                continue;
            }
            $matches = array_values($byPosition[$position] ?? []);
            $person = count($matches) === 1 ? $matches[0] : null;
            $validIdentity = $person !== null
                && !empty($person['is_rrhh_eligible'])
                && in_array((string) ($person['position_source'] ?? ''), ['current', 'assignment'], true);
            $variantId = null;
            $samePersonAndVariant = $validIdentity;
            foreach ($group as $row) {
                $size = $row['size'] ?? null;
                $candidateId = is_string($size) ? ($variants[$size] ?? null) : null;
                if ($candidateId === null
                    || !$this->namesCompatible(
                        (string) ($row['name'] ?? ''),
                        (string) ($person['first_name'] ?? ''),
                        (string) ($person['last_name'] ?? '')
                    )) {
                    $samePersonAndVariant = false;
                }
                if ($variantId !== null && $variantId !== $candidateId) {
                    $samePersonAndVariant = false;
                }
                $variantId = $candidateId;
            }
            if (!$samePersonAndVariant) {
                $conflicts[$position] =
                    'Posición repetida con discrepancias de talla, nombre o estado RRHH. '
                    . 'Revise todas las filas antes de asignar; no se ha elegido ninguna.';
                continue;
            }
            $firstRow = (int) ($group[0]['excel_row'] ?? 0);
            foreach (array_slice($group, 1) as $row) {
                $redundant[(int) ($row['excel_row'] ?? 0)] = $firstRow;
            }
        }
        return ['redundant' => $redundant, 'conflicts' => $conflicts];
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
            $key = (int) ($item[$field] ?? 0);
            $employeeId = (int) ($item['employee_id'] ?? 0);
            if ($key > 0 && $employeeId > 0) {
                $matches[$key][$employeeId] = $item;
            }
        }
        $unique = [];
        foreach ($values as $id) {
            if (count($matches[$id] ?? []) !== 1) {
                throw new InvalidArgumentException(
                    'Funcionario #' . $id . ' no encontrado o ambiguo en RRHH.'
                );
            }
            $unique[$id] = array_values($matches[$id])[0];
        }
        return $unique;
    }

    /**
     * Exactamente dos letras intercambiadas, sin inserciones ni cambios
     * adicionales. Pueden estar separadas (ELMER / ERMEL); la posición
     * vigente y el apellido deben validarse aparte.
     */
    private static function isSingleTransposition(string $source, string $reference): bool
    {
        if (strlen($source) < 5 || strlen($source) !== strlen($reference)) {
            return false;
        }
        $differences = [];
        for ($i = 0, $length = strlen($source); $i < $length; $i++) {
            if ($source[$i] !== $reference[$i]) {
                $differences[] = $i;
                if (count($differences) > 2) {
                    return false;
                }
            }
        }
        if (count($differences) !== 2) {
            return false;
        }
        [$i, $j] = $differences;
        return $source[$i] === $reference[$j]
            && $source[$j] === $reference[$i];
    }

    private function namesCompatible(string $excelName, string $first, string $last): bool
    {
        // No identifica personas por similitud: la posición RRHH debe ser
        // exacta, vigente y única antes de llegar a este comparador.
        // Se admiten nombre/apellido abreviados y UNA errata menor en
        // un apellido largo (p. ej. VILLAREAL / VILLARREAL).
        $normalize = static function (string $value): array {
            $value = mb_strtoupper(trim($value), 'UTF-8');
            $value = strtr($value, [
                'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ü'=>'U', 'Ñ'=>'N',
            ]);
            $value = preg_replace('/[^A-Z ]/u', ' ', $value) ?? '';
            return array_values(array_filter(
                preg_split('/\s+/', trim($value)) ?: [],
                static fn (string $piece): bool => strlen($piece) >= 2
                    && !in_array($piece, ['DE', 'DEL', 'LA', 'LAS', 'LOS'], true)
            ));
        };
        $source = $normalize($excelName);
        $firstTokens = $normalize($first);
        $lastTokens = $normalize($last);
        $reference = array_merge($firstTokens, $lastTokens);
        if (count($source) < 2 || !$firstTokens || !$lastTokens) {
            return false;
        }
        // En un Excel con nombre y apellido abreviados (dos palabras),
        // admitir exclusivamente ortografías muy próximas cuando RRHH ya
        // identificó una posición actual única. No usar esto para
        // resolver la identidad de personas distintas por nombre.
        if (count($source) === 2) {
            foreach ($firstTokens as $registeredFirst) {
                $firstDiff = levenshtein($source[0], $registeredFirst);
                foreach ($lastTokens as $registeredLast) {
                    $lastDiff = levenshtein($source[1], $registeredLast);
                    $firstLong = min(strlen($source[0]), strlen($registeredFirst)) >= 5;
                    $lastLong = min(strlen($source[1]), strlen($registeredLast)) >= 5;
                    $sameSurname = $lastDiff === 0;
                    // Letras intercambiadas una sola vez; no es
                    // similitud abierta. Es obligatorio el apellido
                    // institucional EXACTO, más la posición única
                    // y vigente validada previamente por RRHH.
                    if ($sameSurname && $firstLong
                        && self::isSingleTransposition($source[0], $registeredFirst)) {
                        return true;
                    }
                    if (
                        ($firstDiff === 0 && $lastDiff === 0)
                        || ($firstDiff === 0 && $lastDiff === 1 && $lastLong)
                        || ($firstDiff === 1 && $sameSurname && $firstLong)
                        || ($firstDiff === 1 && $lastDiff === 1 && $firstLong && $lastLong)
                        || ($firstDiff === 2 && $sameSurname
                            && min(strlen($source[0]), strlen($registeredFirst)) >= 7)
                    ) {
                        return true;
                    }
                }
            }
        }

        // Coincidencia estricta del primer nombre o, como máximo,
        // una errata de una letra SI el apellido del Excel coincide
        // exactamente con el apellido institucional y la posición
        // RRHH ya fue resuelta inequívocamente por número.
        $firstIsExact = in_array($source[0], $firstTokens, true);
        if (!$firstIsExact) {
            $surnameConfirmed = count(array_intersect(
                array_slice($source, 1),
                $lastTokens
            )) >= 1;
            $oneLetterNameDifference = false;
            if (strlen($source[0]) >= 5 && $surnameConfirmed) {
                foreach ($firstTokens as $firstToken) {
                    if (strlen($firstToken) >= 5
                        && abs(strlen($source[0]) - strlen($firstToken)) <= 1
                        && levenshtein($source[0], $firstToken) === 1) {
                        $oneLetterNameDifference = true;
                        break;
                    }
                }
            }
            if (!$oneLetterNameDifference) {
                return false;
            }
        }

        $remaining = $reference;
        $exact = 0;
        $fuzzy = 0;
        foreach ($source as $token) {
            $matchedIndex = null;
            foreach ($remaining as $i => $referenceToken) {
                if ($token === $referenceToken) {
                    $matchedIndex = $i;
                    break;
                }
            }
            if ($matchedIndex !== null) {
                unset($remaining[$matchedIndex]);
                $exact++;
                continue;
            }
            if ($fuzzy >= 1 || strlen($token) < 4) {
                continue;
            }
            // Se exige al menos una diferencia real de un carácter en
            // una palabra larga; nunca se utiliza para cambiar posición.
            foreach ($remaining as $i => $referenceToken) {
                if (strlen($referenceToken) >= 4
                    && abs(strlen($token) - strlen($referenceToken)) <= 1
                    && levenshtein($token, $referenceToken) === 1) {
                    unset($remaining[$i]);
                    $fuzzy++;
                    break;
                }
            }
        }
        $matched = $exact + $fuzzy;
        // Para una persona con solo dos tokens en Excel, ambos han de
        // coincidir (exacta o una errata de apellido), siempre con
        // posición vigente verificada de manera independiente.
        return $matched >= max(2, (int) ceil(count($source) * 0.6));
    }

}
