<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Resumen de artículos del lote usando exclusivamente sus renglones reales.
 * No consulta ni modifica inventarios, recepciones, RRHH ni movimientos.
 */
final class ReceiptContentSummary
{
    /**
     * @param list<array<string,mixed>> $items
     * @return array{total_quantity:float,product_count:int,primary_name:?string,products:list<array{name:string,quantity:float,variant_count:int}>}
     */
    public static function fromItems(array $items): array
    {
        $products = [];
        $total = 0.0;
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $name = trim((string) ($item['product_name'] ?? ''));
            $name = $name !== '' ? $name : 'Artículo sin nombre';
            $key = $productId > 0 ? 'id:' . $productId : 'name:' . $name;
            $quantity = (float) ($item['quantity'] ?? 0);
            if (!is_finite($quantity) || $quantity < 0) {
                throw new \InvalidArgumentException('Cantidad de recepción inválida.');
            }
            if (!isset($products[$key])) {
                $products[$key] = [
                    'name' => $name,
                    'quantity' => 0.0,
                    'variant_count' => 0,
                ];
            }
            $products[$key]['quantity'] += $quantity;
            $products[$key]['variant_count']++;
            $total += $quantity;
        }
        $rows = array_values($products);
        return [
            'total_quantity' => $total,
            'product_count' => count($rows),
            'primary_name' => count($rows) === 1 ? $rows[0]['name'] : null,
            'products' => $rows,
        ];
    }
}
