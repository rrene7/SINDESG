<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use App\Repositories\HistoricalImportRepository;
use InvalidArgumentException;
use RuntimeException;

final class HistoricalImportService
{
    public function __construct(
        private HistoricalImportRepository $repository,
        private SpreadsheetReader $reader,
        private AuditRepository $audit
    ) {
    }

    public function upload(array $file, ?string $notes, array $actor): int
    {
        $validated = $this->validateUpload($file);
        $sha256 = hash_file('sha256', $validated['tmp_name']);

        if (!is_string($sha256) || strlen($sha256) !== 64) {
            throw new RuntimeException('No se pudo calcular la huella del archivo.');
        }

        $existing = $this->repository->findBatchByHash($sha256);
        if ($existing !== null) {
            throw new InvalidArgumentException(
                'Este archivo ya fue cargado como ' . $existing['batch_code'] . '.'
            );
        }

        $stored = $this->storeSourceFile($validated, $sha256);
        $batchId = null;

        try {
            $sheets = $this->reader->read(
                $stored['absolute_path'],
                $validated['extension']
            );

            $batchId = $this->repository->createBatch([
                'source_filename' => $validated['original_name'],
                'stored_path' => $stored['relative_path'],
                'file_extension' => $validated['extension'],
                'file_size' => $validated['size_bytes'],
                'file_sha256' => $sha256,
                'uploaded_by_user_id' => $actor['id'],
                'uploaded_by_label_snapshot' => $actor['display_name'],
                'notes' => $this->nullableText($notes),
            ]);

            foreach ($sheets as $sheet) {
                $this->stageSheet($batchId, $sheet);
            }

            $this->validateWorkbookConsistency($batchId);

            $this->audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'migration',
                'action_code' => 'historical_file_staged',
                'record_type' => 'historical_import_batch',
                'record_id' => (string) $batchId,
                'details' => [
                    'source_filename' => $validated['original_name'],
                    'sha256' => $sha256,
                    'sheet_count' => count($sheets),
                    'file_size' => $validated['size_bytes'],
                ],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            return $batchId;
        } catch (\Throwable $exception) {
            if (is_file($stored['absolute_path'])) {
                @unlink($stored['absolute_path']);
            }
            throw $exception;
        }
    }

