<?php
declare(strict_types=1);
namespace App\Services;

/** Short, factual Central ↔ P4 line, retaining full detail in source documents. */
final class P4CentralCardSummary
{
    public static function item(array $row): string
    {
        $count = (int)($row['item_count'] ?? 0);
        $quantity = (float)($row['total_quantity'] ?? 0);
        $name = trim((string)($row['product_name'] ?? ''));
        $size = trim((string)($row['size_name'] ?? ''));
        $units = $quantity > 0 ? P4QuantityDisplay::units($quantity).' unid.' : '';

        // A 516-line request may contain many boot sizes yet only one product.
        // Show the real product only when SQL confirms ALL lines share it.
        // Otherwise never describe a mixed document using its first item.
        if ($count > 1) {
            $productCount = (int)($row['product_count'] ?? 0);
            $quantityLabel = $quantity > 0
                ? P4QuantityDisplay::units($quantity)
                    .(abs($quantity - 1.0) < 0.000001 ? ' unidad' : ' unidades')
                : '';
            if ($productCount === 1 && $name !== '') {
                return $name.($quantityLabel !== '' ? ' · '.$quantityLabel : '');
            }
            if ($productCount > 1) {
                return $productCount.' artículos'
                    .($quantityLabel !== '' ? ' · '.$quantityLabel : '');
            }
            // Defensive fallback when the product query was unavailable.
            return $count.' renglones'.($units !== '' ? ' · '.$units : '');
        }
        $parts = [];
        if ($name !== '') $parts[] = $name;
        if ($size !== '' && $size !== 'S/T' && $size !== '—') $parts[] = 'Talla '.$size;
        if ($units !== '') $parts[] = $units;
        return $parts !== [] ? implode(' · ', $parts) : 'Consultar detalle';
    }

    public static function status(string $status): string
    {
        return match($status) {
            'draft'=>'Borrador','submitted'=>'Enviada','in_review'=>'En revisión',
            'approved'=>'Aprobada','partially_approved'=>'Parcial',
            'rejected'=>'No aprobada','fulfilled'=>'Atendida','closed'=>'Cerrada',
            'prepared'=>'Preparada','authorized'=>'Autorizada',
            'dispatched'=>'En camino','received'=>'Recibida',
            'cancelled'=>'Cancelada',default=>$status,
        };
    }
}
