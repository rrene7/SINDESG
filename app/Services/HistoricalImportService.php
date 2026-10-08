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
                'source_issues' => $this->normalizeIssueRow($row),
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
            return ['source_issues', $this->findFirstContentRow($rows), []];
        }

        $max = min(count($rows), 20);

        for ($index = 0; $index < $max; $index++) {
            $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
            $headers = [];

            foreach ($row as $column => $value) {
                $normalized = $this->normalizeHeader((string) $value);
                if ($normalized !== '') {
                    $headers[$normalized] = (int) $column;
                }
            }

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
        }

        return ['unclassified', null, []];
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
