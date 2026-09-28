<?php

namespace KhaledHajSalem\Zatca\Support;

/**
 * Decimal helpers for invoice amounts (XML Implementation Standard §10).
 */
class Money
{
    /**
     * Round half-up to the given number of decimals.
     *
     * Binary floats store values such as 3 × 3.335 as 10.00499…, which
     * round() takes down to 10.00. Snapping the scaled value to 8 decimals
     * first recovers the intended 1000.5, so half-way values round up.
     */
    public static function round(float $value, int $decimals = 2): float
    {
        $factor = 10 ** $decimals;

        return round((float)sprintf('%.8F', $value * $factor)) / $factor;
    }

    /**
     * Format a decimal without losing precision, keeping at least two
     * decimals (e.g. 3 → "3.00", 3.335 → "3.335"). Used for unit prices
     * and quantities, which have no decimal limit (§7.3).
     */
    public static function format(float $value, int $maxDecimals = 6): string
    {
        $formatted = rtrim(number_format($value, $maxDecimals, '.', ''), '0');
        [$integer, $fraction] = explode('.', $formatted) + [1 => ''];

        return $integer . '.' . str_pad($fraction, 2, '0');
    }
}
