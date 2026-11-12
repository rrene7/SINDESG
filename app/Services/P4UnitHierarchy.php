<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic validation against the canonical RRHH parent_id chain.
 * A descendant must be different from its head and every traversed unit
 * must remain active/current. Cycles and incomplete paths fail closed.
 */
final class P4UnitHierarchy
{
    public static function isCurrent(?array $unit): bool
    {
        return $unit !== null
            && (int) ($unit['is_active'] ?? 0) === 1
            && (string) ($unit['lifecycle_status'] ?? 'vigente') === 'vigente';
    }

    /** @param callable(int):?array $findUnit */
    public static function isDescendant(int $childId, int $headId, callable $findUnit): bool
    {
        if ($childId <= 0 || $headId <= 0 || $childId === $headId) {
            return false;
        }

        $current = $childId;
        $visited = [];
        for ($depth = 0; $depth < 64; $depth++) {
            if (isset($visited[$current])) {
                return false;
            }
            $visited[$current] = true;
            $unit = $findUnit($current);
            if (!self::isCurrent($unit)) {
                return false;
            }

            $parentId = (int) ($unit['parent_id'] ?? 0);
            if ($parentId === $headId) {
                return self::isCurrent($findUnit($headId));
            }
            if ($parentId <= 0) {
                return false;
            }
            $current = $parentId;
        }
        return false;
    }
}
