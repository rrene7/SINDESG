<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class AdjustmentRepository
{
    public const SOURCE_TYPES = ['return', 'exchange', 'replacement'];
    public const OUT_TYPES = ['deterioration', 'writeoff', 'loss', 'shortage'];

    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                COALESCE(SUM(status = 'draft'), 0) AS drafts,
                COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
                COALESCE(SUM(status = 'confirmed' AND adjustment_type = 'return'), 0) AS returns_count,
                COALESCE(SUM(status = 'confirmed' AND adjustment_type = 'exchange'), 0) AS exchanges_count,
                COALESCE(SUM(status = 'confirmed' AND adjustment_type IN ('writeoff','loss','shortage','deterioration')), 0) AS outgoing_adjustments
            FROM inventory_adjustments
        SQL)->fetch();

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'returns_count' => (int) ($row['returns_count'] ?? 0),
            'exchanges_count' => (int) ($row['exchanges_count'] ?? 0),
            'outgoing_adjustments' => (int) ($row['outgoing_adjustments'] ?? 0),
        ];
    }

    public function recent(int $limit = 60): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                a.id,
                a.adjustment_code,
                a.adjustment_type,
                a.occurred_at,
                a.quantity,
                a.counted_quantity,
                a.status,
                a.affected_employee_label_snapshot,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                p.name AS product_name,
                pv.internal_code AS variant_code
            FROM inventory_adjustments a
            INNER JOIN warehouses w ON w.id = a.warehouse_id
            INNER JOIN product_variants pv ON pv.id = a.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            ORDER BY a.occurred_at DESC, a.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function adjustableWarehouses(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                id,
                code,
                name,
                warehouse_type,
                organization_unit_name_snapshot
            FROM warehouses
            WHERE is_active = 1
              AND warehouse_type IN ('CENTRAL', 'P4')
            ORDER BY
                CASE WHEN warehouse_type = 'CENTRAL' THEN 0 ELSE 1 END,
                COALESCE(organization_unit_name_snapshot, name),
                code
        SQL)->fetchAll();
    }

    public function activeVariants(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                pv.id,
                pv.product_id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.status = 'active'
              AND p.status <> 'deleted'
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, b.name, pv.id
        SQL)->fetchAll();
    }

    public function variantsForProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                pv.id,
                pv.product_id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.product_id = :product_id
              AND pv.status = 'active'
              AND p.status <> 'deleted'
            ORDER BY s.sort_order, s.name, b.name, pv.id
        SQL);
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    public function warehouseIsAdjustable(int $warehouseId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM warehouses
             WHERE id = :id
               AND is_active = 1
               AND warehouse_type IN ("CENTRAL", "P4")'
        );
        $statement->execute(['id' => $warehouseId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function variantIsActive(int $variantId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM product_variants pv
             INNER JOIN products p ON p.id = pv.product_id
             WHERE pv.id = :id
               AND pv.status = "active"
               AND p.status <> "deleted"'
        );
        $statement->execute(['id' => $variantId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function variantProductId(int $variantId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT product_id FROM product_variants WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $variantId]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function sourceDeliveryItem(int $deliveryItemId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.id AS delivery_item_id,
                di.delivery_id,
                di.product_variant_id,
                di.quantity AS delivered_quantity,
                d.delivery_code,
                d.delivered_at,
                d.recipient_employee_id,
                d.recipient_label_snapshot,
                d.recipient_document_snapshot,
                d.recipient_position_snapshot,
                d.recipient_rank_snapshot,
                d.p4_warehouse_id AS warehouse_id,
                w.code AS warehouse_code,
                w.organization_unit_name_snapshot AS warehouse_name,
                pv.product_id,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                GREATEST(
                    di.quantity
                    -
                    COALESCE((
                        SELECT SUM(a.quantity)
                        FROM inventory_adjustments a
                        WHERE a.source_delivery_item_id = di.id
                          AND a.status = 'confirmed'
                          AND a.adjustment_type IN ('return', 'exchange')
                    ), 0),
                    0
                ) AS returnable_quantity
            FROM individual_delivery_items di
            INNER JOIN individual_deliveries d ON d.id = di.delivery_id
            INNER JOIN warehouses w ON w.id = d.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE di.id = :delivery_item_id
              AND d.status = 'confirmed'
            LIMIT 1
        SQL);
        $statement->execute(['delivery_item_id' => $deliveryItemId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function createDraft(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO inventory_adjustments
                    (
                        adjustment_code,
                        adjustment_type,
                        warehouse_id,
                        source_delivery_item_id,
                        affected_employee_id,
                        affected_employee_label_snapshot,
                        affected_employee_document_snapshot,
                        affected_employee_position_snapshot,
                        affected_employee_rank_snapshot,
                        product_variant_id,
                        replacement_variant_id,
                        quantity,
                        counted_quantity,
                        return_condition,
                        manual_direction,
                        responsible_employee_id,
                        responsible_label_snapshot,
                        responsible_document_snapshot,
                        responsible_position_snapshot,
                        responsible_rank_snapshot,
                        occurred_at,
                        document_number,
                        reason,
                        notes,
                        status
                    )
                VALUES
                    (
                        :adjustment_code,
                        :adjustment_type,
                        :warehouse_id,
                        :source_delivery_item_id,
                        :affected_employee_id,
                        :affected_employee_label_snapshot,
                        :affected_employee_document_snapshot,
                        :affected_employee_position_snapshot,
                        :affected_employee_rank_snapshot,
                        :product_variant_id,
                        :replacement_variant_id,
                        :quantity,
                        :counted_quantity,
                        :return_condition,
                        :manual_direction,
                        :responsible_employee_id,
                        :responsible_label_snapshot,
                        :responsible_document_snapshot,
                        :responsible_position_snapshot,
                        :responsible_rank_snapshot,
                        :occurred_at,
                        :document_number,
                        :reason,
                        :notes,
                        'draft'
                    )
            SQL);

            $statement->execute([
                'adjustment_code' => 'TMP-AJU-' . strtoupper(bin2hex(random_bytes(6))),
                'adjustment_type' => $data['adjustment_type'],
                'warehouse_id' => $data['warehouse_id'],
                'source_delivery_item_id' => $data['source_delivery_item_id'],
                'affected_employee_id' => $data['affected_employee_id'],
                'affected_employee_label_snapshot' => $data['affected_employee_label_snapshot'],
                'affected_employee_document_snapshot' => $data['affected_employee_document_snapshot'],
                'affected_employee_position_snapshot' => $data['affected_employee_position_snapshot'],
                'affected_employee_rank_snapshot' => $data['affected_employee_rank_snapshot'],
                'product_variant_id' => $data['product_variant_id'],
                'replacement_variant_id' => $data['replacement_variant_id'],
                'quantity' => number_format((float) $data['quantity'], 3, '.', ''),
                'counted_quantity' => $data['counted_quantity'] === null
                    ? null
                    : number_format((float) $data['counted_quantity'], 3, '.', ''),
                'return_condition' => $data['return_condition'],
                'manual_direction' => $data['manual_direction'],
                'responsible_employee_id' => $data['responsible_employee_id'],
                'responsible_label_snapshot' => $data['responsible_label_snapshot'],
                'responsible_document_snapshot' => $data['responsible_document_snapshot'],
                'responsible_position_snapshot' => $data['responsible_position_snapshot'],
                'responsible_rank_snapshot' => $data['responsible_rank_snapshot'],
                'occurred_at' => $data['occurred_at'],
                'document_number' => $data['document_number'],
                'reason' => $data['reason'],
                'notes' => $data['notes'],
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $code = sprintf('AJU-%06d', $id);

            $this->pdo->prepare(
                'UPDATE inventory_adjustments
                 SET adjustment_code = :code
                 WHERE id = :id'
            )->execute(['code' => $code, 'id' => $id]);

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function find(int $adjustmentId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                a.*,
                w.code AS warehouse_code,
                w.name AS warehouse_internal_name,
                w.warehouse_type,
                w.organization_unit_name_snapshot AS warehouse_name,
                pv.internal_code AS variant_code,
                pv.product_id,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                rpv.internal_code AS replacement_variant_code,
                rp.name AS replacement_product_name,
                rs.name AS replacement_size_name,
                rb.name AS replacement_brand_name,
                d.id AS source_delivery_id,
                d.delivery_code AS source_delivery_code
            FROM inventory_adjustments a
            INNER JOIN warehouses w ON w.id = a.warehouse_id
            INNER JOIN product_variants pv ON pv.id = a.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            LEFT JOIN product_variants rpv ON rpv.id = a.replacement_variant_id
            LEFT JOIN products rp ON rp.id = rpv.product_id
            LEFT JOIN sizes rs ON rs.id = rpv.size_id
            LEFT JOIN brands rb ON rb.id = rpv.brand_id
            LEFT JOIN individual_delivery_items di ON di.id = a.source_delivery_item_id
            LEFT JOIN individual_deliveries d ON d.id = di.delivery_id
            WHERE a.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $adjustmentId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function physicalStock(int $warehouseId, int $variantId): float
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(quantity_on_hand, 0)
             FROM v_inventory_stock
             WHERE warehouse_id = :warehouse_id
               AND product_variant_id = :variant_id
             LIMIT 1'
        );
        $statement->execute([
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
        ]);

        return (float) ($statement->fetchColumn() ?: 0);
    }

    public function confirm(int $adjustmentId): array
    {
        $this->pdo->beginTransaction();

        try {
            $adjustment = $this->lockAdjustment($adjustmentId);

            if ($adjustment['status'] === 'confirmed') {
                $this->pdo->commit();
                return ['already_confirmed' => true];
            }

            if ($adjustment['status'] !== 'draft') {
                throw new RuntimeException('Solo se pueden confirmar ajustes en borrador.');
            }

            $warehouse = $this->lockWarehouse((int) $adjustment['warehouse_id']);
            if (
                (int) $warehouse['is_active'] !== 1
                || !in_array($warehouse['warehouse_type'], ['CENTRAL', 'P4'], true)
            ) {
                throw new RuntimeException('El almacén del ajuste no está habilitado para esta operación.');
            }

            $type = (string) $adjustment['adjustment_type'];
            $quantity = (float) $adjustment['quantity'];
            $primaryVariantId = (int) $adjustment['product_variant_id'];
            $replacementVariantId = $adjustment['replacement_variant_id'] !== null
                ? (int) $adjustment['replacement_variant_id']
                : null;

            $primaryBefore = $this->physicalStockForUpdate(
                (int) $adjustment['warehouse_id'],
                $primaryVariantId
            );
            $primaryAfter = $primaryBefore;
            $replacementBefore = null;
            $replacementAfter = null;
            $systemSnapshot = null;
            $effectiveQuantity = $quantity;

            $source = null;
            if (in_array($type, self::SOURCE_TYPES, true)) {
                $source = $this->lockSourceDeliveryItem(
                    (int) $adjustment['source_delivery_item_id']
                );

                if ((int) $source['p4_warehouse_id'] !== (int) $adjustment['warehouse_id']) {
                    throw new RuntimeException('La entrega original pertenece a otro P4.');
                }

                if ((int) $source['product_variant_id'] !== $primaryVariantId) {
                    throw new RuntimeException('La variante original del ajuste no coincide con la entrega.');
                }

                if (in_array($type, ['return', 'exchange'], true)) {
                    $returnable = $this->returnableQuantityForUpdate(
                        (int) $adjustment['source_delivery_item_id'],
                        (float) $source['delivered_quantity']
                    );

                    if ($quantity <= 0 || $quantity > $returnable + 0.000001) {
                        throw new RuntimeException(sprintf(
                            'La cantidad supera lo pendiente de devolver. Disponible para devolución: %s.',
                            number_format($returnable, 3, '.', '')
                        ));
                    }
                }

                if (
                    $type === 'replacement'
                    && ($quantity <= 0 || $quantity > (float) $source['delivered_quantity'] + 0.000001)
                ) {
                    throw new RuntimeException('La reposición supera la cantidad de la entrega original.');
                }
            }

            if ($type === 'return') {
                if ($adjustment['return_condition'] === 'reusable') {
                    $this->insertMovement(
                        $adjustment,
                        $primaryVariantId,
                        'IN',
                        $quantity,
                        'return_reusable',
                        'primary'
                    );
                    $primaryAfter = $primaryBefore + $quantity;
                }
            } elseif ($type === 'exchange') {
                if ($replacementVariantId === null) {
                    throw new RuntimeException('El cambio requiere una variante de reemplazo.');
                }

                if ($replacementVariantId === $primaryVariantId) {
                    throw new RuntimeException('Seleccione una variante distinta para el cambio.');
                }

                $replacementBefore = $this->physicalStockForUpdate(
                    (int) $adjustment['warehouse_id'],
                    $replacementVariantId
                );

                if ($replacementBefore + 0.000001 < $quantity) {
                    throw new RuntimeException('No existe stock suficiente de la variante de reemplazo.');
                }

                if ($adjustment['return_condition'] === 'reusable') {
                    $this->insertMovement(
                        $adjustment,
                        $primaryVariantId,
                        'IN',
                        $quantity,
                        'exchange_return',
                        'primary-return'
                    );
                    $primaryAfter = $primaryBefore + $quantity;
                }

                $this->insertMovement(
                    $adjustment,
                    $replacementVariantId,
                    'OUT',
                    $quantity,
                    'exchange_replacement',
                    'replacement-out'
                );
                $replacementAfter = $replacementBefore - $quantity;
            } elseif ($type === 'replacement') {
                if ($replacementVariantId === null) {
                    throw new RuntimeException('La reposición requiere una variante de reemplazo.');
                }

                $replacementBefore = $this->physicalStockForUpdate(
                    (int) $adjustment['warehouse_id'],
                    $replacementVariantId
                );

                if ($replacementBefore + 0.000001 < $quantity) {
                    throw new RuntimeException('No existe stock suficiente para realizar la reposición.');
                }

                $this->insertMovement(
                    $adjustment,
                    $replacementVariantId,
                    'OUT',
                    $quantity,
                    'replacement',
                    'replacement-out'
                );
                $replacementAfter = $replacementBefore - $quantity;
            } elseif (in_array($type, self::OUT_TYPES, true)) {
                if ($quantity <= 0) {
                    throw new RuntimeException('La cantidad debe ser mayor que cero.');
                }

                if ($primaryBefore + 0.000001 < $quantity) {
                    throw new RuntimeException('La salida dejaría el inventario en negativo.');
                }

                $this->insertMovement(
                    $adjustment,
                    $primaryVariantId,
                    'OUT',
                    $quantity,
                    $type,
                    'primary-out'
                );
                $primaryAfter = $primaryBefore - $quantity;
            } elseif ($type === 'surplus') {
                if ($quantity <= 0) {
                    throw new RuntimeException('La cantidad debe ser mayor que cero.');
                }

                $this->insertMovement(
                    $adjustment,
                    $primaryVariantId,
                    'IN',
                    $quantity,
                    'surplus',
                    'primary-in'
                );
                $primaryAfter = $primaryBefore + $quantity;
            } elseif ($type === 'authorized_adjustment') {
                $direction = (string) $adjustment['manual_direction'];
                if (!in_array($direction, ['IN', 'OUT'], true) || $quantity <= 0) {
                    throw new RuntimeException('El ajuste autorizado requiere dirección y cantidad válida.');
                }

                if ($direction === 'OUT' && $primaryBefore + 0.000001 < $quantity) {
                    throw new RuntimeException('El ajuste de salida dejaría el inventario en negativo.');
                }

                $this->insertMovement(
                    $adjustment,
                    $primaryVariantId,
                    $direction,
                    $quantity,
                    'authorized_adjustment',
                    'primary-' . strtolower($direction)
                );
                $primaryAfter = $direction === 'IN'
                    ? $primaryBefore + $quantity
                    : $primaryBefore - $quantity;
            } elseif ($type === 'cycle_count') {
                if ($adjustment['counted_quantity'] === null) {
                    throw new RuntimeException('El conteo requiere una cantidad física contada.');
                }

                $counted = (float) $adjustment['counted_quantity'];
                $systemSnapshot = $primaryBefore;
                $difference = $counted - $primaryBefore;
                $effectiveQuantity = abs($difference);

                if (abs($difference) > 0.000001) {
                    $direction = $difference > 0 ? 'IN' : 'OUT';

                    if ($direction === 'OUT' && $primaryBefore + 0.000001 < $effectiveQuantity) {
                        throw new RuntimeException('La diferencia del conteo produciría stock negativo.');
                    }

                    $this->insertMovement(
                        $adjustment,
                        $primaryVariantId,
                        $direction,
                        $effectiveQuantity,
                        'cycle_count',
                        'count-' . strtolower($direction)
                    );
                }

                $primaryAfter = $counted;
            } else {
                throw new RuntimeException('Tipo de ajuste no soportado.');
            }

            $this->pdo->prepare(<<<'SQL'
                UPDATE inventory_adjustments
                SET
                    quantity = :quantity,
                    system_quantity_snapshot = :system_quantity_snapshot,
                    primary_stock_before = :primary_stock_before,
                    primary_stock_after = :primary_stock_after,
                    replacement_stock_before = :replacement_stock_before,
                    replacement_stock_after = :replacement_stock_after,
                    status = 'confirmed',
                    confirmed_at = NOW()
                WHERE id = :id
            SQL)->execute([
                'quantity' => number_format($effectiveQuantity, 3, '.', ''),
                'system_quantity_snapshot' => $systemSnapshot === null
                    ? null
                    : number_format($systemSnapshot, 3, '.', ''),
                'primary_stock_before' => number_format($primaryBefore, 3, '.', ''),
                'primary_stock_after' => number_format($primaryAfter, 3, '.', ''),
                'replacement_stock_before' => $replacementBefore === null
                    ? null
                    : number_format($replacementBefore, 3, '.', ''),
                'replacement_stock_after' => $replacementAfter === null
                    ? null
                    : number_format($replacementAfter, 3, '.', ''),
                'id' => $adjustmentId,
            ]);

            $this->pdo->commit();

            return ['already_confirmed' => false];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $adjustmentId): void
    {
        $this->pdo->beginTransaction();

        try {
            $adjustment = $this->lockAdjustment($adjustmentId);

            if ($adjustment['status'] !== 'draft') {
                throw new RuntimeException('Solo un ajuste en borrador puede cancelarse.');
            }

            $this->pdo->prepare(
                'UPDATE inventory_adjustments
                 SET status = "cancelled", cancelled_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $adjustmentId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockAdjustment(int $adjustmentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM inventory_adjustments WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $adjustmentId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException('El ajuste solicitado no existe.');
        }

        return $row;
    }

    private function lockWarehouse(int $warehouseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM warehouses WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $warehouseId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException('El almacén solicitado no existe.');
        }

        return $row;
    }

    private function lockSourceDeliveryItem(int $deliveryItemId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.id,
                di.product_variant_id,
                di.quantity AS delivered_quantity,
                d.p4_warehouse_id,
                d.recipient_employee_id,
                d.status
            FROM individual_delivery_items di
            INNER JOIN individual_deliveries d ON d.id = di.delivery_id
            WHERE di.id = :id
            FOR UPDATE
        SQL);
        $statement->execute(['id' => $deliveryItemId]);
        $row = $statement->fetch();

        if (!$row || $row['status'] !== 'confirmed') {
            throw new RuntimeException('La entrega original no está confirmada.');
        }

        return $row;
    }

    private function returnableQuantityForUpdate(int $deliveryItemId, float $delivered): float
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COALESCE(SUM(quantity), 0)
            FROM inventory_adjustments
            WHERE source_delivery_item_id = :source_delivery_item_id
              AND status = 'confirmed'
              AND adjustment_type IN ('return', 'exchange')
        SQL);
        $statement->execute(['source_delivery_item_id' => $deliveryItemId]);

        return max(0.0, $delivered - (float) $statement->fetchColumn());
    }

    private function physicalStockForUpdate(int $warehouseId, int $variantId): float
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

    private function insertMovement(
        array $adjustment,
        int $variantId,
        string $direction,
        float $quantity,
        string $movementKind,
        string $sourceSuffix
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $statement = $this->pdo->prepare(<<<'SQL'
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
                    'inventory_adjustment',
                    :reference_id,
                    :reference_code,
                    :document_number,
                    :notes,
                    :actor_label,
                    :source_key
                )
            ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)
        SQL);
        $statement->execute([
            'warehouse_id' => $adjustment['warehouse_id'],
            'product_variant_id' => $variantId,
            'movement_kind' => $movementKind,
            'direction' => $direction,
            'quantity' => number_format($quantity, 3, '.', ''),
            'occurred_at' => $adjustment['occurred_at'],
            'reference_id' => $adjustment['id'],
            'reference_code' => $adjustment['adjustment_code'],
            'document_number' => $adjustment['document_number'],
            'notes' => $adjustment['reason']
                . ($adjustment['notes'] ? ' · ' . $adjustment['notes'] : ''),
            'actor_label' => $adjustment['responsible_label_snapshot'],
            'source_key' => sprintf(
                'inventory-adjustment:%d:%s',
                (int) $adjustment['id'],
                $sourceSuffix
            ),
        ]);
    }
}
