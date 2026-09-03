<?php

namespace Tests\Unit;

use App\Services\Compensation\CommissionCalculator;
use App\Services\Compensation\DeterministicAllocationCalculator;
use PHPUnit\Framework\TestCase;

class ConsumptionFinanceCalculatorTest extends TestCase
{
    private CommissionCalculator $commissions;

    private DeterministicAllocationCalculator $allocations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commissions = new CommissionCalculator;
        $this->allocations = new DeterministicAllocationCalculator;
    }

    public function test_stored_value_commission_uses_integer_cents_and_basis_points(): void
    {
        // 2000.00 consumed * 10% = 200.00.
        $this->assertSame(20_000, $this->commissions->calculate(200_000, 1_000));
    }

    public function test_count_card_uses_actual_paid_value_and_consumed_ordinal(): void
    {
        // 2000.00 / 10 units, consume units 4-5: 400.00 base * 10% = 40.00.
        $base = $this->allocations->unitSlice(200_000, 10, 3, 2);

        $this->assertSame(40_000, $base);
        $this->assertSame(4_000, $this->commissions->calculate($base, 1_000));
    }

    public function test_period_card_daily_value_and_single_session_example(): void
    {
        // 36500.00 / 365 inclusive service days = 100.00 daily value; 10% = 10.00.
        $dailyBase = $this->allocations->unitSlice(3_650_000, 365, 0, 1);

        $this->assertSame(10_000, $dailyBase);
        $this->assertSame(1_000, $this->commissions->calculate($dailyBase, 1_000));
    }

    public function test_period_daily_role_target_is_split_after_rounding_once(): void
    {
        // The role earns 10.00 for the entire day; two events share exactly 5.00 each.
        $dailyRoleTarget = $this->commissions->calculate(10_000, 1_000);
        $eventTargets = $this->allocations->equal($dailyRoleTarget, ['event-a', 'event-b']);

        $this->assertSame(['event-a' => 500, 'event-b' => 500], $eventTargets);
        $this->assertSame($dailyRoleTarget, array_sum($eventTargets));
    }

    public function test_recipient_rounding_uses_stable_largest_remainder_without_overpaying(): void
    {
        $twoRecipients = $this->allocations->weighted(1, ['staff-1' => 5_000, 'staff-2' => 5_000]);
        $threeRecipients = $this->allocations->weighted(2, [
            'staff-1' => 3_334,
            'staff-2' => 3_333,
            'staff-3' => 3_333,
        ]);

        $this->assertSame(['staff-1' => 1, 'staff-2' => 0], $twoRecipients);
        $this->assertSame(1, array_sum($twoRecipients));
        $this->assertSame(['staff-1' => 1, 'staff-2' => 1, 'staff-3' => 0], $threeRecipients);
        $this->assertSame(2, array_sum($threeRecipients));
    }

    public function test_mixed_count_lot_known_value_is_not_lost_by_unknown_units(): void
    {
        // A paid unit remains provable even when the same event also consumes one gifted/unknown unit.
        $knownValue = $this->allocations->unitSlice(1_001, 3, 0, 1);

        $this->assertSame(334, $knownValue);
        $this->assertSame(33, $this->commissions->calculate($knownValue, 1_000));
    }

    public function test_commission_near_database_money_limit_does_not_overflow(): void
    {
        $this->assertSame(
            999_800_010_000,
            $this->commissions->calculate(1_000_000_000_000, 9_999, 9_999),
        );
    }
}
