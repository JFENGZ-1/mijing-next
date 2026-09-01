<?php

namespace App\Support\Finance;

use InvalidArgumentException;

final class Money
{
    public static function decimalToCents(mixed $value): int
    {
        $normalized = trim((string) $value);
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid decimal money value.');
        }

        $negative = ($matches[1] ?? '') === '-';
        $whole = (int) $matches[2];
        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $cents = ($whole * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
