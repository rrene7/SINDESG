<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InventoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function overview(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                COALESCE((SELECT SUM(quantity_on_hand) FROM v_inventory_stock), 0) AS total_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'CENTRAL'
                ), 0) AS central_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'P4'
                ), 0) AS p4_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'TRANSIT'
                ), 0) AS transit_stock,
                COALESCE((
                    SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r
                        ON r.id = i.request_id
                       AND r.status IN ('approved', 'partially_approved')
                ), 0) AS committed_stock,
                COALESCE((SELECT COUNT(*) FROM inventory_movements), 0) AS movements,
                COALESCE((SELECT COUNT(*) FROM warehouses WHERE is_active = 1), 0) AS warehouses,
                COALESCE((
                    SELECT COUNT(*)
                    FROM (
                        SELECT product_variant_id
                        FROM v_inventory_stock
                        GROUP BY product_variant_id
                        HAVING SUM(quantity_on_hand) > 0
                    ) available_variants
                ), 0) AS variants_with_stock
        SQL)->fetch();

        $centralStock = (float) ($row['central_stock'] ?? 0);
        $committedStock = (float) ($row['committed_stock'] ?? 0);

        return [
            'total_stock' => (float) ($row['total_stock'] ?? 0),
            'central_stock' => $centralStock,
            'committed_stock' => $committedStock,
            'available_stock' => max(0.0, $centralStock - $committedStock),
            'p4_stock' => (float) ($row['p4_stock'] ?? 0),
            'transit_stock' => (float) ($row['transit_stock'] ?? 0),
            'movements' => (int) ($row['movements'] ?? 0),
            'warehouses' => (int) ($row['warehouses'] ?? 0),
            'variants_with_stock' => (int) ($row['variants_with_stock'] ?? 0),
        ];
    }

    public function productStockMap(): array
    {
        $rows = $this->pdo->query(<<<'SQL'
            SELECT
                p.id AS product_id,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS stock_total,
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'CENTRAL' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS stock_central,
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'P4' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS stock_p4,
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'TRANSIT' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS stock_transit,
                CAST(COALESCE((
                    SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r
                        ON r.id = i.request_id
                       AND r.status IN ('approved', 'partially_approved')
                    INNER JOIN product_variants pvr ON pvr.id = i.product_variant_id
                    WHERE pvr.product_id = p.id
                ), 0) AS DECIMAL(14,3)) AS stock_committed
            FROM products p
            LEFT JOIN product_variants pv
                ON pv.product_id = p.id
               AND pv.status <> 'deleted'
            LEFT JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
            LEFT JOIN warehouses w ON w.id = vs.warehouse_id
            WHERE p.status <> 'deleted'
            GROUP BY p.id
        SQL)->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $central = (float) ($row['stock_central'] ?? 0);
            $committed = (float) ($row['stock_committed'] ?? 0);

            $map[(int) $row['product_id']] = [
                'stock_total' => (float) ($row['stock_total'] ?? 0),
                'stock_central' => $central,
                'stock_committed' => $committed,
                'stock_available' => max(0.0, $central - $committed),
                'stock_p4' => (float) ($row['stock_p4'] ?? 0),
                'stock_transit' => (float) ($row['stock_transit'] ?? 0),
            ];
        }

        return $map;
    }

    public function stockByProduct(int $productId): array
    {
        $sql = <<<'SQL'
            SELECT
                pv.id AS variant_id,
                pv.internal_code,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS stock_total,
                CAST(COALESCE(SUM(
                    CASE WHEN w.warehouse_type = 'CENTRAL' THEN vs.quantity_on_hand ELSE 0 END
                ), 0) AS DECIMAL(14,3)) AS stock_central,
                CAST(COALESCE(SUM(
                    CASE WHEN w.warehouse_type = 'P4' THEN vs.quantity_on_hand ELSE 0 END
                ), 0) AS DECIMAL(14,3)) AS stock_p4,
                CAST(COALESCE(SUM(
                    CASE WHEN w.warehouse_type = 'TRANSIT' THEN vs.quantity_on_hand ELSE 0 END
                ), 0) AS DECIMAL(14,3)) AS stock_transit,
                CAST(COALESCE((
                    SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r
                        ON r.id = i.request_id
                       AND r.status IN ('approved', 'partially_approved')
                    WHERE i.product_variant_id = pv.id
                ), 0) AS DECIMAL(14,3)) AS stock_committed
            FROM product_variants pv
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            LEFT JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
            LEFT JOIN warehouses w ON w.id = vs.warehouse_id
            WHERE pv.product_id = :product_id
              AND pv.status <> 'deleted'
            GROUP BY pv.id, pv.internal_code, s.name, b.name
            ORDER BY s.sort_order, s.name, b.name, pv.id
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['product_id' => $productId]);

        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $central = (float) ($row['stock_central'] ?? 0);
            $committed = (float) ($row['stock_committed'] ?? 0);
            $row['stock_available'] = max(0.0, $central - $committed);
        }
        unset($row);

        return $rows;
    }

    public function recentMovementsForProduct(int $productId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $sql = '
            SELECT
                im.id,
                im.movement_kind,
                im.direction,
                im.quantity,
                im.occurred_at,
                im.reference_code,
                im.document_number,
                im.notes,
                im.actor_label,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                pv.internal_code AS variant_code,
                s.name AS size_name,
                b.name AS brand_name
            FROM inventory_movements im
            INNER JOIN warehouses w ON w.id = im.warehouse_id
            INNER JOIN product_variants pv ON pv.id = im.product_variant_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.product_id = :product_id
            ORDER BY im.occurred_at DESC, im.id DESC
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    public function warehouses(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name, warehouse_type, organization_unit_id
             FROM warehouses
             WHERE is_active = 1
             ORDER BY CASE WHEN warehouse_type = "CENTRAL" THEN 0 ELSE 1 END, name'
        )->fetchAll();
    }
}
