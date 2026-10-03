<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function categoryCards(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                c.id,
                c.code,
                c.slug,
                c.name,
                c.sort_order,
                COUNT(DISTINCT p.id) AS product_count,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS stock_total,
                (
                    SELECT pi.path
                    FROM products p2
                    INNER JOIN product_images pi
                        ON pi.product_id = p2.id
                       AND pi.is_primary = 1
                    WHERE p2.category_id = c.id
                      AND p2.status = 'active'
                    ORDER BY p2.created_at DESC, p2.id DESC
                    LIMIT 1
                ) AS image_path
            FROM categories c
            LEFT JOIN products p
                ON p.category_id = c.id
               AND p.status = 'active'
            LEFT JOIN product_variants pv
                ON pv.product_id = p.id
               AND pv.status = 'active'
            LEFT JOIN v_inventory_stock vs
                ON vs.product_variant_id = pv.id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order, c.id
        SQL)->fetchAll();
    }

    public function featuredProduct(): ?array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                p.id,
                p.internal_code,
                p.name,
                p.description,
                p.sex,
                p.color,
                c.id AS category_id,
                c.name AS category_name,
                pi.path AS primary_image,
                CAST(COALESCE(ips.quantity_on_hand, 0) AS DECIMAL(14,3)) AS stock_total
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN v_inventory_product_stock ips ON ips.product_id = p.id
            LEFT JOIN product_images pi
                ON pi.product_id = p.id
               AND pi.is_primary = 1
            WHERE p.status = 'active'
            ORDER BY
                CASE WHEN COALESCE(ips.quantity_on_hand, 0) > 0 THEN 0 ELSE 1 END,
                COALESCE(ips.quantity_on_hand, 0) DESC,
                p.created_at DESC,
                p.id DESC
            LIMIT 1
        SQL)->fetch();

        return $row ?: null;
    }

    public function sizesForProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                COALESCE(s.name, 'S/T') AS size_name,
                CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS quantity_on_hand
            FROM product_variants pv
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
            WHERE pv.product_id = :product_id
              AND pv.status = 'active'
            GROUP BY COALESCE(s.name, 'S/T'), s.sort_order
            HAVING COALESCE(SUM(vs.quantity_on_hand), 0) > 0
            ORDER BY s.sort_order, size_name
        SQL);
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    public function stockBreakdownForProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'CENTRAL' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS central_stock,
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'P4' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS p4_stock,
                CAST(COALESCE(SUM(CASE WHEN w.warehouse_type = 'TRANSIT' THEN vs.quantity_on_hand ELSE 0 END), 0) AS DECIMAL(14,3)) AS transit_stock
            FROM product_variants pv
            LEFT JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
            LEFT JOIN warehouses w ON w.id = vs.warehouse_id
            WHERE pv.product_id = :product_id
              AND pv.status = 'active'
        SQL);
        $statement->execute(['product_id' => $productId]);
        $row = $statement->fetch() ?: [];

        return [
            'central' => (float) ($row['central_stock'] ?? 0),
            'p4' => (float) ($row['p4_stock'] ?? 0),
            'transit' => (float) ($row['transit_stock'] ?? 0),
        ];
    }

    public function recentReceipts(int $limit = 4): array
    {
        $limit = max(1, min($limit, 10));

        return $this->pdo->query('
            SELECT
                r.id,
                r.receipt_code,
                r.supplier_or_origin,
                r.status,
                r.received_at,
                l.lot_code,
                COALESCE(SUM(ri.quantity), 0) AS total_quantity
            FROM receipts r
            INNER JOIN lots l ON l.receipt_id = r.id
            LEFT JOIN receipt_items ri ON ri.receipt_id = r.id
            GROUP BY r.id, l.lot_code
            ORDER BY r.received_at DESC, r.id DESC
            LIMIT ' . $limit)->fetchAll();
    }
}
