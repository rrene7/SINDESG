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
                'SELECT id, request_code AS code, status, requested_at AS event_at
                 FROM service_requests WHERE p4_warehouse_id = :warehouse_id
                 ORDER BY id DESC LIMIT ' . $limit,
                $warehouseId
            ),
            'transfers' => $this->rows(
                'SELECT id, transfer_code AS code, status, prepared_at AS event_at
                 FROM distributions WHERE destination_warehouse_id = :warehouse_id
                 ORDER BY id DESC LIMIT ' . $limit,
                $warehouseId
            ),
            'deliveries' => $this->rows(
                'SELECT id, delivery_code AS code, status, delivered_at AS event_at
                 FROM individual_deliveries WHERE p4_warehouse_id = :warehouse_id
                 ORDER BY id DESC LIMIT ' . $limit,
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
