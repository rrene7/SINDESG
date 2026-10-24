<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Read-only presentation of scoped product totals and a bounded size preview.
 * Product totals always come from the full per-product SQL aggregation.
 */
final class P4InventoryDigest
{
    public const INITIAL_SIZES = 5;
    public const MAX_PRODUCTS = 12;
    public const MAX_VARIANTS = 25;

    /** @return list<array<string,mixed>> */
    public static function group(array $products, array $variants): array
    {
        $groups = [];
        foreach (array_slice($products, 0, self::MAX_PRODUCTS) as $product) {
            $id = (int)($product['product_id'] ?? 0);
            if ($id <= 0) continue;
            $groups[$id] = [
                'id' => $id,
                'name' => (string)($product['product_name'] ?? 'Artículo sin nombre'),
                'total' => (float)($product['physical_quantity'] ?? 0),
                'variant_count' => (int)($product['variant_count'] ?? 0),
                'shown_variants' => 0,
                'sizes' => [],
            ];
        }
        foreach (array_slice($variants, 0, self::MAX_VARIANTS) as $variant) {
            $id = (int)($variant['product_id'] ?? 0);
            if (!isset($groups[$id])) continue;
            $size = (string)($variant['size_name'] ?? 'Sin talla');
            $groups[$id]['shown_variants']++;
            // More than one variant may share a size: combine visible rows.
            $groups[$id]['sizes'][$size] = ($groups[$id]['sizes'][$size] ?? 0.0)
                + (float)($variant['physical_quantity'] ?? 0);
        }
        foreach ($groups as &$group) {
            $sizes = [];
            foreach ($group['sizes'] as $size => $qty) {
                $sizes[] = ['name'=>(string)$size,'quantity'=>(float)$qty];
            }
            $group['sizes'] = $sizes;
            $group['first_sizes'] = array_slice($sizes,0,self::INITIAL_SIZES);
            $group['more_sizes'] = array_slice($sizes,self::INITIAL_SIZES);
            $group['complete'] = $group['shown_variants'] >= $group['variant_count'];
        }
        unset($group);
        return array_values($groups);
    }
}