    public function reprocess(int $batchId, array $actor): array
    {
        $batch = $this->repository->batch($batchId);
        if ($batch === null) {
            throw new InvalidArgumentException('El lote histórico no existe.');
        }

        if ((string) ($batch['status'] ?? '') !== 'staged') {
            throw new RuntimeException(
                'Solo se puede reprocesar un lote que permanezca en staging.'
            );
        }

        if ((int) ($batch['imported_count'] ?? 0) > 0) {
            throw new RuntimeException(
                'El lote ya contiene filas archivadas y no puede reprocesarse.'
            );
        }

        $relativePath = ltrim((string) ($batch['stored_path'] ?? ''), '/\\');
        $absolutePath = SINDESG_ROOT . '/storage/uploads/' . $relativePath;

        if ($relativePath === '' || !is_file($absolutePath)) {
            throw new RuntimeException(
                'No se encontró el archivo fuente conservado para reprocesar este lote.'
            );
        }

        $extension = strtolower(trim((string) ($batch['file_extension'] ?? '')));
        $sheets = $this->reader->read($absolutePath, $extension);

        $this->repository->resetStaging($batchId);

        foreach ($sheets as $sheet) {
            $this->stageSheet($batchId, $sheet);
        }

        $this->validateWorkbookConsistency($batchId);

        $this->audit->log([
            'user_id' => $actor['id'],
            'user_label_snapshot' => $actor['display_name'],
            'module_code' => 'migration',
            'action_code' => 'historical_batch_reprocessed',
            'record_type' => 'historical_import_batch',
            'record_id' => (string) $batchId,
            'details' => [
                'source_filename' => $batch['source_filename'],
                'sheet_count' => count($sheets),
                'live_inventory_changed' => false,
            ],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return [
            'batch_id' => $batchId,
            'sheet_count' => count($sheets),
        ];
    }

    public function archiveReady(int $batchId, array $actor): array
    {
        $result = $this->repository->archiveReadySnapshots($batchId);

        $this->audit->log([
            'user_id' => $actor['id'],
            'user_label_snapshot' => $actor['display_name'],
            'module_code' => 'migration',
            'action_code' => 'historical_snapshot_archived',
            'record_type' => 'historical_import_batch',
            'record_id' => (string) $batchId,
            'details' => $result + [
                'live_inventory_changed' => false,
            ],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return $result;
    }

    public function cancel(int $batchId, array $actor): void
    {
        $this->repository->cancelBatch($batchId);

        $this->audit->log([
            'user_id' => $actor['id'],
            'user_label_snapshot' => $actor['display_name'],
            'module_code' => 'migration',
            'action_code' => 'historical_batch_cancelled',
            'record_type' => 'historical_import_batch',
            'record_id' => (string) $batchId,
            'details' => [
                'live_inventory_changed' => false,
            ],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    private function stageSheet(int $batchId, array $sheet): void
    {
        $name = mb_substr(trim((string) ($sheet['name'] ?? 'Hoja')), 0, 180);
        $rows = is_array($sheet['rows'] ?? null) ? $sheet['rows'] : [];

        [$profile, $headerIndex, $headers] = $this->detectProfile($name, $rows);

        $sheetId = $this->repository->createSheet([
            'batch_id' => $batchId,
            'sheet_name' => $name !== '' ? $name : 'Hoja',
            'profile' => $profile,
            'header_row_number' => $headerIndex === null ? null : $headerIndex + 1,
            'total_source_rows' => count($rows),
        ]);

        foreach ($rows as $index => $row) {
            if (!is_array($row) || $this->rowIsBlank($row)) {
                continue;
            }

            if ($headerIndex !== null && $index <= $headerIndex) {
                continue;
            }

            $staged = match ($profile) {
                'inventory_normalized_2026' => $this->normalizeInventoryRow(
                    $row,
                    $headers
                ),
                'inventory_control_2026' => $this->normalizeControlInventoryRow(
                    $row,
                    $headers
                ),
                'historical_entries_2024_2026' => $this->normalizeHistoricalMovementRow(
                    $row,
                    $headers,
                    'entry'
                ),
                'historical_exits_2024_2026' => $this->normalizeHistoricalMovementRow(
                    $row,
                    $headers,
                    'exit'
                ),
                'source_issues' => $this->normalizeIssueRow($row),
                'reference_report',
                'rendition_report',
                'legacy_inventory_summary' => $this->normalizeReferenceRow($row, $profile),
                default => $this->normalizeUnknownRow($row),
            };

            if ($staged === null) {
                continue;
            }

            $this->repository->insertRow([
                'batch_id' => $batchId,
                'sheet_id' => $sheetId,
                'source_row_number' => $index + 1,
                'row_profile' => $profile,
                'raw_json' => json_encode(
                    array_values($row),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: '[]',
                'normalized_code' => $staged['normalized_code'],
                'normalized_description' => $staged['normalized_description'],
                'normalized_product' => $staged['normalized_product'],
                'normalized_category' => $staged['normalized_category'],
                'normalized_size' => $staged['normalized_size'],
                'normalized_sex' => $staged['normalized_sex'],
                'normalized_initial_quantity' => $staged['normalized_initial_quantity'],
                'normalized_entries_quantity' => $staged['normalized_entries_quantity'],
                'normalized_exits_quantity' => $staged['normalized_exits_quantity'],
                'normalized_quantity' => $staged['normalized_quantity'],
                'mapping_variant_id' => $staged['mapping_variant_id'],
                'row_status' => $staged['row_status'],
                'issues_json' => $staged['issues'] === []
                    ? null
                    : json_encode(
                        $staged['issues'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
            ]);
        }

        $this->repository->refreshSheetCounters($sheetId);
    }

    private function detectProfile(string $sheetName, array $rows): array
    {
        $normalizedSheet = $this->normalizeHeader($sheetName);

        if (str_contains($normalizedSheet, 'INCIDENCIA')) {
            $max = min(count($rows), 20);

            for ($index = 0; $index < $max; $index++) {
                $headers = $this->headersFromRow(
                    is_array($rows[$index] ?? null) ? $rows[$index] : []
                );

                if (isset($headers['FILA ORIGEN']) && isset($headers['INCIDENCIA'])) {
                    return ['source_issues', $index, $headers];
                }
            }

            return ['source_issues', $this->findFirstContentRow($rows), []];
        }

        $max = min(count($rows), 25);

        for ($index = 0; $index < $max; $index++) {
            $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
            $headers = $this->headersFromRow($row);

            if (
                isset($headers['CODIGO ISTMO'])
                && isset($headers['INVENTARIO RECALCULADO'])
                && (
                    isset($headers['PRODUCTO BASE'])
                    || isset($headers['DESCRIPCION ORIGINAL'])
                )
            ) {
                return ['inventory_normalized_2026', $index, $headers];
            }

            if (
                isset($headers['CODIGO ISTMO'])
                && isset($headers['NOMBRE'])
                && isset($headers['TIPO'])
                && isset($headers['INVENTARIO INICIAL'])
                && isset($headers['ENTRADAS'])
                && isset($headers['SALIDAS'])
                && isset($headers['INVENTARIO'])
            ) {
                return ['inventory_control_2026', $index, $headers];
            }

            if (
                isset($headers['FECHA'])
                && isset($headers['CODIGO ISTMO'])
                && isset($headers['PRODUCTO'])
                && isset($headers['CANTIDAD'])
            ) {
                if (str_contains($normalizedSheet, 'ENTRADA')) {
                    return ['historical_entries_2024_2026', $index, $headers];
                }

                if (str_contains($normalizedSheet, 'SALIDA')) {
                    return ['historical_exits_2024_2026', $index, $headers];
                }
            }
        }

        if (str_contains($normalizedSheet, 'RENDICION')) {
            return ['rendition_report', null, []];
        }

        if (in_array(
            $normalizedSheet,
            ['ACUMUALDOS', 'ACUMULADOS', 'DETALLE', 'ENT X FECHA', 'SAL X DEPEND'],
            true
        )) {
            return ['reference_report', null, []];
        }

        $previewRows = array_slice($rows, 0, 12);
        foreach ($previewRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $value) {
                if (str_contains(
                    $this->normalizeHeader((string) $value),
                    'INVENTARIO DE UNIFORMES'
                )) {
                    return ['legacy_inventory_summary', null, []];
                }
            }
        }

        return ['unclassified', null, []];
    }

    private function headersFromRow(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized !== '') {
                $headers[$normalized] = (int) $column;
            }
        }

        return $headers;
    }

    private function normalizeInventoryRow(array $row, array $headers): ?array
    {
        $value = function (array $names) use ($row, $headers): string {
            foreach ($names as $name) {
                $key = $this->normalizeHeader($name);
                if (isset($headers[$key])) {
                    return trim((string) ($row[$headers[$key]] ?? ''));
                }
            }
            return '';
        };

        $code = $value(['CÓDIGO ISTMO']);
        $description = $value(['DESCRIPCIÓN ORIGINAL']);
        $product = $value(['PRODUCTO BASE']);
        $category = $value(['CATEGORÍA']);
        $size = $value(['TALLA NORMALIZADA', 'TALLA ALFABÉTICA', 'TALLA NUMÉRICA']);
        $sex = $value(['SEXO']);

        if (
            $code === ''
            && $description === ''
            && $product === ''
            && $category === ''
        ) {
            return null;
        }

        $issues = [];

        if ($code === '') {
            $issues[] = 'Código ISTMO faltante.';
        }

        if ($description === '' && $product === '') {
            $issues[] = 'Descripción o producto faltante.';
        }

        $initial = $this->numericOrNull($value(['INVENTARIO INICIAL']));
        $entries = $this->numericOrNull($value(['ENTRADAS']));
        $exits = $this->numericOrNull($value(['SALIDAS']));
        $recalculatedRaw = $value(['INVENTARIO RECALCULADO']);
        $recalculated = $this->numericOrNull($recalculatedRaw);

        if ($recalculated === null) {
            $issues[] = 'Inventario recalculado no numérico.';
        }

        $originStatus = $value(['ESTADO ORIGEN']);
        if ($originStatus !== '' && $this->normalizeHeader($originStatus) !== 'CORRECTO') {
            $issues[] = 'Incidencia declarada por el archivo: ' . $originStatus;
        }

        $mappingVariantId = null;
        if ($code !== '') {
            $matches = $this->repository->matchVariantsByOfficialCode($code);

            if (count($matches) === 1) {
                $mappingVariantId = (int) $matches[0]['id'];
            } elseif ($matches === []) {
                $issues[] = 'Código no encontrado en el catálogo actual.';
            } else {
                $issues[] = 'Código ambiguo: coincide con varias variantes.';
            }
        }

        return [
            'normalized_code' => $code !== '' ? $code : null,
            'normalized_description' => $description !== '' ? $description : null,
            'normalized_product' => $product !== '' ? $product : null,
            'normalized_category' => $category !== '' ? $category : null,
            'normalized_size' => $size !== '' ? $size : null,
            'normalized_sex' => $sex !== '' ? $sex : null,
            'normalized_initial_quantity' => $initial,
            'normalized_entries_quantity' => $entries,
            'normalized_exits_quantity' => $exits,
            'normalized_quantity' => $recalculated,
            'mapping_variant_id' => $mappingVariantId,
            'row_status' => $issues === [] ? 'ready' : 'review',
            'issues' => $issues,
        ];
    }

    private function normalizeControlInventoryRow(array $row, array $headers): ?array
    {
        $value = function (array $names) use ($row, $headers): string {
            foreach ($names as $name) {
                $key = $this->normalizeHeader($name);
                if (isset($headers[$key])) {
                    return trim((string) ($row[$headers[$key]] ?? ''));
                }
            }
            return '';
        };

        $code = $value(['CÓDIGO ISTMO']);
        $description = $value(['NOMBRE']);
        $category = $value(['TIPO']);

        if ($code === '' && $description === '' && $category === '') {
            return null;
        }

        $issues = [];
        if ($code === '') {
            $issues[] = 'Código ISTMO faltante.';
        }
        if ($description === '') {
            $issues[] = 'Nombre del artículo faltante.';
        }

        $initialRaw = $value(['INVENTARIO INICIAL']);
        $entriesRaw = $value(['ENTRADAS']);
        $exitsRaw = $value(['SALIDAS']);
        $inventoryRaw = $value(['INVENTARIO']);

        $initial = $this->numericOrNull($initialRaw);
        $entries = $this->numericOrNull($entriesRaw);
        $exits = $this->numericOrNull($exitsRaw);
        $inventory = $this->numericOrNull($inventoryRaw);

        if ($entries === null) {
            $issues[] = 'Entradas acumuladas no numéricas.';
        }
        if ($exits === null) {
            $issues[] = 'Salidas acumuladas no numéricas.';
        }
        if ($inventory === null) {
            $issues[] = 'Inventario calculado no numérico.';
        }

        if ($entries !== null && $exits !== null && $inventory !== null) {
            $expected = ($initial ?? 0.0) + $entries - $exits;
            if (abs($expected - $inventory) > 0.0005) {
                $issues[] = sprintf(
                    'La aritmética del inventario no coincide: %.3f + %.3f - %.3f = %.3f, archivo %.3f.',
                    $initial ?? 0.0,
                    $entries,
                    $exits,
                    $expected,
                    $inventory
                );
            }
        }

        [$mappingVariantId, $mappedSize, $mappingIssues] = $this->mapVariant($code);
        array_push($issues, ...$mappingIssues);

        return [
            'normalized_code' => $code !== '' ? $code : null,
            'normalized_description' => $description !== '' ? $description : null,
            'normalized_product' => $description !== '' ? $description : null,
            'normalized_category' => $category !== '' ? $category : null,
            'normalized_size' => $mappedSize,
            'normalized_sex' => null,
            'normalized_initial_quantity' => $initial,
            'normalized_entries_quantity' => $entries,
            'normalized_exits_quantity' => $exits,
            'normalized_quantity' => $inventory,
            'mapping_variant_id' => $mappingVariantId,
            'row_status' => $issues === [] ? 'ready' : 'review',
            'issues' => $issues,
        ];
    }

    private function normalizeHistoricalMovementRow(
        array $row,
        array $headers,
        string $direction
    ): ?array {
        $value = function (array $names) use ($row, $headers): string {
            foreach ($names as $name) {
                $key = $this->normalizeHeader($name);
                if (isset($headers[$key])) {
                    return trim((string) ($row[$headers[$key]] ?? ''));
                }
            }
            return '';
        };

        $date = $value(['FECHA']);
        $code = $value(['CÓDIGO ISTMO']);
        $product = $value(['PRODUCTO']);
        $quantityKey = $this->normalizeHeader('CANTIDAD');
        $quantityRaw = isset($headers[$quantityKey])
            ? ($row[$headers[$quantityKey]] ?? null)
            : null;
        $quantityText = trim((string) ($quantityRaw ?? ''));

        if (
            $code === ''
            && $date === ''
            && ($product === '' || $product === '0')
            && $quantityText === ''
        ) {
            return null;
        }

        $issues = [];

        if ($date === '') {
            $issues[] = 'Fecha del movimiento faltante.';
        }
        if ($code === '') {
            $issues[] = 'Código ISTMO faltante.';
        }
        if ($product === '' || $product === '0') {
            $issues[] = 'Producto del movimiento faltante.';
        }

        $quantity = $this->numericOrNull($quantityText);
        if ($quantity === null) {
            $issues[] = 'Cantidad del movimiento no numérica o faltante.';
        } elseif (is_string($quantityRaw)) {
            $issues[] = 'Cantidad almacenada como texto en el archivo fuente.';
        }

        [$mappingVariantId, $mappedSize, $mappingIssues] = $this->mapVariant($code);
        array_push($issues, ...$mappingIssues);

        $issues[] = $direction === 'entry'
            ? 'Entrada histórica conservada como referencia; Fase 13A no crea movimientos en el kardex.'
            : 'Salida histórica conservada como referencia; Fase 13A no crea movimientos en el kardex.';

        return [
            'normalized_code' => $code !== '' ? $code : null,
            'normalized_description' => $product !== '' && $product !== '0' ? $product : null,
            'normalized_product' => $product !== '' && $product !== '0' ? $product : null,
            'normalized_category' => null,
            'normalized_size' => $mappedSize,
            'normalized_sex' => null,
            'normalized_initial_quantity' => null,
            'normalized_entries_quantity' => $direction === 'entry' ? $quantity : null,
            'normalized_exits_quantity' => $direction === 'exit' ? $quantity : null,
            'normalized_quantity' => $quantity,
            'mapping_variant_id' => $mappingVariantId,
            'row_status' => 'review',
            'issues' => $issues,
        ];
    }

    private function normalizeReferenceRow(array $row, string $profile): ?array
    {
        $nonEmpty = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                $row
            ),
            static fn (string $value): bool => $value !== ''
        ));

        if ($nonEmpty === []) {
            return null;
        }

        $label = match ($profile) {
            'rendition_report' => 'Rendición histórica',
            'legacy_inventory_summary' => 'Resumen histórico de inventario',
            default => 'Reporte histórico de referencia',
        };

        return [
            'normalized_code' => null,
            'normalized_description' => mb_substr($nonEmpty[0], 0, 255),
            'normalized_product' => null,
            'normalized_category' => null,
            'normalized_size' => null,
            'normalized_sex' => null,
            'normalized_initial_quantity' => null,
            'normalized_entries_quantity' => null,
            'normalized_exits_quantity' => null,
            'normalized_quantity' => null,
            'mapping_variant_id' => null,
            'row_status' => 'review',
            'issues' => [
                $label . '; se conserva para consulta y no se archiva como stock ni como kardex.',
            ],
        ];
    }

    private function mapVariant(string $code): array
    {
        if ($code === '') {
            return [null, null, []];
        }

        $matches = $this->repository->matchVariantsByOfficialCode($code);

        if (count($matches) === 1) {
            return [
                (int) $matches[0]['id'],
                trim((string) ($matches[0]['size_name'] ?? '')) ?: null,
                [],
            ];
        }

        if ($matches === []) {
            return [null, null, ['Código no encontrado en el catálogo actual.']];
        }

        return [null, null, ['Código ambiguo: coincide con varias variantes.']];
    }

    private function validateWorkbookConsistency(int $batchId): void
    {
        $controlRows = $this->repository->rowsForProfile(
            $batchId,
            'inventory_control_2026'
        );

        if ($controlRows === []) {
            return;
        }

        $entryTotals = $this->repository->movementTotalsByCode(
            $batchId,
            'historical_entries_2024_2026'
        );
        $exitTotals = $this->repository->movementTotalsByCode(
            $batchId,
            'historical_exits_2024_2026'
        );

        if ($entryTotals === [] && $exitTotals === []) {
            return;
        }

        $sheetIds = [];

        foreach ($controlRows as $row) {
            $code = $this->normalizeCode((string) ($row['normalized_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $issues = json_decode((string) ($row['issues_json'] ?? ''), true);
            $issues = is_array($issues) ? array_values($issues) : [];

            $expectedEntries = $this->nullableFloat($row['normalized_entries_quantity'] ?? null);
            $expectedExits = $this->nullableFloat($row['normalized_exits_quantity'] ?? null);
            $actualEntries = $entryTotals[$code] ?? 0.0;
            $actualExits = $exitTotals[$code] ?? 0.0;

            if (
                $expectedEntries !== null
                && abs($expectedEntries - $actualEntries) > 0.0005
            ) {
                $issues[] = sprintf(
                    'Totales de entradas no coinciden con ENTRADAS: control %.3f vs detalle %.3f.',
                    $expectedEntries,
                    $actualEntries
                );
            }

            if (
                $expectedExits !== null
                && abs($expectedExits - $actualExits) > 0.0005
            ) {
                $issues[] = sprintf(
                    'Totales de salidas no coinciden con SALIDAS: control %.3f vs detalle %.3f.',
                    $expectedExits,
                    $actualExits
                );
            }

            if (count($issues) !== count(json_decode((string) ($row['issues_json'] ?? ''), true) ?: [])) {
                $this->repository->markRowForReview((int) $row['id'], $issues);
                $sheetIds[(int) $row['sheet_id']] = true;
            }
        }

        foreach (array_keys($sheetIds) as $sheetId) {
            $this->repository->refreshSheetCounters((int) $sheetId);
        }
    }

    private function normalizeCode(string $code): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($code)));
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return is_numeric((string) $value) ? (float) $value : null;
    }

    private function normalizeIssueRow(array $row): ?array
    {
        $nonEmpty = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                $row
            ),
            static fn (string $value): bool => $value !== ''
        ));

        if ($nonEmpty === []) {
            return null;
        }

        return [
            'normalized_code' => $nonEmpty[1] ?? null,
            'normalized_description' => $nonEmpty[2] ?? null,
            'normalized_product' => null,
            'normalized_category' => null,
            'normalized_size' => null,
            'normalized_sex' => null,
            'normalized_initial_quantity' => null,
            'normalized_entries_quantity' => null,
            'normalized_exits_quantity' => null,
            'normalized_quantity' => null,
            'mapping_variant_id' => null,
            'row_status' => 'review',
            'issues' => [
                'Fila procedente de la hoja de incidencias; conservar para revisión.',
            ],
        ];
    }

    private function normalizeUnknownRow(array $row): ?array
    {
        if ($this->rowIsBlank($row)) {
            return null;
        }

        return [
            'normalized_code' => null,
            'normalized_description' => null,
            'normalized_product' => null,
            'normalized_category' => null,
            'normalized_size' => null,
            'normalized_sex' => null,
            'normalized_initial_quantity' => null,
            'normalized_entries_quantity' => null,
            'normalized_exits_quantity' => null,
            'normalized_quantity' => null,
            'mapping_variant_id' => null,
            'row_status' => 'review',
            'issues' => [
                'Perfil de hoja no reconocido. No se importará automáticamente.',
            ],
        ];
    }

    private function validateUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Seleccione un archivo XLSX o CSV.');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('El archivo no pudo cargarse correctamente.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = trim((string) ($file['name'] ?? 'historico'));
        $sizeBytes = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('La carga recibida no es válida.');
        }

        if ($sizeBytes <= 0 || $sizeBytes > 30 * 1024 * 1024) {
            throw new InvalidArgumentException('El archivo debe pesar como máximo 30 MB.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Solo se permiten archivos XLSX o CSV.');
        }

        return [
            'tmp_name' => $tmpName,
            'original_name' => mb_substr($originalName, 0, 255),
            'size_bytes' => $sizeBytes,
            'extension' => $extension,
        ];
    }

    private function storeSourceFile(array $file, string $sha256): array
    {
        $directory = SINDESG_ROOT . '/storage/uploads/historical';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de históricos.');
        }

        $filename = $sha256 . '.' . $file['extension'];
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo histórico.');
        }

        return [
            'relative_path' => 'historical/' . $filename,
            'absolute_path' => $destination,
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii)) {
            $value = $ascii;
        }

        $value = mb_strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function numericOrNull(string $value): ?float
    {
        $value = trim(str_replace([',', ' '], ['', ''], $value));

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findFirstContentRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if (is_array($row) && !$this->rowIsBlank($row)) {
                return $index;
            }
        }

        return null;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
