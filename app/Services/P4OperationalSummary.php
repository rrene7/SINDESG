<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Warehouse-scoped, read-only P4 operational summaries.
 * Applies warehouse predicates before ordering and limiting; never loads
 * records of another P4, even where those records are more recent.
 */
final class P4OperationalSummary
{
    public function __construct(private PDO $pdo) {}

    public function forWarehouse(int $userId, int $warehouseId, int $limit = 20): array
    {
        $allowed = (new P4AssignedWarehouses($this->pdo))->listForUser($userId);
        if ($warehouseId <= 0
            || !in_array($warehouseId, array_map(
                static fn (array $w): int => (int) $w['id'], $allowed
            ), true)) {
            throw new RuntimeException('El almacén P4 no está autorizado.');
        }
        $limit = max(1, min($limit, 50));
        return [
            'requests' => $this->rows(
                'SELECT r.id, r.request_code AS code, r.status, r.requested_at AS event_at,
                        r.requested_by_label_snapshot AS person_label,
                        (SELECT COALESCE(SUM(i.quantity_requested),0) FROM service_request_items i
                         WHERE i.request_id=r.id) AS total_quantity,
                        (SELECT COUNT(*) FROM service_request_items i
                         WHERE i.request_id=r.id) AS item_count,
                        (SELECT p.name FROM service_request_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         JOIN products p ON p.id=v.product_id
                         WHERE i.request_id=r.id ORDER BY i.id LIMIT 1) AS product_name,
                        (SELECT sz.name FROM service_request_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         LEFT JOIN sizes sz ON sz.id=v.size_id
                         WHERE i.request_id=r.id ORDER BY i.id LIMIT 1) AS size_name
                 FROM service_requests r WHERE r.p4_warehouse_id = :warehouse_id
                 ORDER BY r.id DESC LIMIT ' . $limit,
                $warehouseId
            ),
            'transfers' => $this->rows(
                'SELECT d.id, d.transfer_code AS code, d.status,
                        COALESCE(d.received_at,d.dispatched_at,d.prepared_at) AS event_at,
                        d.received_by_label_snapshot AS person_label,
                        (SELECT COALESCE(SUM(i.quantity),0) FROM distribution_items i
                         WHERE i.distribution_id=d.id) AS total_quantity,
                        (SELECT COUNT(*) FROM distribution_items i
                         WHERE i.distribution_id=d.id) AS item_count,
                        (SELECT p.name FROM distribution_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         JOIN products p ON p.id=v.product_id
                         WHERE i.distribution_id=d.id ORDER BY i.id LIMIT 1) AS product_name,
                        (SELECT sz.name FROM distribution_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         LEFT JOIN sizes sz ON sz.id=v.size_id
                         WHERE i.distribution_id=d.id ORDER BY i.id LIMIT 1) AS size_name
                 FROM distributions d WHERE d.destination_warehouse_id = :warehouse_id
                 ORDER BY d.id DESC LIMIT ' . $limit,
                $warehouseId
            ),
            'deliveries' => $this->rows(
                'SELECT d.id, d.delivery_code AS code, d.status, d.delivered_at AS event_at,
                        d.recipient_label_snapshot AS person_label,
                        (SELECT COALESCE(SUM(i.quantity),0) FROM individual_delivery_items i
                         WHERE i.delivery_id=d.id) AS total_quantity,
                        (SELECT COUNT(*) FROM individual_delivery_items i
                         WHERE i.delivery_id=d.id) AS item_count,
                        (SELECT p.name FROM individual_delivery_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         JOIN products p ON p.id=v.product_id
                         WHERE i.delivery_id=d.id ORDER BY i.id LIMIT 1) AS product_name,
                        (SELECT sz.name FROM individual_delivery_items i
                         JOIN product_variants v ON v.id=i.product_variant_id
                         LEFT JOIN sizes sz ON sz.id=v.size_id
                         WHERE i.delivery_id=d.id ORDER BY i.id LIMIT 1) AS size_name
                 FROM individual_deliveries d WHERE d.p4_warehouse_id = :warehouse_id
                 ORDER BY d.id DESC LIMIT ' . $limit,
                $warehouseId
            ),
        ];
    }

    private function rows(string $sql, int $warehouseId): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['warehouse_id' => $warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
