<?php

namespace App\Console\Commands;

use App\Services\Cards\MemberCardBookingRulesPatchService;
use Illuminate\Console\Command;

class PatchMemberCardBookingRules extends Command
{
    protected $signature = 'member-cards:patch-booking-rules';

    protected $description = 'Backfill bookingRules.defaultPrice on stored-value member card snapshots';

    public function handle(MemberCardBookingRulesPatchService $patchService): int
    {
        $patched = $patchService->patchStoredValueSnapshots();

        $this->info("Patched booking rules on {$patched} stored-value card(s).");

        return self::SUCCESS;
    }
}
