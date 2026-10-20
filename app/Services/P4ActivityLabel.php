<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Labels for scoped P4 activity only. Never fabricates a person, product or
 * quantity; underlying codes and row ids remain available for audit.
 */
final class P4ActivityLabel
{
    public static function title(string $type, array $row): string
    {
        $person = trim((string) ($row['person_label'] ?? ''));
        $status = (string) ($row['status'] ?? '');
        $title = match ($type) {
            'requests' => $person !== '' ? 'Solicitud de ' . $person : 'Solicitud de dotación',
            'transfers' => $status === 'received'
                ? ($person !== '' ? 'Transferencia recibida por ' . $person . ' desde Central' : 'Transferencia recibida de Central')
                : 'Transferencia desde Central',
            'deliveries' => $person !== '' ? 'Entrega a ' . $person : 'Entrega individual de dotación',
            default => 'Movimiento de dotación',
        };
        $product = trim((string) ($row['product_name'] ?? ''));
        if ($product !== '') {
            $title .= ' · ' . $product;
            $size = trim((string) ($row['size_name'] ?? ''));
            if ($size !== '' && $size !== 'S/T' && $size !== '—') $title .= ' · Talla ' . $size;
        }
        $items = (int) ($row['item_count'] ?? 0);
        if ($items > 1) $title .= ' · ' . $items . ' renglones';
        $quantity = (float) ($row['total_quantity'] ?? 0);
        if ($quantity > 0) {
            $title .= ' · ' . P4QuantityDisplay::units($quantity)
                . (abs($quantity - 1.0) < 0.000001 ? ' unidad' : ' unidades');
        }
        return $title;
    }
}
