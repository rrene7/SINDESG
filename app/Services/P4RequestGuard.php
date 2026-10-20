<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

/** Authorization guard against the SINDESG database; never trusts a posted P4 id. */
final class P4RequestGuard
{
    public function __construct(private PDO $pdo) {}

    public function allowed(int $userId, int $warehouseId): bool
    {
        if ($userId <= 0 || $warehouseId <= 0) return false;
        $q = $this->pdo->prepare(
            "SELECT 1 FROM security_user_p4_scopes s
             JOIN warehouses w ON w.id=s.warehouse_id
             WHERE s.user_id=:uid AND s.warehouse_id=:wid
               AND w.warehouse_type='P4' AND w.is_active=1 LIMIT 1"
        );
        $q->execute(['uid'=>$userId,'wid'=>$warehouseId]);
        return (bool) $q->fetchColumn();
    }

    public function assertRequest(int $userId, int $requestId): void
    {
        if ($requestId <= 0 || $userId <= 0) throw new RuntimeException('Solicitud no autorizada.');
        $q = $this->pdo->prepare(
            "SELECT 1 FROM service_requests r
             JOIN security_user_p4_scopes s ON s.warehouse_id=r.p4_warehouse_id
             JOIN warehouses w ON w.id=s.warehouse_id
             WHERE r.id=:rid AND s.user_id=:uid
               AND w.warehouse_type='P4' AND w.is_active=1 LIMIT 1"
        );
        $q->execute(['rid'=>$requestId,'uid'=>$userId]);
        if (!$q->fetchColumn()) throw new RuntimeException('Solicitud no autorizada para este P4.');
    }
}
