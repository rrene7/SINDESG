<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Dashboard data limited to explicitly assigned, active P4 warehouses.
 * All counts are full SQL aggregates (never computed from recent-record limits).
 * Stock represents physical on-hand balance, not unreserved/available stock.
 */
final class P4DashboardData
{
    public function __construct(private PDO $pdo) {}

    public function forWarehouse(int $userId, int $warehouseId): array
    {
        $allowed = (new P4AssignedWarehouses($this->pdo))->listForUser($userId);
        $ids = array_map(static fn(array $w): int => (int)$w['id'], $allowed);
        if ($userId <= 0 || $warehouseId <= 0 || !in_array($warehouseId, $ids, true)) {
            throw new RuntimeException('Almacén P4 no autorizado.');
        }

        $queries = [
            'requests_waiting_central' =>
                "SELECT COUNT(*) FROM service_requests
                 WHERE p4_warehouse_id=:wh AND status IN ('submitted', 'in_review')",
            'transfers_to_receive' =>
                "SELECT COUNT(*) FROM distributions
                 WHERE destination_warehouse_id=:wh AND status='dispatched'",
            'beneficiaries_pending' =>
                "SELECT COUNT(DISTINCT i.beneficiary_employee_id)
                 FROM service_request_items i
                 JOIN service_requests r ON r.id=i.request_id
                 WHERE r.p4_warehouse_id=:wh
                   AND r.status IN ('approved','partially_approved','fulfilled')
                   AND i.beneficiary_employee_id IS NOT NULL
                   AND i.quantity_received > i.quantity_delivered",
            'deliveries_confirmed' =>
                "SELECT COUNT(*) FROM individual_deliveries
                 WHERE p4_warehouse_id=:wh AND status='confirmed'",
            'physical_units' =>
                "SELECT COALESCE(SUM(quantity_on_hand),0) FROM v_inventory_stock
                 WHERE warehouse_id=:wh",
            'stock_variants' =>
                "SELECT COUNT(*) FROM v_inventory_stock
                 WHERE warehouse_id=:wh AND quantity_on_hand > 0",
            'stock_products' =>
                "SELECT COUNT(DISTINCT pv.product_id)
                 FROM v_inventory_stock vs
                 JOIN product_variants pv ON pv.id=vs.product_variant_id
                 WHERE vs.warehouse_id=:wh AND vs.quantity_on_hand > 0",
        ];
        $counts = [];
        foreach ($queries as $name => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['wh' => $warehouseId]);
            $value = $stmt->fetchColumn();
            $counts[$name] = $name === 'physical_units' ? (float)$value : (int)$value;
        }

        // Full article totals are computed independently of the 25-size UI
        // sample. The summary cannot understate a product with 26+ variants.
        $productTotals = $this->pdo->prepare(<<<'SQL'
            SELECT pv.product_id, p.name AS product_name,
                   SUM(vs.quantity_on_hand) AS physical_quantity,
                   COUNT(*) AS variant_count
            FROM v_inventory_stock vs
            JOIN product_variants pv ON pv.id=vs.product_variant_id
            JOIN products p ON p.id=pv.product_id
            WHERE vs.warehouse_id=:wh AND vs.quantity_on_hand > 0
            GROUP BY pv.product_id,p.name
            ORDER BY p.name,pv.product_id
            LIMIT 12
SQL);
        $productTotals->execute(['wh' => $warehouseId]);
        $stockProducts = $productTotals->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT pv.product_id, p.name AS product_name,
                   COALESCE(sz.name, 'Sin talla') AS size_name,
                   vs.quantity_on_hand AS physical_quantity
            FROM v_inventory_stock vs
            JOIN product_variants pv ON pv.id=vs.product_variant_id
            JOIN products p ON p.id=pv.product_id
            LEFT JOIN sizes sz ON sz.id=pv.size_id
            WHERE vs.warehouse_id=:wh AND vs.quantity_on_hand > 0
            ORDER BY p.name, sz.sort_order, sz.name, pv.id
            LIMIT 25
SQL);
        $stmt->execute(['wh' => $warehouseId]);
        return [
            'metrics' => $counts,
            'stock_products' => $stockProducts,
            'stock' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
