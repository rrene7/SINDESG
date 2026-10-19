<?php
declare(strict_types=1);

namespace App\Services;

/** Human-readable unit quantities: omit unnecessary zero decimals. */
final class P4QuantityDisplay
{
    public static function units(float $quantity): string
    {
        $formatted = number_format($quantity, 3, ',', '.');
        return str_contains($formatted, ',')
            ? rtrim(rtrim($formatted, '0'), ',')
            : $formatted;
    }
}
