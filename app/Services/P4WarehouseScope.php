<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

/**
 * Explicit P4 warehouse scope; fail closed for non-Central/non-admin users.
 * Foundation only: controllers must invoke assertWarehouse() for every
 * record lookup and mutating action before this can be deployed.
 */
final class P4WarehouseScope
{
    public function __construct(private PDO $pdo) {}

    public function warehouseIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT s.warehouse_id FROM security_user_p4_scopes s
             INNER JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.user_id = :user_id AND w.warehouse_type = :warehouse_type
             ORDER BY s.warehouse_id'
        );
        $stmt->execute(['user_id' => $userId, 'warehouse_type' => 'P4']);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function authorized(
        array $roleCodes,
        array $allowedWarehouseIds,
        int $warehouseId
    ): bool {
        if ($warehouseId <= 0) {
            return false;
        }
        if (in_array('ADMIN', $roleCodes, true) || in_array('CENTRAL', $roleCodes, true)) {
            return true;
        }
        return in_array('P4', $roleCodes, true)
            && in_array($warehouseId, $allowedWarehouseIds, true);
    }

    public static function assertAuthorized(
        array $roleCodes,
        array $allowedWarehouseIds,
        int $warehouseId
    ): void {
        if (!self::authorized($roleCodes, $allowedWarehouseIds, $warehouseId)) {
            throw new InvalidArgumentException('No tiene acceso al almacén P4 solicitado.');
        }
    }
}
