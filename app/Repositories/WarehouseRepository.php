<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class WarehouseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM warehouses WHERE warehouse_type = 'CENTRAL' AND is_active = 1) AS central_active,
                (SELECT COUNT(*) FROM warehouses WHERE warehouse_type = 'P4' AND is_active = 1) AS p4_active,
                (SELECT COUNT(*) FROM warehouses WHERE warehouse_type = 'P4' AND is_active = 0) AS p4_inactive,
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'CENTRAL'
                ) AS central_stock,
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'P4'
                ) AS p4_stock
        SQL)->fetch();

        return [
            'central_active' => (int) ($row['central_active'] ?? 0),
            'p4_active' => (int) ($row['p4_active'] ?? 0),
            'p4_inactive' => (int) ($row['p4_inactive'] ?? 0),
            'central_stock' => (float) ($row['central_stock'] ?? 0),
            'p4_stock' => (float) ($row['p4_stock'] ?? 0),
        ];
    }

    public function all(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                w.*,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS stock_total,
                COUNT(DISTINCT CASE WHEN COALESCE(vs.quantity_on_hand, 0) <> 0 THEN vs.product_variant_id END) AS variants_with_stock
            FROM warehouses w
            LEFT JOIN v_inventory_stock vs ON vs.warehouse_id = w.id
            GROUP BY w.id
            ORDER BY
                CASE
                    WHEN w.warehouse_type = 'CENTRAL' THEN 0
                    WHEN w.warehouse_type = 'TRANSIT' THEN 1
                    ELSE 2
                END,
                w.is_active DESC,
                w.name
        SQL)->fetchAll();
    }

    public function find(int $warehouseId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                w.*,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS stock_total,
                COUNT(DISTINCT CASE WHEN COALESCE(vs.quantity_on_hand, 0) <> 0 THEN vs.product_variant_id END) AS variants_with_stock
            FROM warehouses w
            LEFT JOIN v_inventory_stock vs ON vs.warehouse_id = w.id
            WHERE w.id = :id
            GROUP BY w.id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $warehouseId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function stock(int $warehouseId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                c.name AS category_name,
                p.id AS product_id,
                p.name AS product_name,
                p.internal_code AS product_code,
                pv.id AS variant_id,
                pv.internal_code AS variant_code,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(vs.quantity_on_hand AS DECIMAL(14,3)) AS quantity_on_hand,
                vs.last_movement_at
            FROM v_inventory_stock vs
            INNER JOIN product_variants pv ON pv.id = vs.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE vs.warehouse_id = :warehouse_id
              AND vs.quantity_on_hand <> 0
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, b.name, pv.id
        SQL);
        $statement->execute(['warehouse_id' => $warehouseId]);

        return $statement->fetchAll();
    }

    public function existsForOrganizationUnit(int $organizationUnitId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM warehouses WHERE organization_unit_id = :organization_unit_id'
        );
        $statement->execute(['organization_unit_id' => $organizationUnitId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createP4(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $temporaryCode = 'TMP-P4-' . strtoupper(bin2hex(random_bytes(6)));

            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO warehouses
                    (
                        code,
                        name,
                        warehouse_type,
                        organization_unit_id,
                        organization_unit_code_snapshot,
                        organization_unit_name_snapshot,
                        organization_unit_type_snapshot,
                        responsible_employee_id,
                        responsible_label_snapshot,
                        responsible_document_snapshot,
                        responsible_position_snapshot,
                        responsible_rank_snapshot,
                        notes,
                        is_active,
                        status_changed_at
                    )
                VALUES
                    (
                        :code,
                        :name,
                        'P4',
                        :organization_unit_id,
                        :organization_unit_code_snapshot,
                        :organization_unit_name_snapshot,
                        :organization_unit_type_snapshot,
                        :responsible_employee_id,
                        :responsible_label_snapshot,
                        :responsible_document_snapshot,
                        :responsible_position_snapshot,
                        :responsible_rank_snapshot,
                        :notes,
                        1,
                        NOW()
                    )
            SQL);
            $statement->execute([
                'code' => $temporaryCode,
                'name' => 'P4 - ' . $data['organization_unit_name_snapshot'],
                'organization_unit_id' => $data['organization_unit_id'],
                'organization_unit_code_snapshot' => $data['organization_unit_code_snapshot'],
                'organization_unit_name_snapshot' => $data['organization_unit_name_snapshot'],
                'organization_unit_type_snapshot' => $data['organization_unit_type_snapshot'],
                'responsible_employee_id' => $data['responsible_employee_id'],
                'responsible_label_snapshot' => $data['responsible_label_snapshot'],
                'responsible_document_snapshot' => $data['responsible_document_snapshot'],
                'responsible_position_snapshot' => $data['responsible_position_snapshot'],
                'responsible_rank_snapshot' => $data['responsible_rank_snapshot'],
                'notes' => $data['notes'],
            ]);

            $warehouseId = (int) $this->pdo->lastInsertId();
            $code = sprintf('P4-%06d', $warehouseId);

            $update = $this->pdo->prepare(
                'UPDATE warehouses SET code = :code WHERE id = :id'
            );
            $update->execute(['code' => $code, 'id' => $warehouseId]);

            $this->pdo->commit();
            return $warehouseId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function setActive(int $warehouseId, bool $active): void
    {
        $warehouse = $this->find($warehouseId);

        if ($warehouse === null) {
            throw new RuntimeException('El almacén solicitado no existe.');
        }

        if ($warehouse['warehouse_type'] !== 'P4') {
            throw new RuntimeException('Solo los almacenes P4 pueden activarse o inactivarse desde este módulo.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE warehouses
             SET is_active = :is_active,
                 status_changed_at = NOW()
             WHERE id = :id
               AND warehouse_type = "P4"'
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'id' => $warehouseId,
        ]);
    }
}
