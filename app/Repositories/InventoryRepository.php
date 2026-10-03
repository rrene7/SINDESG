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

        return [
            'total_stock' => (float) ($row['total_stock'] ?? 0),
            'movements' => (int) ($row['movements'] ?? 0),
            'warehouses' => (int) ($row['warehouses'] ?? 0),
            'variants_with_stock' => (int) ($row['variants_with_stock'] ?? 0),
        ];
    }

    public function productStockMap(): array
    {
        $rows = $this->pdo->query(
            'SELECT product_id, quantity_on_hand FROM v_inventory_product_stock'
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['product_id']] = (float) $row['quantity_on_hand'];
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
                ), 0) AS DECIMAL(14,3)) AS stock_central
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

        return $statement->fetchAll();
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
