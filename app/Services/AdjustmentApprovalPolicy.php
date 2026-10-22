<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Segregation of duties for stock-compensating adjustments.
 * Both endpoints and their service must reject unauthorized actions.
 */
final class AdjustmentApprovalPolicy
{
    public static function canDraft(array $actor): bool
    {
        return self::validActor($actor)
            && (in_array('ADMIN', (array) ($actor['role_codes'] ?? []), true)
                || in_array('CENTRAL', (array) ($actor['role_codes'] ?? []), true));
    }

    public static function canConfirm(array $actor): bool
    {
        return self::validActor($actor)
            && in_array('ADMIN', (array) ($actor['role_codes'] ?? []), true);
    }

    public static function assertDraftAllowed(array $actor): void
    {
        if (!self::canDraft($actor)) {
            throw new InvalidArgumentException(
                'Solo Almacén Central o el Administrador pueden preparar borradores de ajustes.'
            );
        }
    }

    public static function assertConfirmAllowed(array $actor): void
    {
        if (!self::canConfirm($actor)) {
            throw new InvalidArgumentException(
                'El registro de un ajuste no autoriza modificar existencias. La confirmación requiere ADMIN.'
            );
        }
    }

    public static function assertDifferentActor(?int $creatorId, array $approver): void
    {
        self::assertConfirmAllowed($approver);
        if ($creatorId === null || $creatorId <= 0) {
            throw new InvalidArgumentException(
                'Borrador sin trazabilidad de creador. No puede confirmarse: requiere regularización documentada.'
            );
        }
        if ($creatorId === (int) ($approver['id'] ?? 0)) {
            throw new InvalidArgumentException(
                'Separación de funciones: un usuario no puede confirmar el ajuste que preparó.'
            );
        }
    }

    private static function validActor(array $actor): bool
    {
        return (int) ($actor['id'] ?? 0) > 0;
    }
}
