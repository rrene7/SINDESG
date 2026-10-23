<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Server-side decision intent and nominal/group consistency.
 * Deliberately independent from the browser; the repository performs this
 * inside the locked transaction, before any approval or request update.
 */
final class CentralDecisionGuard
{
    /**
     * @param list<array<string,mixed>> $items
     * @param array<int,float> $approved
     * @param array<int,float> $groups
     */
    public static function validate(
        array $items,
        array $approved,
        array $groups,
        string $action,
        bool $partialConfirmed
    ): void {
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Seleccione expresamente aprobar o rechazar.');
        }
        if ($items === []) {
            throw new InvalidArgumentException('La solicitud no contiene destinatarios.');
        }
        $expectedItems = [];
        $expectedGroups = [];
        $groupApproved = [];
        $requestedTotal = 0.0;
        $approvedTotal = 0.0;
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $variant = (int) $item['product_variant_id'];
            $requested = (float) $item['quantity_requested'];
            if ($id <= 0 || $variant <= 0 || !is_finite($requested) || $requested <= 0) {
                throw new InvalidArgumentException('La nómina contiene un renglón inválido.');
            }
            if (!array_key_exists($id, $approved) || isset($expectedItems[$id])) {
                throw new InvalidArgumentException('Faltan asignaciones nominales. Actualice la página.');
            }
            $quantity = $approved[$id];
            if (!is_finite($quantity) || $quantity < 0 || $quantity > $requested + 0.000001) {
                throw new InvalidArgumentException('Cantidad nominal fuera del máximo solicitado.');
            }
            $expectedItems[$id] = true;
            $expectedGroups[$variant] = true;
            $groupApproved[$variant] = ($groupApproved[$variant] ?? 0.0) + $quantity;
            $approvedTotal += $quantity;
            $requestedTotal += $requested;
        }
        if (count($expectedItems) !== count($approved)
            || count($expectedGroups) !== count($groups)) {
            throw new InvalidArgumentException('La selección contiene filas o tallas ajenas a la solicitud.');
        }
        foreach ($groupApproved as $variant => $sum) {
            if (!array_key_exists($variant, $groups)
                || !is_finite($groups[$variant])
                || abs($sum - $groups[$variant]) > 0.000001) {
                throw new InvalidArgumentException(
                    'Una cantidad por talla no coincide con sus funcionarios. Valide y aplique el grupo antes de continuar.'
                );
            }
        }
        if ($action === 'reject') {
            if ($approvedTotal > 0.000001 || array_sum($groups) > 0.000001) {
                throw new InvalidArgumentException(
                    'Para rechazar debe retirar toda asignación y confirmarlo expresamente.'
                );
            }
            return;
        }
        if ($approvedTotal <= 0.000001) {
            throw new InvalidArgumentException(
                'No se puede aprobar cero unidades. Use la opción separada de rechazo si corresponde.'
            );
        }
        if ($approvedTotal < $requestedTotal - 0.000001 && !$partialConfirmed) {
            throw new InvalidArgumentException(
                'Confirme expresamente los funcionarios incluidos en la aprobación parcial.'
            );
        }
    }
}
