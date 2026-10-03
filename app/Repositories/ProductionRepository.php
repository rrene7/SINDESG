<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class ProductionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM production_orders WHERE status = 'draft') AS drafts,
                (SELECT COUNT(*) FROM production_orders WHERE status = 'confirmed') AS confirmed,
                (
                    SELECT COALESCE(SUM(po2.quantity), 0)
                    FROM production_outputs po2
                    INNER JOIN production_orders pord ON pord.id = po2.production_order_id
                    WHERE pord.status = 'confirmed'
                ) AS output_quantity
        SQL)->fetch();

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'output_quantity' => (float) ($row['output_quantity'] ?? 0),
        ];
    }

    public function recent(int $limit = 30): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                p.id,
                p.production_code,
                p.internal_lot_code,
                p.produced_at,
                p.responsible_label,
                p.status,
                p.confirmed_at,
                (SELECT COUNT(*) FROM production_outputs o WHERE o.production_order_id = p.id) AS output_lines,
                (SELECT COALESCE(SUM(o.quantity), 0) FROM production_outputs o WHERE o.production_order_id = p.id) AS output_quantity,
                (SELECT COUNT(*) FROM production_inputs i WHERE i.production_order_id = p.id) AS input_lines
            FROM production_orders p
            ORDER BY p.produced_at DESC, p.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function find(int $orderId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                p.*,
                w.code AS warehouse_code,
                w.name AS warehouse_name
            FROM production_orders p
            INNER JOIN warehouses w ON w.id = p.warehouse_id
            WHERE p.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $orderId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function outputs(int $orderId): array
    {
        return $this->lines('production_outputs', $orderId);
    }

    public function inputs(int $orderId): array
    {
        return $this->lines('production_inputs', $orderId);
    }

    public function variantOptions(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                pv.id,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(COALESCE((
                    SELECT SUM(
                        CASE
                            WHEN im.direction = 'IN' THEN im.quantity
                            WHEN im.direction = 'OUT' THEN -im.quantity
                            ELSE 0
                        END
                    )
                    FROM inventory_movements im
                    INNER JOIN warehouses w ON w.id = im.warehouse_id
                    WHERE im.product_variant_id = pv.id
                      AND w.code = 'CENTRAL-SG'
                ), 0) AS DECIMAL(14,3)) AS central_stock
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id AND p.status = 'active'
            INNER JOIN categories c ON c.id = p.category_id AND c.is_active = 1
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.status = 'active'
            ORDER BY c.sort_order, c.id, p.name, s.sort_order, s.name, b.name, pv.id
        SQL)->fetchAll();
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

    public function createDraft(array $data, array $outputs, array $inputs): int
    {
        $this->pdo->beginTransaction();

        try {
            $warehouseId = $this->centralWarehouseId(false);
            $temporaryCode = 'TMP-P-' . strtoupper(bin2hex(random_bytes(6)));
            $temporaryLot = 'TMP-PL-' . strtoupper(bin2hex(random_bytes(6)));

            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO production_orders
                    (
                        production_code,
                        internal_lot_code,
                        warehouse_id,
                        produced_at,
                        responsible_label,
                        notes,
                        status
                    )
                VALUES
                    (
                        :production_code,
                        :internal_lot_code,
                        :warehouse_id,
                        :produced_at,
                        :responsible_label,
                        :notes,
                        'draft'
                    )
            SQL);
            $statement->execute([
                'production_code' => $temporaryCode,
                'internal_lot_code' => $temporaryLot,
                'warehouse_id' => $warehouseId,
                'produced_at' => $data['produced_at'],
                'responsible_label' => $data['responsible_label'],
                'notes' => $data['notes'],
            ]);

            $orderId = (int) $this->pdo->lastInsertId();
            $productionCode = sprintf('OPR-%06d', $orderId);
            $lotCode = sprintf('PLT-%06d', $orderId);

            $update = $this->pdo->prepare(
                'UPDATE production_orders
                 SET production_code = :production_code,
                     internal_lot_code = :internal_lot_code
                 WHERE id = :id'
            );
            $update->execute([
                'production_code' => $productionCode,
                'internal_lot_code' => $lotCode,
                'id' => $orderId,
            ]);

            $this->insertLines('production_outputs', $orderId, $outputs);
            $this->insertLines('production_inputs', $orderId, $inputs);

            $this->pdo->commit();
            return $orderId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function confirm(int $orderId): array
    {
        $this->pdo->beginTransaction();

        try {
            $orderStatement = $this->pdo->prepare(
                'SELECT * FROM production_orders WHERE id = :id FOR UPDATE'
            );
            $orderStatement->execute(['id' => $orderId]);
            $order = $orderStatement->fetch();

            if (!$order) {
                throw new RuntimeException('La orden de producción solicitada no existe.');
            }

            if ($order['status'] === 'confirmed') {
                $this->pdo->commit();
                return ['already_confirmed' => true];
            }

            if ($order['status'] !== 'draft') {
                throw new RuntimeException('Solo se pueden confirmar órdenes en borrador.');
            }

            $warehouseId = $this->centralWarehouseId(true);

            $outputStatement = $this->pdo->prepare(
                'SELECT id, product_variant_id, quantity, notes
                 FROM production_outputs
                 WHERE production_order_id = :order_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $outputStatement->execute(['order_id' => $orderId]);
            $outputs = $outputStatement->fetchAll();

            if ($outputs === []) {
                throw new RuntimeException('No se puede confirmar una producción sin producto terminado.');
            }

            $inputStatement = $this->pdo->prepare(
                'SELECT id, product_variant_id, quantity, notes
                 FROM production_inputs
                 WHERE production_order_id = :order_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $inputStatement->execute(['order_id' => $orderId]);
            $inputs = $inputStatement->fetchAll();

            foreach ($inputs as $input) {
                $available = $this->stockForUpdate(
                    $warehouseId,
                    (int) $input['product_variant_id']
                );

                if ($available < (float) $input['quantity']) {
                    throw new RuntimeException(sprintf(
                        'Stock insuficiente para el insumo variante #%d. Disponible: %s; requerido: %s.',
                        (int) $input['product_variant_id'],
                        number_format($available, 3, '.', ''),
                        number_format((float) $input['quantity'], 3, '.', '')
                    ));
                }
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
                        :movement_kind,
                        :direction,
                        :quantity,
                        :occurred_at,
                        'production',
                        :reference_id,
                        :reference_code,
                        :document_number,
                        :notes,
                        :actor_label,
                        :source_key
                    )
                ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)
            SQL);

            foreach ($inputs as $input) {
                $movement->execute([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $input['product_variant_id'],
                    'movement_kind' => 'production_input',
                    'direction' => 'OUT',
                    'quantity' => $input['quantity'],
                    'occurred_at' => $order['produced_at'],
                    'reference_id' => $orderId,
                    'reference_code' => $order['production_code'],
                    'document_number' => $order['internal_lot_code'],
                    'notes' => $input['notes'],
                    'actor_label' => $order['responsible_label'],
                    'source_key' => sprintf('production:%d:input:%d', $orderId, (int) $input['id']),
                ]);
            }

            foreach ($outputs as $output) {
                $movement->execute([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $output['product_variant_id'],
                    'movement_kind' => 'production_output',
                    'direction' => 'IN',
                    'quantity' => $output['quantity'],
                    'occurred_at' => $order['produced_at'],
                    'reference_id' => $orderId,
                    'reference_code' => $order['production_code'],
                    'document_number' => $order['internal_lot_code'],
                    'notes' => $output['notes'],
                    'actor_label' => $order['responsible_label'],
                    'source_key' => sprintf('production:%d:output:%d', $orderId, (int) $output['id']),
                ]);
            }

            $confirm = $this->pdo->prepare(
                'UPDATE production_orders
                 SET status = "confirmed", confirmed_at = NOW()
                 WHERE id = :id'
            );
            $confirm->execute(['id' => $orderId]);

            $this->pdo->commit();
            return ['already_confirmed' => false];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lines(string $table, int $orderId): array
    {
        if (!in_array($table, ['production_outputs', 'production_inputs'], true)) {
            throw new RuntimeException('Tipo de detalle de producción no válido.');
        }

        $statement = $this->pdo->prepare("
            SELECT
                x.id,
                x.quantity,
                x.notes,
                pv.id AS variant_id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                pv.supplier_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name
            FROM {$table} x
            INNER JOIN product_variants pv ON pv.id = x.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE x.production_order_id = :order_id
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, pv.id
        ");
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }

    private function insertLines(string $table, int $orderId, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        if (!in_array($table, ['production_outputs', 'production_inputs'], true)) {
            throw new RuntimeException('Tipo de detalle de producción no válido.');
        }

        $statement = $this->pdo->prepare("
            INSERT INTO {$table}
                (production_order_id, product_variant_id, quantity, notes)
            VALUES
                (:production_order_id, :product_variant_id, :quantity, :notes)
        ");

        foreach ($lines as $line) {
            $statement->execute([
                'production_order_id' => $orderId,
                'product_variant_id' => $line['product_variant_id'],
                'quantity' => $line['quantity'],
                'notes' => $line['notes'],
            ]);
        }
    }

    private function centralWarehouseId(bool $lock): int
    {
        $sql = 'SELECT id FROM warehouses WHERE code = :code AND is_active = 1 LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['code' => 'CENTRAL-SG']);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'El Almacén Central no está configurado. Aplique 003_inventory_core.sql.'
            );
        }

        return (int) $id;
    }

    private function stockForUpdate(int $warehouseId, int $variantId): float
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COALESCE(SUM(
                CASE
                    WHEN direction = 'IN' THEN quantity
                    WHEN direction = 'OUT' THEN -quantity
                    ELSE 0
                END
            ), 0)
            FROM inventory_movements
            WHERE warehouse_id = :warehouse_id
              AND product_variant_id = :variant_id
        SQL);
        $statement->execute([
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
        ]);

        return (float) $statement->fetchColumn();
    }
}
