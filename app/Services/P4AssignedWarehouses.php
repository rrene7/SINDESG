<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Read-only P4 assignments from SINDESG migration 020. Never infer a unit from employee name. */
final class P4AssignedWarehouses
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(int $userId): array
    {
        if ($userId <= 0) return [];
        $q = $this->pdo->prepare(
            "SELECT w.id, w.code, w.name, w.organization_unit_name_snapshot
             FROM security_user_p4_scopes s
             JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.user_id = :user_id AND w.warehouse_type = 'P4' AND w.is_active = 1
             ORDER BY w.code"
        );
        $q->execute(['user_id' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
