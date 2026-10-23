<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Presentation-only aggregation. All nominal request_item_id values stay
 * intact: DistributionRepository::createPrepared receives per-item quantities.
 */
final class DistributionPreparationGroups
{
    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public static function groups(array $items): array
    {
        $groups = [];
        $seen = [];
        foreach ($items as $item) {
            $id = (int) ($item['request_item_id'] ?? 0);
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $pending = (float) ($item['remaining_quantity'] ?? 0);
            if ($id <= 0 || $variantId <= 0 || !is_finite($pending) || $pending <= 0
                || isset($seen[$id])) {
                throw new InvalidArgumentException('Renglón nominal de distribución inválido o duplicado.');
            }
            $seen[$id] = true;
            if (!isset($groups[$variantId])) {
                $groups[$variantId] = [
                    'variant_id' => $variantId,
                    'product_name' => (string) ($item['product_name'] ?? ''),
                    'category_name' => (string) ($item['category_name'] ?? ''),
                    'size_name' => (string) ($item['size_name'] ?? ''),
                    'variant_code' => (string) ($item['variant_code'] ?? ''),
                    'central_stock' => max(0.0, (float) ($item['central_stock'] ?? 0)),
                    'approved' => 0.0,
                    'dispatched' => 0.0,
                    'pending' => 0.0,
                    'items' => [],
                ];
            }
            $groups[$variantId]['approved'] += (float) ($item['quantity_approved'] ?? 0);
            $groups[$variantId]['dispatched'] += (float) ($item['quantity_dispatched'] ?? 0);
            $groups[$variantId]['pending'] += $pending;
            $groups[$variantId]['items'][] = $item;
        }
        $result = array_values($groups);
        usort($result, static fn(array $a, array $b): int =>
            strnatcasecmp($a['product_name'], $b['product_name'])
            ?: strnatcasecmp($a['size_name'], $b['size_name']));
        return $result;
    }
}
