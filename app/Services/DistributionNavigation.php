<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Eligibility for displaying the "prepare distribution" action.
 * The controller and repository remain the authoritative server-side guards.
 */
final class DistributionNavigation
{
    public static function canPrepare(
        string $requestStatus,
        float $remainingQuantity,
        bool $p4Active,
        ?string $existingDistributionStatus
    ): bool {
        return in_array($requestStatus, ['approved', 'partially_approved'], true)
            && is_finite($remainingQuantity)
            && $remainingQuantity > 0.000001
            && $p4Active
            && !in_array($existingDistributionStatus, ['prepared', 'authorized', 'dispatched'], true);
    }
}
