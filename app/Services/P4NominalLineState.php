<?php
declare(strict_types=1);

namespace App\Services;

/** Read-only classification of each requested line, never an inventory decision. */
final class P4NominalLineState
{
    /** @param array<string,mixed> $item
     * @return array{key:string,label:string}
     */
    public static function classify(array $item): array
    {
        if (empty($item['beneficiary_employee_id'])) {
            return ['key' => 'consolidated', 'label' => 'Cantidad consolidada'];
        }

        $approved = (float) ($item['quantity_approved'] ?? 0);
        $received = (float) ($item['quantity_received'] ?? 0);
        $delivered = (float) ($item['quantity_delivered'] ?? 0);
        if ($approved <= 0.000001) {
            return ['key' => 'review', 'label' => 'Pendiente de aprobación'];
        }
        if ($delivered + 0.000001 >= $approved) {
            return ['key' => 'delivered', 'label' => 'Entregado'];
        }
        if ($received > $delivered + 0.000001) {
            return ['key' => 'ready', 'label' => $delivered > 0.000001
                ? 'Entrega parcial · disponible'
                : 'Disponible para entregar'];
        }
        return ['key' => 'waiting', 'label' => $delivered > 0.000001
            ? 'Entrega parcial · por recibir'
            : 'Pendiente de recepción'];
    }
}
