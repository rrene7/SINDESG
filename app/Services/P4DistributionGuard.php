<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;
final class P4DistributionGuard {
    public function __construct(private PDO $pdo) {}
    public function assertAssigned(int $userId, int $distributionId): void {
        if ($userId <= 0 || $distributionId <= 0) throw new RuntimeException('Transferencia P4 no autorizada.');
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM distributions d
             JOIN security_user_p4_scopes s ON s.warehouse_id = d.destination_warehouse_id
             JOIN warehouses w ON w.id=s.warehouse_id
             WHERE d.id=:distribution_id AND s.user_id=:user_id
             AND w.warehouse_type='P4' AND w.is_active=1 LIMIT 1"
        );
        $stmt->execute(['distribution_id'=>$distributionId,'user_id'=>$userId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Transferencia de otro almacén P4 no autorizada.');
    }
}
