<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class ReceiptRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM receipts WHERE status = 'draft') AS drafts,
                (SELECT COUNT(*) FROM receipts WHERE status = 'confirmed') AS confirmed,
                (
                    SELECT COALESCE(SUM(ri.quantity), 0)
                    FROM receipt_items ri
                    INNER JOIN receipts r ON r.id = ri.receipt_id
                    WHERE r.status = 'confirmed'
                ) AS received_quantity
        SQL)->fetch();

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'received_quantity' => (float) ($row['received_quantity'] ?? 0),
        ];
    }

    public function recent(int $limit = 30): array
    {
        $limit = max(1, min($limit, 100));

        $sql = '
            SELECT
                r.id,
                r.receipt_code,
                r.source_type,
                r.supplier_or_origin,
                r.document_number,
                r.received_at,
                r.received_by_label,
                r.status,
                r.confirmed_at,
                l.lot_code,
                COUNT(ri.id) AS item_count,
                COALESCE(SUM(ri.quantity), 0) AS total_quantity
            FROM receipts r
            INNER JOIN lots l ON l.receipt_id = r.id
            LEFT JOIN receipt_items ri ON ri.receipt_id = r.id
            GROUP BY r.id, l.lot_code
            ORDER BY r.received_at DESC, r.id DESC
            LIMIT ' . $limit;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function find(int $receiptId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                r.*,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                l.id AS lot_id,
                l.lot_code,
                l.status AS lot_status
            FROM receipts r
            INNER JOIN warehouses w ON w.id = r.warehouse_id
            INNER JOIN lots l ON l.receipt_id = r.id
            WHERE r.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $receiptId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function items(int $receiptId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                ri.id,
                ri.quantity,
                ri.notes,
                pv.id AS variant_id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                pv.supplier_code,
                p.id AS product_id,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name
            FROM receipt_items ri
            INNER JOIN product_variants pv ON pv.id = ri.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE ri.receipt_id = :receipt_id
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, pv.id
        SQL);
        $statement->execute(['receipt_id' => $receiptId]);

        return $statement->fetchAll();
    }

    public function activeVariantIds(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_filter(
            array_map('intval', $variantIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($variantIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id FROM product_variants WHERE status = 'active' AND id IN ({$placeholders})"
        );
        $statement->execute($variantIds);

        return array_map('intval', array_column($statement->fetchAll(), 'id'));
    }

    public function createDraft(array $data, array $items): int
    {
        $this->pdo->beginTransaction();

        try {
            $warehouseId = $this->centralWarehouseId();
            $temporaryReceiptCode = 'TMP-R-' . strtoupper(bin2hex(random_bytes(6)));

            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO receipts
                    (
                        receipt_code,
                        warehouse_id,
                        source_type,
                        supplier_or_origin,
                        document_number,
                        received_at,
                        received_by_label,
                        notes,
                        status
                    )
                VALUES
                    (
                        :receipt_code,
                        :warehouse_id,
                        :source_type,
                        :supplier_or_origin,
                        :document_number,
                        :received_at,
                        :received_by_label,
                        :notes,
                        'draft'
                    )
            SQL);

            $statement->execute([
                'receipt_code' => $temporaryReceiptCode,
                'warehouse_id' => $warehouseId,
                'source_type' => $data['source_type'],
                'supplier_or_origin' => $data['supplier_or_origin'],
                'document_number' => $data['document_number'],
                'received_at' => $data['received_at'],
                'received_by_label' => $data['received_by_label'],
                'notes' => $data['notes'],
            ]);

            $receiptId = (int) $this->pdo->lastInsertId();
            $receiptCode = sprintf('REC-%06d', $receiptId);

            $updateReceipt = $this->pdo->prepare(
                'UPDATE receipts SET receipt_code = :code WHERE id = :id'
            );
            $updateReceipt->execute(['code' => $receiptCode, 'id' => $receiptId]);

            $temporaryLotCode = 'TMP-L-' . strtoupper(bin2hex(random_bytes(6)));
            $lotStatement = $this->pdo->prepare(
                'INSERT INTO lots (receipt_id, lot_code, status, received_at)
                 VALUES (:receipt_id, :lot_code, "pending", :received_at)'
            );
            $lotStatement->execute([
                'receipt_id' => $receiptId,
                'lot_code' => $temporaryLotCode,
                'received_at' => $data['received_at'],
            ]);

            $lotId = (int) $this->pdo->lastInsertId();
            $lotCode = sprintf('LOT-%06d', $lotId);

            $updateLot = $this->pdo->prepare(
                'UPDATE lots SET lot_code = :code WHERE id = :id'
            );
            $updateLot->execute(['code' => $lotCode, 'id' => $lotId]);

            $itemStatement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO receipt_items
                    (receipt_id, lot_id, product_variant_id, quantity, notes)
                VALUES
                    (:receipt_id, :lot_id, :product_variant_id, :quantity, :notes)
            SQL);

            foreach ($items as $item) {
                $itemStatement->execute([
                    'receipt_id' => $receiptId,
                    'lot_id' => $lotId,
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'],
                ]);
            }

            $this->pdo->commit();
            return $receiptId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function confirm(int $receiptId): array
    {
        $this->pdo->beginTransaction();

        try {
            $receiptStatement = $this->pdo->prepare(
                'SELECT * FROM receipts WHERE id = :id FOR UPDATE'
            );
            $receiptStatement->execute(['id' => $receiptId]);
            $receipt = $receiptStatement->fetch();

            if (!$receipt) {
                throw new RuntimeException('La recepción solicitada no existe.');
            }

            if ($receipt['status'] === 'confirmed') {
                $this->pdo->commit();
                return ['already_confirmed' => true];
            }

            if ($receipt['status'] !== 'draft') {
                throw new RuntimeException('Solo se pueden confirmar recepciones en borrador.');
            }

            $itemsStatement = $this->pdo->prepare(
                'SELECT id, product_variant_id, quantity, notes
                 FROM receipt_items
                 WHERE receipt_id = :receipt_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $itemsStatement->execute(['receipt_id' => $receiptId]);
            $items = $itemsStatement->fetchAll();

            if ($items === []) {
                throw new RuntimeException('No se puede confirmar una recepción sin artículos.');
            }

            $movement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO inventory_movements
                    (
                        warehouse_id,
                        product_variant_id,
                        movement_kind,
                        direction,
                        quantity,
                        occurred_at,
                        reference_type,
                        reference_id,
                        reference_code,
                        document_number,
                        notes,
                        actor_label,
                        source_key
                    )
                VALUES
                    (
                        :warehouse_id,
                        :product_variant_id,
                        'receipt',
                        'IN',
                        :quantity,
                        :occurred_at,
                        'receipt',
                        :reference_id,
                        :reference_code,
                        :document_number,
                        :notes,
                        :actor_label,
                        :source_key
                    )
                ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)
            SQL);

            foreach ($items as $item) {
                $movement->execute([
                    'warehouse_id' => $receipt['warehouse_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'occurred_at' => $receipt['received_at'],
                    'reference_id' => $receiptId,
                    'reference_code' => $receipt['receipt_code'],
                    'document_number' => $receipt['document_number'],
                    'notes' => $item['notes'],
                    'actor_label' => $receipt['received_by_label'],
                    'source_key' => sprintf('receipt:%d:item:%d', $receiptId, (int) $item['id']),
                ]);
            }

            $confirmReceipt = $this->pdo->prepare(
                'UPDATE receipts
                 SET status = "confirmed", confirmed_at = NOW()
                 WHERE id = :id'
            );
            $confirmReceipt->execute(['id' => $receiptId]);

            $confirmLot = $this->pdo->prepare(
                'UPDATE lots
                 SET status = "received"
                 WHERE receipt_id = :receipt_id'
            );
            $confirmLot->execute(['receipt_id' => $receiptId]);

            $this->pdo->commit();
            return ['already_confirmed' => false];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function centralWarehouseId(): int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM warehouses WHERE code = :code AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['code' => 'CENTRAL-SG']);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'El Almacén Central no está configurado. Aplique 003_inventory_core.sql.'
            );
        }

        return (int) $id;
    }
}
