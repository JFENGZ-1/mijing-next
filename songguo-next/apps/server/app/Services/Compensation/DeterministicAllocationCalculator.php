<?php

namespace App\Services\Compensation;

use InvalidArgumentException;

final class DeterministicAllocationCalculator
{
    /**
     * @param  list<int|string>  $stableKeys
     * @return array<int|string, int>
     */
    public function equal(int $totalCents, array $stableKeys): array
    {
        if ($totalCents < 0 || $stableKeys === []) {
            throw new InvalidArgumentException('Allocation requires a non-negative total and at least one key.');
        }

        $base = intdiv($totalCents, count($stableKeys));
        $remainder = $totalCents % count($stableKeys);
        $allocations = [];

        foreach (array_values($stableKeys) as $index => $key) {
            $allocations[$key] = $base + ($index < $remainder ? 1 : 0);
        }

        return $allocations;
    }

    public function unitSlice(int $totalCents, int $totalUnits, int $offset, int $units): int
    {
        if ($totalCents < 0 || $totalUnits < 1 || $offset < 0 || $units < 0 || $offset + $units > $totalUnits) {
            throw new InvalidArgumentException('Invalid unit allocation range.');
        }

        $base = intdiv($totalCents, $totalUnits);
        $remainderUnits = $totalCents % $totalUnits;
        $bonusStart = min($offset, $remainderUnits);
        $bonusEnd = min($offset + $units, $remainderUnits);

        return ($base * $units) + max(0, $bonusEnd - $bonusStart);
    }

    /**
     * Largest-remainder allocation with a stable key tie-break. The result always sums to totalCents.
     *
     * @param  array<string,int>  $weights
     * @return array<string,int>
     */
    public function weighted(int $totalCents, array $weights): array
    {
        if ($totalCents < 0 || $weights === [] || collect($weights)->contains(fn ($weight) => $weight < 0)) {
            throw new InvalidArgumentException('Weighted allocation requires non-negative values.');
        }
        $weightTotal = array_sum($weights);
        if ($weightTotal < 1) {
            throw new InvalidArgumentException('Weighted allocation requires positive total weight.');
        }

        $allocations = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $numerator = $totalCents * $weight;
            $allocations[$key] = intdiv($numerator, $weightTotal);
            $remainders[$key] = $numerator % $weightTotal;
        }
        $remaining = $totalCents - array_sum($allocations);
        $keys = array_keys($weights);
        usort($keys, fn ($left, $right) => $remainders[$right] <=> $remainders[$left]
            ?: strcmp((string) $left, (string) $right));
        for ($index = 0; $index < $remaining; $index++) {
            $allocations[$keys[$index]]++;
        }

        return $allocations;
    }
}
