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
                CAST(GREATEST(
                    COALESCE((
                        SELECT SUM(vs2.quantity_on_hand)
                        FROM products p2
                        INNER JOIN product_variants pv2
                            ON pv2.product_id = p2.id
                           AND pv2.status = 'active'
                        INNER JOIN v_inventory_stock vs2
                            ON vs2.product_variant_id = pv2.id
                        INNER JOIN warehouses w2
                            ON w2.id = vs2.warehouse_id
                           AND w2.code = 'CENTRAL-SG'
                        WHERE p2.category_id = c.id
                          AND p2.status = 'active'
                    ), 0)
                    -
                    COALESCE((
                        SELECT SUM(GREATEST(sri.quantity_approved - sri.quantity_dispatched, 0))
                        FROM service_request_items sri
                        INNER JOIN service_requests sr
                            ON sr.id = sri.request_id
                           AND sr.status IN ('approved', 'partially_approved')
                        INNER JOIN product_variants pvr ON pvr.id = sri.product_variant_id
                        INNER JOIN products pr ON pr.id = pvr.product_id
                        WHERE pr.category_id = c.id
                    ), 0),
                    0
                ) AS DECIMAL(14,3)) AS stock_available,
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
                CAST(COALESCE(ips.quantity_on_hand, 0) AS DECIMAL(14,3)) AS stock_total,
                CAST(COALESCE((
                    SELECT SUM(vs2.quantity_on_hand)
                    FROM product_variants pv2
                    INNER JOIN v_inventory_stock vs2 ON vs2.product_variant_id = pv2.id
                    INNER JOIN warehouses w2 ON w2.id = vs2.warehouse_id
                    WHERE pv2.product_id = p.id
                      AND pv2.status = 'active'
                      AND w2.code = 'CENTRAL-SG'
                ), 0) AS DECIMAL(14,3)) AS central_stock,
                CAST(COALESCE((
                    SELECT SUM(GREATEST(sri.quantity_approved - sri.quantity_dispatched, 0))
                    FROM service_request_items sri
                    INNER JOIN service_requests sr
                        ON sr.id = sri.request_id
                       AND sr.status IN ('approved', 'partially_approved')
                    INNER JOIN product_variants pvr ON pvr.id = sri.product_variant_id
                    WHERE pvr.product_id = p.id
                ), 0) AS DECIMAL(14,3)) AS committed_stock
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

        if (!$row) {
            return null;
        }

        $row['stock_available'] = max(
            0.0,
            (float) ($row['central_stock'] ?? 0) - (float) ($row['committed_stock'] ?? 0)
        );

        return $row;
    }

    public function sizesForProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                COALESCE(s.name, 'S/T') AS size_name,
                CAST(
                    SUM(
                        GREATEST(
                            COALESCE(cs.central_stock, 0) - COALESCE(rs.committed_stock, 0),
                            0
                        )
                    )
                    AS DECIMAL(14,3)
                ) AS quantity_on_hand
            FROM product_variants pv
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN (
                SELECT
                    vs.product_variant_id,
                    SUM(vs.quantity_on_hand) AS central_stock
                FROM v_inventory_stock vs
                INNER JOIN warehouses w
                    ON w.id = vs.warehouse_id
                   AND w.code = 'CENTRAL-SG'
                GROUP BY vs.product_variant_id
            ) cs ON cs.product_variant_id = pv.id
            LEFT JOIN (
                SELECT
                    sri.product_variant_id,
                    SUM(GREATEST(sri.quantity_approved - sri.quantity_dispatched, 0)) AS committed_stock
                FROM service_request_items sri
                INNER JOIN service_requests sr
                    ON sr.id = sri.request_id
                   AND sr.status IN ('approved', 'partially_approved')
                GROUP BY sri.product_variant_id
            ) rs ON rs.product_variant_id = pv.id
            WHERE pv.product_id = :product_id
              AND pv.status = 'active'
            GROUP BY COALESCE(s.name, 'S/T'), s.sort_order
            HAVING SUM(
                GREATEST(
                    COALESCE(cs.central_stock, 0) - COALESCE(rs.committed_stock, 0),
                    0
                )
            ) > 0
            ORDER BY s.sort_order, size_name
        SQL);
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    public function stockBreakdownForProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM product_variants pv
                    INNER JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE pv.product_id = :central_product_id
                      AND pv.status = 'active'
                      AND w.warehouse_type = 'CENTRAL'
                ) AS central_stock,
                (
                    SELECT COALESCE(SUM(GREATEST(sri.quantity_approved - sri.quantity_dispatched, 0)), 0)
                    FROM service_request_items sri
                    INNER JOIN service_requests sr
                        ON sr.id = sri.request_id
                       AND sr.status IN ('approved', 'partially_approved')
                    INNER JOIN product_variants pv ON pv.id = sri.product_variant_id
                    WHERE pv.product_id = :committed_product_id
                ) AS committed_stock,
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM product_variants pv
                    INNER JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE pv.product_id = :p4_product_id
                      AND pv.status = 'active'
                      AND w.warehouse_type = 'P4'
                ) AS p4_stock,
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM product_variants pv
                    INNER JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE pv.product_id = :transit_product_id
                      AND pv.status = 'active'
                      AND w.warehouse_type = 'TRANSIT'
                ) AS transit_stock
        SQL);
        $statement->execute([
            'central_product_id' => $productId,
            'committed_product_id' => $productId,
            'p4_product_id' => $productId,
            'transit_product_id' => $productId,
        ]);
        $row = $statement->fetch() ?: [];

        $central = (float) ($row['central_stock'] ?? 0);
        $committed = (float) ($row['committed_stock'] ?? 0);

        return [
            'central' => $central,
            'committed' => $committed,
            'available' => max(0.0, $central - $committed),
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
