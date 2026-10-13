<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Resumen de la necesidad por artículo y talla.
 * No aprueba, reserva ni mueve existencias: esos efectos siguen
 * exclusivamente en RequestRepository::decide y distribución.
 */
final class CentralRequestReview
{
    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public static function groups(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }
            if (!isset($groups[$variantId])) {
                $groups[$variantId] = [
                    'variant_id' => $variantId,
                    'product_name' => (string) ($item['product_name'] ?? ''),
                    'size_name' => (string) ($item['size_name'] ?? ''),
                    'variant_code' => (string) ($item['variant_code'] ?? ''),
                    'category_name' => (string) ($item['category_name'] ?? ''),
                    'central_stock' => (float) ($item['central_stock'] ?? 0),
                    'available_to_approve' => max(0.0, (float) ($item['available_to_approve'] ?? 0)),
                    'requested' => 0.0,
                    'approved' => 0.0,
                    'dispatched' => 0.0,
                    'received' => 0.0,
                    'delivered' => 0.0,
                    'recipients' => 0,
                    'items' => [],
                ];
            }
            $g = &$groups[$variantId];
            $g['requested'] += (float) ($item['quantity_requested'] ?? 0);
            $g['approved'] += (float) ($item['quantity_approved'] ?? 0);
            $g['dispatched'] += (float) ($item['quantity_dispatched'] ?? 0);
            $g['received'] += (float) ($item['quantity_received'] ?? 0);
            $g['delivered'] += (float) ($item['quantity_delivered'] ?? 0);
            if (!empty($item['beneficiary_employee_id'])) {
                $g['recipients']++;
            }
            $g['items'][] = $item;
            unset($g);
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int =>
            strcmp($a['product_name'], $b['product_name'])
            ?: (is_numeric($a['size_name']) && is_numeric($b['size_name'])
                ? ((float) $a['size_name'] <=> (float) $b['size_name'])
                : strnatcasecmp($a['size_name'], $b['size_name']))
        );
        return $groups;
    }
}
