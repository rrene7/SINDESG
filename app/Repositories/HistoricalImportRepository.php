<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class HistoricalImportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findBatchByHash(string $sha256): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM historical_import_batches WHERE file_sha256 = :sha LIMIT 1'
        );
        $statement->execute(['sha' => $sha256]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function createBatch(array $data): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO historical_import_batches
                (
                    batch_code,
                    source_filename,
                    stored_path,
                    file_extension,
                    file_size,
                    file_sha256,
                    uploaded_by_user_id,
                    uploaded_by_label_snapshot,
                    notes,
                    status
                )
            VALUES
                (
                    :batch_code,
                    :source_filename,
                    :stored_path,
                    :file_extension,
                    :file_size,
                    :file_sha256,
                    :uploaded_by_user_id,
                    :uploaded_by_label_snapshot,
                    :notes,
                    'staged'
                )
        SQL);

        $statement->execute([
            'batch_code' => 'TMP-HIS-' . strtoupper(bin2hex(random_bytes(5))),
            'source_filename' => $data['source_filename'],
            'stored_path' => $data['stored_path'],
            'file_extension' => $data['file_extension'],
            'file_size' => $data['file_size'],
            'file_sha256' => $data['file_sha256'],
            'uploaded_by_user_id' => $data['uploaded_by_user_id'],
            'uploaded_by_label_snapshot' => $data['uploaded_by_label_snapshot'],
            'notes' => $data['notes'],
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $code = sprintf('HIS-%06d', $id);

        $this->pdo->prepare(
            'UPDATE historical_import_batches SET batch_code = :code WHERE id = :id'
        )->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    public function createSheet(array $data): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO historical_import_sheets
                (
                    batch_id,
                    sheet_name,
                    profile,
                    header_row_number,
                    total_source_rows,
                    staged_rows,
                    ready_rows,
                    review_rows,
                    imported_rows
                )
            VALUES
                (
                    :batch_id,
                    :sheet_name,
                    :profile,
                    :header_row_number,
                    :total_source_rows,
                    0,
                    0,
                    0,
                    0
                )
        SQL);
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function insertRow(array $data): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO historical_import_rows
                (
                    batch_id,
                    sheet_id,
                    source_row_number,
                    row_profile,
                    raw_json,
                    normalized_code,
                    normalized_description,
                    normalized_product,
                    normalized_category,
                    normalized_size,
                    normalized_sex,
                    normalized_initial_quantity,
                    normalized_entries_quantity,
                    normalized_exits_quantity,
                    normalized_quantity,
                    mapping_variant_id,
                    row_status,
                    issues_json
                )
            VALUES
                (
                    :batch_id,
                    :sheet_id,
                    :source_row_number,
                    :row_profile,
                    :raw_json,
                    :normalized_code,
                    :normalized_description,
                    :normalized_product,
                    :normalized_category,
                    :normalized_size,
                    :normalized_sex,
                    :normalized_initial_quantity,
                    :normalized_entries_quantity,
                    :normalized_exits_quantity,
                    :normalized_quantity,
                    :mapping_variant_id,
                    :row_status,
                    :issues_json
                )
        SQL);
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function refreshSheetCounters(int $sheetId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE historical_import_sheets s
            SET
                staged_rows = (
                    SELECT COUNT(*)
                    FROM historical_import_rows r
                    WHERE r.sheet_id = s.id
                ),
                ready_rows = (
                    SELECT COUNT(*)
                    FROM historical_import_rows r
                    WHERE r.sheet_id = s.id AND r.row_status = 'ready'
                ),
                review_rows = (
                    SELECT COUNT(*)
                    FROM historical_import_rows r
                    WHERE r.sheet_id = s.id AND r.row_status = 'review'
                ),
                imported_rows = (
                    SELECT COUNT(*)
                    FROM historical_import_rows r
                    WHERE r.sheet_id = s.id AND r.row_status = 'imported'
                )
            WHERE s.id = :sheet_id
        SQL);
        $statement->execute(['sheet_id' => $sheetId]);
    }

    public function listBatches(int $limit = 80): array
    {
        $limit = max(1, min($limit, 200));

        return $this->pdo->query('
            SELECT
                b.*,
                COUNT(DISTINCT s.id) AS sheet_count,
                COUNT(r.id) AS row_count,
                COALESCE(SUM(r.row_status = "ready"), 0) AS ready_count,
                COALESCE(SUM(r.row_status = "review"), 0) AS review_count,
                COALESCE(SUM(r.row_status = "imported"), 0) AS imported_count
            FROM historical_import_batches b
            LEFT JOIN historical_import_sheets s ON s.batch_id = b.id
            LEFT JOIN historical_import_rows r ON r.sheet_id = s.id
            GROUP BY b.id
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function batch(int $batchId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                b.*,
                COUNT(DISTINCT s.id) AS sheet_count,
                COUNT(r.id) AS row_count,
                COALESCE(SUM(r.row_status = 'ready'), 0) AS ready_count,
                COALESCE(SUM(r.row_status = 'review'), 0) AS review_count,
                COALESCE(SUM(r.row_status = 'imported'), 0) AS imported_count,
                COALESCE(SUM(r.row_status = 'skipped'), 0) AS skipped_count
            FROM historical_import_batches b
            LEFT JOIN historical_import_sheets s ON s.batch_id = b.id
            LEFT JOIN historical_import_rows r ON r.sheet_id = s.id
            WHERE b.id = :id
            GROUP BY b.id
        SQL);
        $statement->execute(['id' => $batchId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function sheets(int $batchId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM historical_import_sheets
             WHERE batch_id = :batch_id
             ORDER BY id'
        );
        $statement->execute(['batch_id' => $batchId]);

        return $statement->fetchAll();
    }

    public function rows(
        int $batchId,
        ?int $sheetId,
        string $status,
        int $limit = 500
    ): array {
        $limit = max(1, min($limit, 1000));
        $where = ['r.batch_id = :batch_id'];
        $params = ['batch_id' => $batchId];

        if ($sheetId !== null && $sheetId > 0) {
            $where[] = 'r.sheet_id = :sheet_id';
            $params['sheet_id'] = $sheetId;
        }

        if (in_array($status, ['ready', 'review', 'imported', 'skipped'], true)) {
            $where[] = 'r.row_status = :row_status';
            $params['row_status'] = $status;
        }

        $statement = $this->pdo->prepare('
            SELECT
                r.*,
                s.sheet_name,
                s.profile AS sheet_profile,
                pv.internal_code AS mapped_internal_code,
                pv.institutional_code AS mapped_institutional_code,
                p.name AS mapped_product_name,
                sz.name AS mapped_size_name
            FROM historical_import_rows r
            INNER JOIN historical_import_sheets s ON s.id = r.sheet_id
            LEFT JOIN product_variants pv ON pv.id = r.mapping_variant_id
            LEFT JOIN products p ON p.id = pv.product_id
            LEFT JOIN sizes sz ON sz.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY s.id, r.source_row_number
            LIMIT ' . $limit
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function matchVariantsByOfficialCode(string $code): array
    {
        $search = $this->normalizeCode($code);
        if ($search === '') {
            return [];
        }

        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                pv.id,
                pv.internal_code,
                pv.institutional_code,
                p.name AS product_name,
                s.name AS size_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE pv.status = 'active'
              AND p.status <> 'deleted'
              AND (
                    UPPER(REPLACE(COALESCE(pv.institutional_code, ''), ' ', '')) = :official_code
                 OR UPPER(REPLACE(COALESCE(pv.internal_code, ''), ' ', '')) = :internal_code
              )
            ORDER BY pv.id
            LIMIT 10
        SQL);
        $statement->execute([
            'official_code' => $search,
            'internal_code' => $search,
        ]);

        return $statement->fetchAll();
    }

    public function historicalResolutionSchemaReady(): bool
    {
        $exists = $this->pdo->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'historical_import_resolutions'"
        )->fetchColumn();

        return (int) $exists === 1;
    }

    public function controlDiscrepancyRows(int $batchId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                r.*,
                s.sheet_name,
                pv.internal_code AS mapped_internal_code,
                pv.institutional_code AS mapped_institutional_code,
                p.name AS mapped_product_name,
                sz.name AS mapped_size_name
            FROM historical_import_rows r
            INNER JOIN historical_import_sheets s ON s.id = r.sheet_id
            LEFT JOIN product_variants pv ON pv.id = r.mapping_variant_id
            LEFT JOIN products p ON p.id = pv.product_id
            LEFT JOIN sizes sz ON sz.id = pv.size_id
            WHERE r.batch_id = :batch_id
              AND r.row_profile = 'inventory_control_2026'
              AND r.row_status = 'review'
              AND r.mapping_variant_id IS NOT NULL
              AND (
                    r.issues_json LIKE '%Totales de entradas no coinciden con ENTRADAS:%'
                 OR r.issues_json LIKE '%Totales de salidas no coinciden con SALIDAS:%'
                 OR r.issues_json LIKE '%La aritmética del inventario no coincide:%'
              )
            ORDER BY r.source_row_number
        SQL);
        $statement->execute(['batch_id' => $batchId]);

        return $statement->fetchAll();
    }

    public function resolveControlRow(
        int $batchId,
        int $rowId,
        array $resolution,
        array $actor
    ): array {
        if (!$this->historicalResolutionSchemaReady()) {
            throw new RuntimeException(
                'Falta aplicar database/schema/018_historical_reconciliation.sql.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $batch = $this->lockBatch($batchId);
            if (!in_array($batch['status'], ['staged', 'partially_imported'], true)) {
                throw new RuntimeException(
                    'El lote ya no admite conciliaciones históricas.'
                );
            }

            $statement = $this->pdo->prepare(
                'SELECT *
                 FROM historical_import_rows
                 WHERE id = :id
                   AND batch_id = :batch_id
                 FOR UPDATE'
            );
            $statement->execute([
                'id' => $rowId,
                'batch_id' => $batchId,
            ]);
            $row = $statement->fetch();

            if (!$row) {
                throw new RuntimeException('La fila histórica no existe.');
            }

            if (
                (string) $row['row_profile'] !== 'inventory_control_2026'
                || (string) $row['row_status'] !== 'review'
                || empty($row['mapping_variant_id'])
            ) {
                throw new RuntimeException(
                    'La fila no está disponible para conciliación controlada.'
                );
            }

            $existing = $this->pdo->prepare(
                'SELECT id
                 FROM historical_import_resolutions
                 WHERE import_row_id = :row_id
                 LIMIT 1'
            );
            $existing->execute(['row_id' => $rowId]);

            if ($existing->fetchColumn() !== false) {
                throw new RuntimeException('Esta fila histórica ya fue conciliada.');
            }

            $issues = json_decode((string) ($row['issues_json'] ?? ''), true);
            $issues = is_array($issues) ? array_values($issues) : [];

            $remainingIssues = array_values(array_filter(
                $issues,
                static fn ($issue): bool =>
                    !str_starts_with(
                        (string) $issue,
                        'Totales de salidas no coinciden con SALIDAS:'
                    )
            ));

            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO historical_import_resolutions
                    (
                        batch_id,
                        import_row_id,
                        resolution_type,
                        official_code_snapshot,
                        original_initial_quantity,
                        original_entries_quantity,
                        original_exits_quantity,
                        original_recalculated_quantity,
                        resolved_initial_quantity,
                        resolved_entries_quantity,
                        resolved_exits_quantity,
                        resolved_recalculated_quantity,
                        source_reference,
                        reason,
                        resolved_by_user_id,
                        resolved_by_label_snapshot
                    )
                VALUES
                    (
                        :batch_id,
                        :import_row_id,
                        'normalized_source',
                        :official_code_snapshot,
                        :original_initial_quantity,
                        :original_entries_quantity,
                        :original_exits_quantity,
                        :original_recalculated_quantity,
                        :resolved_initial_quantity,
                        :resolved_entries_quantity,
                        :resolved_exits_quantity,
                        :resolved_recalculated_quantity,
                        :source_reference,
                        :reason,
                        :resolved_by_user_id,
                        :resolved_by_label_snapshot
                    )
            SQL);
            $insert->execute([
                'batch_id' => $batchId,
                'import_row_id' => $rowId,
                'official_code_snapshot' => (string) ($row['normalized_code'] ?? ''),
                'original_initial_quantity' => $row['normalized_initial_quantity'],
                'original_entries_quantity' => $row['normalized_entries_quantity'],
                'original_exits_quantity' => $row['normalized_exits_quantity'],
                'original_recalculated_quantity' => $row['normalized_quantity'],
                'resolved_initial_quantity' => $resolution['initial_quantity'],
                'resolved_entries_quantity' => $resolution['entries_quantity'],
                'resolved_exits_quantity' => $resolution['exits_quantity'],
                'resolved_recalculated_quantity' => $resolution['recalculated_quantity'],
                'source_reference' => $resolution['source_reference'],
                'reason' => $resolution['reason'],
                'resolved_by_user_id' => $actor['id'],
                'resolved_by_label_snapshot' => $actor['display_name'],
            ]);

            $update = $this->pdo->prepare(
                'UPDATE historical_import_rows
                 SET normalized_initial_quantity = :initial_quantity,
                     normalized_entries_quantity = :entries_quantity,
                     normalized_exits_quantity = :exits_quantity,
                     normalized_quantity = :recalculated_quantity,
                     row_status = :row_status,
                     issues_json = :issues_json
                 WHERE id = :id'
            );
            $update->execute([
                'initial_quantity' => $resolution['initial_quantity'],
                'entries_quantity' => $resolution['entries_quantity'],
                'exits_quantity' => $resolution['exits_quantity'],
                'recalculated_quantity' => $resolution['recalculated_quantity'],
                'row_status' => $remainingIssues === [] ? 'ready' : 'review',
                'issues_json' => $remainingIssues === []
                    ? null
                    : (json_encode(
                        $remainingIssues,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ) ?: '[]'),
                'id' => $rowId,
            ]);

            $this->refreshSheetCounters((int) $row['sheet_id']);

            $this->pdo->commit();

            return [
                'row_id' => $rowId,
                'code' => (string) ($row['normalized_code'] ?? ''),
                'original_exits' => $row['normalized_exits_quantity'],
                'resolved_exits' => $resolution['exits_quantity'],
                'resolved_quantity' => $resolution['recalculated_quantity'],
                'row_status' => $remainingIssues === [] ? 'ready' : 'review',
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function mappingDiagnostics(int $batchId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(mapping_variant_id IS NOT NULL), 0) AS mapped,
                COALESCE(SUM(
                    mapping_variant_id IS NULL
                    AND normalized_code IS NOT NULL
                    AND normalized_code <> ''
                ), 0) AS unmapped,
                COALESCE(SUM(row_status = 'ready'), 0) AS ready,
                COALESCE(SUM(row_status = 'review'), 0) AS review,
                COALESCE(SUM(issues_json LIKE '%Código no encontrado en el catálogo actual.%'), 0) AS missing_catalog,
                COALESCE(SUM(issues_json LIKE '%Código ambiguo:%'), 0) AS ambiguous,
                COALESCE(SUM(issues_json LIKE '%Totales de entradas no coinciden con ENTRADAS:%'), 0) AS entry_mismatch,
                COALESCE(SUM(issues_json LIKE '%Totales de salidas no coinciden con SALIDAS:%'), 0) AS exit_mismatch,
                COALESCE(SUM(issues_json LIKE '%La aritmética del inventario no coincide:%'), 0) AS arithmetic_mismatch
            FROM historical_import_rows
            WHERE batch_id = :batch_id
              AND row_profile = 'inventory_control_2026'
        SQL);
        $statement->execute(['batch_id' => $batchId]);
        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'mapped' => (int) ($row['mapped'] ?? 0),
            'unmapped' => (int) ($row['unmapped'] ?? 0),
            'ready' => (int) ($row['ready'] ?? 0),
            'review' => (int) ($row['review'] ?? 0),
            'missing_catalog' => (int) ($row['missing_catalog'] ?? 0),
            'ambiguous' => (int) ($row['ambiguous'] ?? 0),
            'entry_mismatch' => (int) ($row['entry_mismatch'] ?? 0),
            'exit_mismatch' => (int) ($row['exit_mismatch'] ?? 0),
            'arithmetic_mismatch' => (int) ($row['arithmetic_mismatch'] ?? 0),
        ];
    }

    public function unmappedControlExamples(int $batchId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));

        $statement = $this->pdo->prepare(
            'SELECT normalized_code, normalized_description, normalized_category, issues_json
             FROM historical_import_rows
             WHERE batch_id = :batch_id
               AND row_profile = "inventory_control_2026"
               AND mapping_variant_id IS NULL
             ORDER BY source_row_number
             LIMIT ' . $limit
        );
        $statement->execute(['batch_id' => $batchId]);

        return $statement->fetchAll();
    }

    public function historicalCatalogStatus(string $sourceName): array
    {
        $empty = [
            'schema_ready' => false,
            'categories' => 0,
            'subcategories' => 0,
            'products' => 0,
            'sizes' => 0,
            'variants' => 0,
        ];

        $exists = $this->pdo->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'historical_catalog_mappings'"
        )->fetchColumn();

        if ((int) $exists !== 1) {
            return $empty;
        }

        $statement = $this->pdo->prepare(
            'SELECT entity_type, COUNT(*) AS total
             FROM historical_catalog_mappings
             WHERE source_name = :source_name
             GROUP BY entity_type'
        );
        $statement->execute(['source_name' => $sourceName]);

        $status = $empty;
        $status['schema_ready'] = true;

        foreach ($statement->fetchAll() as $row) {
            $type = (string) ($row['entity_type'] ?? '');
            if ($type === 'category') {
                $status['categories'] = (int) ($row['total'] ?? 0);
            } elseif ($type === 'subcategory') {
                $status['subcategories'] = (int) ($row['total'] ?? 0);
            } elseif (array_key_exists($type . 's', $status)) {
                $status[$type . 's'] = (int) ($row['total'] ?? 0);
            }
        }

        return $status;
    }

    public function prepareHistoricalCatalog(array $dataset): array
    {
        $sourceName = trim((string) ($dataset['source'] ?? ''));
        if ($sourceName === '') {
            throw new RuntimeException('La fuente normalizada del catálogo no está identificada.');
        }

        $status = $this->historicalCatalogStatus($sourceName);
        if (!$status['schema_ready']) {
            throw new RuntimeException(
                'Falta aplicar database/schema/017_historical_catalog_preparation.sql.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $categoryIds = [];
            foreach (($dataset['categories'] ?? []) as $index => $category) {
                $sourceKey = trim((string) ($category['id'] ?? ''));
                $name = trim((string) ($category['name'] ?? ''));

                if ($sourceKey === '' || $name === '') {
                    throw new RuntimeException('Categoría histórica incompleta.');
                }

                $existing = $this->pdo->prepare(
                    'SELECT id, name FROM categories WHERE code = :code LIMIT 1'
                );
                $existing->execute(['code' => $sourceKey]);
                $row = $existing->fetch();

                if ($row && $this->catalogText((string) $row['name']) !== $this->catalogText($name)) {
                    throw new RuntimeException(
                        'El código de categoría ' . $sourceKey . ' ya existe con otro nombre.'
                    );
                }

                $slug = 'historico-' . strtolower($sourceKey);
                $statement = $this->pdo->prepare(
                    'INSERT INTO categories
                        (code, slug, name, description, sort_order, is_active)
                     VALUES
                        (:code, :slug, :name, :description, :sort_order, 1)
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        description = VALUES(description),
                        sort_order = VALUES(sort_order),
                        is_active = 1'
                );
                $statement->execute([
                    'code' => $sourceKey,
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Catálogo histórico normalizado 2026 · ' . $sourceKey,
                    'sort_order' => 600 + (($index + 1) * 10),
                ]);

                $select = $this->pdo->prepare(
                    'SELECT id FROM categories WHERE code = :code LIMIT 1'
                );
                $select->execute(['code' => $sourceKey]);
                $categoryId = (int) $select->fetchColumn();
                $categoryIds[$sourceKey] = $categoryId;

                $this->upsertHistoricalCatalogMapping(
                    $sourceName,
                    'category',
                    $sourceKey,
                    $categoryId,
                    ['name' => $name]
                );
            }

            foreach (($dataset['subcategories'] ?? []) as $subcategory) {
                $sourceKey = trim((string) ($subcategory['id'] ?? ''));
                if ($sourceKey === '') {
                    continue;
                }

                $this->upsertHistoricalCatalogMapping(
                    $sourceName,
                    'subcategory',
                    $sourceKey,
                    null,
                    [
                        'category_id' => $subcategory['category_id'] ?? null,
                        'name' => $subcategory['name'] ?? null,
                        'note' => 'SINDESG aún no tiene tabla operativa de subcategorías; se conserva como metadata.',
                    ]
                );
            }

            $sizeIds = [];
            foreach (($dataset['sizes'] ?? []) as $index => $size) {
                $sourceKey = trim((string) ($size['id'] ?? ''));
                $name = trim((string) ($size['name'] ?? ''));

                if ($sourceKey === '' || $name === '') {
                    throw new RuntimeException('Talla histórica incompleta.');
                }

                $existing = $this->pdo->prepare(
                    'SELECT id, name FROM sizes WHERE code = :code LIMIT 1'
                );
                $existing->execute(['code' => $sourceKey]);
                $row = $existing->fetch();

                if ($row && $this->catalogText((string) $row['name']) !== $this->catalogText($name)) {
                    throw new RuntimeException(
                        'El código de talla ' . $sourceKey . ' ya existe con otro nombre.'
                    );
                }

                $statement = $this->pdo->prepare(
                    'INSERT INTO sizes (code, name, sort_order, is_active)
                     VALUES (:code, :name, :sort_order, 1)
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        sort_order = VALUES(sort_order),
                        is_active = 1'
                );
                $statement->execute([
                    'code' => $sourceKey,
                    'name' => $name,
                    'sort_order' => 1000 + (($index + 1) * 10),
                ]);

                $select = $this->pdo->prepare(
                    'SELECT id FROM sizes WHERE code = :code LIMIT 1'
                );
                $select->execute(['code' => $sourceKey]);
                $sizeId = (int) $select->fetchColumn();
                $sizeIds[$sourceKey] = $sizeId;

                $this->upsertHistoricalCatalogMapping(
                    $sourceName,
                    'size',
                    $sourceKey,
                    $sizeId,
                    ['name' => $name]
                );
            }

            $productIds = [];
            foreach (($dataset['products'] ?? []) as $product) {
                $sourceKey = trim((string) ($product['id'] ?? ''));
                $categoryKey = trim((string) ($product['category'] ?? ''));
                $name = trim((string) ($product['name'] ?? ''));

                if (
                    $sourceKey === ''
                    || $name === ''
                    || !isset($categoryIds[$categoryKey])
                ) {
                    throw new RuntimeException(
                        'Producto histórico incompleto o sin categoría válida: ' . $sourceKey
                    );
                }

                $existing = $this->pdo->prepare(
                    'SELECT id, name FROM products WHERE internal_code = :code LIMIT 1'
                );
                $existing->execute(['code' => $sourceKey]);
                $row = $existing->fetch();

                if ($row && $this->catalogText((string) $row['name']) !== $this->catalogText($name)) {
                    throw new RuntimeException(
                        'El código de producto ' . $sourceKey . ' ya existe con otro nombre.'
                    );
                }

                $subcategoryName = trim((string) ($product['subcategory_name'] ?? ''));
                $brandModel = trim((string) ($product['brand'] ?? ''));
                $description = 'Fuente histórica normalizada 2026';
                if ($subcategoryName !== '') {
                    $description .= ' · Subcategoría: ' . $subcategoryName;
                }
                if ($brandModel !== '' && $this->catalogText($brandModel) !== 'NO ESPECIFICADO') {
                    $description .= ' · Marca/modelo: ' . $brandModel;
                }

                $statement = $this->pdo->prepare(
                    'INSERT INTO products
                        (category_id, internal_code, name, description, sex, color, status)
                     VALUES
                        (:category_id, :internal_code, :name, :description, :sex, :color, "active")
                     ON DUPLICATE KEY UPDATE
                        category_id = VALUES(category_id),
                        name = VALUES(name),
                        description = VALUES(description),
                        sex = VALUES(sex),
                        color = VALUES(color),
                        status = "active"'
                );
                $statement->execute([
                    'category_id' => $categoryIds[$categoryKey],
                    'internal_code' => $sourceKey,
                    'name' => $name,
                    'description' => $description,
                    'sex' => $this->historicalProductSex((string) ($product['sexes'] ?? '')),
                    'color' => $this->nullableCatalogText($product['colors'] ?? null),
                ]);

                $select = $this->pdo->prepare(
                    'SELECT id FROM products WHERE internal_code = :code LIMIT 1'
                );
                $select->execute(['code' => $sourceKey]);
                $productId = (int) $select->fetchColumn();
                $productIds[$sourceKey] = $productId;

                $this->upsertHistoricalCatalogMapping(
                    $sourceName,
                    'product',
                    $sourceKey,
                    $productId,
                    [
                        'category' => $categoryKey,
                        'subcategory' => $product['subcategory'] ?? null,
                        'subcategory_name' => $subcategoryName !== '' ? $subcategoryName : null,
                        'brand_model' => $brandModel !== '' ? $brandModel : null,
                    ]
                );
            }

            foreach (($dataset['variants'] ?? []) as $variant) {
                $sourceKey = trim((string) ($variant['id'] ?? ''));
                $productKey = trim((string) ($variant['product'] ?? ''));
                $sizeKey = trim((string) ($variant['size'] ?? ''));
                $officialCode = trim((string) ($variant['code'] ?? ''));

                if (
                    $sourceKey === ''
                    || $officialCode === ''
                    || !isset($productIds[$productKey])
                    || !isset($sizeIds[$sizeKey])
                ) {
                    throw new RuntimeException(
                        'Variante histórica incompleta: ' . ($sourceKey !== '' ? $sourceKey : 'sin ID')
                    );
                }

                $normalizedCode = $this->normalizeCode($officialCode);
                $conflict = $this->pdo->prepare(
                    'SELECT id, internal_code, institutional_code
                     FROM product_variants
                     WHERE status = "active"
                       AND UPPER(REPLACE(COALESCE(institutional_code, ""), " ", "")) = :code
                       AND COALESCE(internal_code, "") <> :internal_code
                     LIMIT 1'
                );
                $conflict->execute([
                    'code' => $normalizedCode,
                    'internal_code' => $sourceKey,
                ]);
                $conflictRow = $conflict->fetch();

                if ($conflictRow) {
                    throw new RuntimeException(
                        'El código ISTMO ' . $officialCode
                        . ' ya pertenece a otra variante activa ('
                        . (string) ($conflictRow['internal_code'] ?? $conflictRow['id'])
                        . ').'
                    );
                }

                $brandId = $this->findOrCreateHistoricalBrand(
                    (string) ($variant['brand'] ?? '')
                );

                $existing = $this->pdo->prepare(
                    'SELECT id FROM product_variants WHERE internal_code = :code LIMIT 1'
                );
                $existing->execute(['code' => $sourceKey]);
                $variantId = (int) ($existing->fetchColumn() ?: 0);

                if ($variantId > 0) {
                    $statement = $this->pdo->prepare(
                        'UPDATE product_variants
                         SET product_id = :product_id,
                             size_id = :size_id,
                             brand_id = :brand_id,
                             institutional_code = :institutional_code,
                             color = :color,
                             status = "active"
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'product_id' => $productIds[$productKey],
                        'size_id' => $sizeIds[$sizeKey],
                        'brand_id' => $brandId,
                        'institutional_code' => $officialCode,
                        'color' => $this->nullableCatalogText($variant['color'] ?? null),
                        'id' => $variantId,
                    ]);
                } else {
                    $statement = $this->pdo->prepare(
                        'INSERT INTO product_variants
                            (
                                product_id,
                                size_id,
                                brand_id,
                                internal_code,
                                institutional_code,
                                supplier_code,
                                color,
                                status
                            )
                         VALUES
                            (
                                :product_id,
                                :size_id,
                                :brand_id,
                                :internal_code,
                                :institutional_code,
                                NULL,
                                :color,
                                "active"
                            )'
                    );
                    $statement->execute([
                        'product_id' => $productIds[$productKey],
                        'size_id' => $sizeIds[$sizeKey],
                        'brand_id' => $brandId,
                        'internal_code' => $sourceKey,
                        'institutional_code' => $officialCode,
                        'color' => $this->nullableCatalogText($variant['color'] ?? null),
                    ]);
                    $variantId = (int) $this->pdo->lastInsertId();
                }

                $this->upsertHistoricalCatalogMapping(
                    $sourceName,
                    'variant',
                    $sourceKey,
                    $variantId,
                    [
                        'institutional_code' => $officialCode,
                        'search_code' => $variant['search'] ?? null,
                        'product' => $productKey,
                        'size' => $sizeKey,
                        'brand_model' => $variant['brand'] ?? null,
                        'sex' => $variant['sex'] ?? null,
                        'sleeve' => $variant['sleeve'] ?? null,
                        'unit' => $variant['unit'] ?? null,
                        'description' => $variant['description'] ?? null,
                    ]
                );
            }

            $this->pdo->commit();

            return [
                'categories' => count($dataset['categories'] ?? []),
                'subcategories' => count($dataset['subcategories'] ?? []),
                'products' => count($dataset['products'] ?? []),
                'sizes' => count($dataset['sizes'] ?? []),
                'variants' => count($dataset['variants'] ?? []),
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function upsertHistoricalCatalogMapping(
        string $sourceName,
        string $entityType,
        string $sourceKey,
        ?int $targetId,
        array $metadata
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO historical_catalog_mappings
                (source_name, entity_type, source_key, target_id, metadata_json)
             VALUES
                (:source_name, :entity_type, :source_key, :target_id, :metadata_json)
             ON DUPLICATE KEY UPDATE
                target_id = VALUES(target_id),
                metadata_json = VALUES(metadata_json)'
        );
        $statement->execute([
            'source_name' => $sourceName,
            'entity_type' => $entityType,
            'source_key' => $sourceKey,
            'target_id' => $targetId,
            'metadata_json' => json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '{}',
        ]);
    }

    private function findOrCreateHistoricalBrand(string $name): ?int
    {
        $name = trim($name);
        if ($name === '' || $this->catalogText($name) === 'NO ESPECIFICADO') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM brands WHERE name = :name LIMIT 1'
        );
        $statement->execute(['name' => $name]);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO brands (name, is_active) VALUES (:name, 1)'
        );
        $statement->execute(['name' => $name]);

        return (int) $this->pdo->lastInsertId();
    }

    private function historicalProductSex(string $value): string
    {
        $normalized = $this->catalogText($value);

        if ($normalized === 'FEMENINO') {
            return 'femenino';
        }
        if ($normalized === 'MASCULINO') {
            return 'masculino';
        }
        if ($normalized === 'UNISEX') {
            return 'unisex';
        }
        if (str_contains($normalized, ',')) {
            return 'mixto';
        }

        return 'no_aplica';
    }

    private function nullableCatalogText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' && $this->catalogText($value) !== 'NO ESPECIFICADO'
            ? $value
            : null;
    }

    private function catalogText(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        return strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
    }

    public function rowsForProfile(int $batchId, string $profile): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, sheet_id, normalized_code, normalized_entries_quantity,
                    normalized_exits_quantity, issues_json, row_status
             FROM historical_import_rows
             WHERE batch_id = :batch_id
               AND row_profile = :profile
             ORDER BY id'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'profile' => $profile,
        ]);

        return $statement->fetchAll();
    }

    public function movementTotalsByCode(int $batchId, string $profile): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                UPPER(REPLACE(TRIM(normalized_code), " ", "")) AS normalized_search_code,
                SUM(normalized_quantity) AS total_quantity
             FROM historical_import_rows
             WHERE batch_id = :batch_id
               AND row_profile = :profile
               AND normalized_code IS NOT NULL
               AND normalized_code <> ""
               AND normalized_quantity IS NOT NULL
             GROUP BY UPPER(REPLACE(TRIM(normalized_code), " ", ""))'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'profile' => $profile,
        ]);

        $totals = [];
        foreach ($statement->fetchAll() as $row) {
            $code = trim((string) ($row['normalized_search_code'] ?? ''));
            if ($code !== '') {
                $totals[$code] = (float) ($row['total_quantity'] ?? 0);
            }
        }

        return $totals;
    }

    public function markRowForReview(int $rowId, array $issues): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE historical_import_rows
             SET row_status = "review",
                 issues_json = :issues_json
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $rowId,
            'issues_json' => json_encode(
                array_values(array_unique($issues)),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '[]',
        ]);
    }

    public function referenceReviewSummary(int $batchId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                row_profile,
                COUNT(*) AS total
             FROM historical_import_rows
             WHERE batch_id = :batch_id
               AND row_status = "review"
               AND row_profile NOT IN ("inventory_normalized_2026", "inventory_control_2026")
             GROUP BY row_profile
             ORDER BY row_profile'
        );
        $statement->execute(['batch_id' => $batchId]);

        return $statement->fetchAll();
    }

    public function finalizeHistoricalReferences(int $batchId): array
    {
        $this->pdo->beginTransaction();

        try {
            $batch = $this->lockBatch($batchId);

            if ($batch['status'] === 'cancelled') {
                throw new RuntimeException('El lote está cancelado.');
            }

            $control = $this->pdo->prepare(
                'SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(row_status = "imported"), 0) AS imported,
                    COALESCE(SUM(row_status = "ready"), 0) AS ready,
                    COALESCE(SUM(row_status = "review"), 0) AS review
                 FROM historical_import_rows
                 WHERE batch_id = :batch_id
                   AND row_profile IN ("inventory_normalized_2026", "inventory_control_2026")'
            );
            $control->execute(['batch_id' => $batchId]);
            $controlStatus = $control->fetch() ?: [];

            $controlTotal = (int) ($controlStatus['total'] ?? 0);
            $controlImported = (int) ($controlStatus['imported'] ?? 0);
            $controlReady = (int) ($controlStatus['ready'] ?? 0);
            $controlReview = (int) ($controlStatus['review'] ?? 0);

            if ($controlTotal === 0) {
                throw new RuntimeException(
                    'El lote no contiene un Control de inventario archivado.'
                );
            }

            if (
                $controlReady > 0
                || $controlReview > 0
                || $controlImported !== $controlTotal
            ) {
                throw new RuntimeException(
                    'Primero archive todas las filas del Control de inventario antes de cerrar las referencias históricas.'
                );
            }

            $sheetStatement = $this->pdo->prepare(
                'SELECT DISTINCT sheet_id
                 FROM historical_import_rows
                 WHERE batch_id = :batch_id
                   AND row_status = "review"
                   AND row_profile NOT IN ("inventory_normalized_2026", "inventory_control_2026")'
            );
            $sheetStatement->execute(['batch_id' => $batchId]);
            $sheetIds = array_map(
                static fn (array $row): int => (int) $row['sheet_id'],
                $sheetStatement->fetchAll()
            );

            $update = $this->pdo->prepare(
                'UPDATE historical_import_rows
                 SET row_status = "skipped"
                 WHERE batch_id = :batch_id
                   AND row_status = "review"
                   AND row_profile NOT IN ("inventory_normalized_2026", "inventory_control_2026")'
            );
            $update->execute(['batch_id' => $batchId]);
            $skipped = $update->rowCount();

            foreach ($sheetIds as $sheetId) {
                $this->refreshSheetCounters($sheetId);
            }

            $remainingReady = $this->countRowsByStatus($batchId, 'ready');
            $remainingReview = $this->countRowsByStatus($batchId, 'review');

            if ($remainingReady !== 0 || $remainingReview !== 0) {
                throw new RuntimeException(
                    'El lote aún contiene filas pendientes y no puede cerrarse.'
                );
            }

            $this->pdo->prepare(
                'UPDATE historical_import_batches
                 SET status = "imported"
                 WHERE id = :id'
            )->execute(['id' => $batchId]);

            $this->pdo->commit();

            return [
                'control_imported' => $controlImported,
                'references_skipped' => $skipped,
                'remaining_ready' => 0,
                'remaining_review' => 0,
                'batch_status' => 'imported',
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function archiveReadySnapshots(int $batchId): array
    {
        $this->pdo->beginTransaction();

        try {
            $batch = $this->lockBatch($batchId);
            if ($batch['status'] === 'cancelled') {
                throw new RuntimeException('El lote está cancelado.');
            }

            $rows = $this->pdo->prepare(<<<'SQL'
                SELECT
                    r.*,
                    s.sheet_name,
                    b.source_filename
                FROM historical_import_rows r
                INNER JOIN historical_import_sheets s ON s.id = r.sheet_id
                INNER JOIN historical_import_batches b ON b.id = r.batch_id
                WHERE r.batch_id = :batch_id
                  AND r.row_status = 'ready'
                  AND r.row_profile IN ('inventory_normalized_2026', 'inventory_control_2026')
                  AND r.mapping_variant_id IS NOT NULL
                ORDER BY r.id
                FOR UPDATE
            SQL);
            $rows->execute(['batch_id' => $batchId]);
            $ready = $rows->fetchAll();

            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO historical_stock_snapshots
                    (
                        batch_id,
                        import_row_id,
                        product_variant_id,
                        source_filename,
                        source_sheet,
                        source_row_number,
                        official_code_snapshot,
                        description_snapshot,
                        size_snapshot,
                        initial_quantity,
                        entries_quantity,
                        exits_quantity,
                        recalculated_quantity
                    )
                VALUES
                    (
                        :batch_id,
                        :import_row_id,
                        :product_variant_id,
                        :source_filename,
                        :source_sheet,
                        :source_row_number,
                        :official_code_snapshot,
                        :description_snapshot,
                        :size_snapshot,
                        :initial_quantity,
                        :entries_quantity,
                        :exits_quantity,
                        :recalculated_quantity
                    )
                ON DUPLICATE KEY UPDATE import_row_id = VALUES(import_row_id)
            SQL);

            $updateRow = $this->pdo->prepare(
                'UPDATE historical_import_rows
                 SET row_status = "imported", imported_at = NOW()
                 WHERE id = :id'
            );

            $sheetIds = [];
            $imported = 0;

            foreach ($ready as $row) {
                $insert->execute([
                    'batch_id' => $batchId,
                    'import_row_id' => $row['id'],
                    'product_variant_id' => $row['mapping_variant_id'],
                    'source_filename' => $row['source_filename'],
                    'source_sheet' => $row['sheet_name'],
                    'source_row_number' => $row['source_row_number'],
                    'official_code_snapshot' => $row['normalized_code'],
                    'description_snapshot' => $row['normalized_description'],
                    'size_snapshot' => $row['normalized_size'],
                    'initial_quantity' => $row['normalized_initial_quantity'],
                    'entries_quantity' => $row['normalized_entries_quantity'],
                    'exits_quantity' => $row['normalized_exits_quantity'],
                    'recalculated_quantity' => $row['normalized_quantity'],
                ]);

                $updateRow->execute(['id' => $row['id']]);
                $sheetIds[(int) $row['sheet_id']] = true;
                $imported++;
            }

            foreach (array_keys($sheetIds) as $sheetId) {
                $this->refreshSheetCounters($sheetId);
            }

            $remainingReady = $this->countRowsByStatus($batchId, 'ready');
            $remainingReview = $this->countRowsByStatus($batchId, 'review');
            $totalImported = $this->countRowsByStatus($batchId, 'imported');

            $status = $remainingReady === 0 && $remainingReview === 0
                ? 'imported'
                : ($totalImported > 0 ? 'partially_imported' : 'staged');

            $this->pdo->prepare(
                'UPDATE historical_import_batches SET status = :status WHERE id = :id'
            )->execute(['status' => $status, 'id' => $batchId]);

            $this->pdo->commit();

            return [
                'imported' => $imported,
                'remaining_ready' => $remainingReady,
                'remaining_review' => $remainingReview,
                'batch_status' => $status,
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteBatch(int $batchId): void
    {
        $this->pdo->prepare(
            'DELETE FROM historical_import_batches WHERE id = :id'
        )->execute(['id' => $batchId]);
    }

    public function resetStaging(int $batchId): void
    {
        $this->pdo->beginTransaction();

        try {
            $batch = $this->lockBatch($batchId);

            if ($batch['status'] !== 'staged') {
                throw new RuntimeException(
                    'Solo se puede reprocesar un lote que permanezca en staging.'
                );
            }

            $snapshotStatement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM historical_stock_snapshots WHERE batch_id = :id'
            );
            $snapshotStatement->execute(['id' => $batchId]);

            if ((int) $snapshotStatement->fetchColumn() > 0) {
                throw new RuntimeException(
                    'El lote ya contiene snapshots archivados y no puede reprocesarse.'
                );
            }

            $this->pdo->prepare(
                'DELETE FROM historical_import_sheets WHERE batch_id = :id'
            )->execute(['id' => $batchId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelBatch(int $batchId): void
    {
        $this->pdo->beginTransaction();

        try {
            $batch = $this->lockBatch($batchId);

            if (in_array($batch['status'], ['imported', 'partially_imported'], true)) {
                throw new RuntimeException(
                    'Un lote que ya archivó filas históricas no puede cancelarse.'
                );
            }

            $this->pdo->prepare(
                'UPDATE historical_import_batches
                 SET status = "cancelled"
                 WHERE id = :id'
            )->execute(['id' => $batchId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function countSnapshotsForBatch(int $batchId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM historical_stock_snapshots WHERE batch_id = :batch_id'
        );
        $statement->execute(['batch_id' => $batchId]);

        return (int) $statement->fetchColumn();
    }

    private function countRowsByStatus(int $batchId, string $status): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM historical_import_rows
             WHERE batch_id = :batch_id
               AND row_status = :status'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'status' => $status,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function lockBatch(int $batchId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM historical_import_batches WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $batchId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException('El lote histórico no existe.');
        }

        return $row;
    }

    private function normalizeCode(string $code): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($code)));
    }
}
