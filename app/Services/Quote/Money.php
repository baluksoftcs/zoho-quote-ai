<?php

namespace App\Services\Quote;

use InvalidArgumentException;

final class Money
{
    // "12500" or 12500.5 → paise as an integer (1250050)
    public static function toPaise(int|float|string $amount): int
    {
        $str = is_string($amount) ? trim($amount) : number_format((float) $amount, 2, '.', '');

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $str)) {
            throw new InvalidArgumentException("Invalid money amount: {$str}");
        }

        [$rupees, $paise] = array_pad(explode('.', $str), 2, '0');

        return ((int) $rupees * 100) + (int) str_pad($paise, 2, '0');
    }

    // 1250050 → "12500.50"
    public static function format(int $paise): string
    {
        return sprintf('%d.%02d', intdiv($paise, 100), $paise % 100);
    }

    // Percentage in basis points, rounded half-up. 1800 bp = 18%
    public static function percentOf(int $paise, int $basisPoints): int
    {
        return intdiv($paise * $basisPoints + 5000, 10000);
    }
}
