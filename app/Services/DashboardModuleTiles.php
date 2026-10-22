<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Presentation-only dashboard policy. Hiding the redundant quick-access grid
 * does not grant or revoke route permissions; those remain enforced by RBAC.
 */
final class DashboardModuleTiles
{
    public static function visibleFor(array $user): bool
    {
        return in_array('ADMIN', (array) ($user['role_codes'] ?? []), true);
    }
}
