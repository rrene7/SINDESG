<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

/**
 * Authorize P4 against current active assignments, never browser-supplied warehouse IDs.
 * Exact request-item ownership and delivery ownership are checked before every mutation.
 */
final class P4DeliveryGuard
{
    public function __construct(private PDO $pdo) {}

    public function assertNominal(int $userId, int $itemId): array
    {
        if ($userId <= 0 || $itemId <= 0) throw new RuntimeException('Entrega nominal no autorizada.');
        $stmt = $this->pdo->prepare(
            "SELECT i.id, r.p4_warehouse_id
             FROM service_request_items i
             JOIN service_requests r ON r.id=i.request_id
             JOIN security_user_p4_scopes scope ON scope.warehouse_id=r.p4_warehouse_id
             JOIN warehouses w ON w.id=scope.warehouse_id
             WHERE i.id=:item AND scope.user_id=:user
               AND w.warehouse_type='P4' AND w.is_active=1
               AND i.beneficiary_employee_id IS NOT NULL
               AND i.quantity_received > i.quantity_delivered
               AND r.status IN ('approved','partially_approved','fulfilled')
             LIMIT 1"
        );
        $stmt->execute(['item'=>$itemId,'user'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('El renglón no corresponde a un P4 autorizado o no está pendiente.');
        return $row;
    }

    public function assertDelivery(int $userId, int $deliveryId): void
    {
        if ($userId <= 0 || $deliveryId <= 0) throw new RuntimeException('Entrega no autorizada.');
        $stmt=$this->pdo->prepare(
            "SELECT 1 FROM individual_deliveries d
             JOIN security_user_p4_scopes s ON s.warehouse_id=d.p4_warehouse_id
             JOIN warehouses w ON w.id=s.warehouse_id
             WHERE d.id=:delivery AND s.user_id=:user
               AND w.warehouse_type='P4' AND w.is_active=1 LIMIT 1"
        );
        $stmt->execute(['delivery'=>$deliveryId,'user'=>$userId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Entrega de otro P4 no autorizada.');
    }
}
