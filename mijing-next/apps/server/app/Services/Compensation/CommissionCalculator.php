<?php

namespace App\Services\Compensation;

use InvalidArgumentException;

final class CommissionCalculator
{
    public function calculate(int $baseValueCents, int $rateBps, int $allocationBps = 10000): int
    {
        if ($baseValueCents < 0
            || $rateBps < 0 || $rateBps > 10000
            || $allocationBps < 0 || $allocationBps > 10000) {
            throw new InvalidArgumentException('Invalid commission operands.');
        }

        $rateAllocation = $rateBps * $allocationBps;
        $denominator = 100_000_000;
        $whole = intdiv($baseValueCents, $denominator);
        $remainder = $baseValueCents % $denominator;
        if ($rateAllocation !== 0 && $whole > intdiv(PHP_INT_MAX, $rateAllocation)) {
            throw new InvalidArgumentException('Commission result exceeds integer range.');
        }

        // Decompose before multiplication. DECIMAL(12,2) values can be close to
        // 1e12 cents; multiplying three operands directly would overflow PHP int.
        return ($whole * $rateAllocation)
            + intdiv(($remainder * $rateAllocation) + intdiv($denominator, 2), $denominator);
    }
}
