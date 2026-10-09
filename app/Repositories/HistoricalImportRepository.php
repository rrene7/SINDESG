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
