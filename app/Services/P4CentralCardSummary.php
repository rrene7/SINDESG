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

        // Multiple lines: the first product alone would misrepresent the order.
        if ($count > 1) {
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
