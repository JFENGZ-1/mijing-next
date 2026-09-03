<?php

namespace Database\Seeders;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\CardProduct;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MemberCardOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        $member = $site
            ? Member::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('member_no', 'DEMO-MEMBER-001')
                ->first()
            ?? Member::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('account_id', 2)
                ->first()
            ?? Member::query()
                ->where('tenant_id', $site->tenant_id)
                ->whereKey(1)
                ->first()
            : null;
        $staff = $site
            ? Staff::query()->where('tenant_id', $site->tenant_id)->orderBy('id')->first()
            : null;
        $memberCard = $member
            ? MemberCard::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('member_id', $member->id)
                ->first()
            : null;

        if (! $site || ! $member || ! $staff) {
            $this->command?->warn('MemberCardOrderSeeder skipped: required local fixtures missing.');

            return;
        }

        if (! MemberCardOrder::query()->where('tenant_id', $site->tenant_id)->exists()) {
            MemberCardOrder::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'member_card_id' => $memberCard?->id,
                'order_no' => 'ORD-SEED-'.strtoupper(Str::random(6)),
                'amount' => 1000,
                'status' => MemberCardOrderStatus::Paid,
                'created_by_staff_id' => $staff->id,
            ]);

            MemberCardOrder::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'member_card_id' => $memberCard?->id,
                'order_no' => 'ORD-SEED-'.strtoupper(Str::random(6)),
                'amount' => 500,
                'status' => MemberCardOrderStatus::PendingPayment,
                'created_by_staff_id' => $staff->id,
            ]);
        }

        $this->seedReminderFixtures($site, $member, $staff);
    }

    private function seedReminderFixtures(Site $site, Member $member, Staff $staff): void
    {
        $periodProduct = CardProduct::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('card_type', CardType::Period)
            ->first();

        if ($periodProduct && ! MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('card_no', 'MC-REMIND-EXPIRING')
            ->exists()) {
            MemberCard::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_product_id' => $periodProduct->id,
                'card_type' => CardType::Period,
                'card_no' => 'MC-REMIND-EXPIRING',
                'status' => MemberCardStatus::Active,
                'product_snapshot' => ['name' => '即将到期期限卡'],
                'valid_from' => now()->subDays(20),
                'valid_until' => now()->addDays(7),
                'issued_at' => now()->subDays(20),
                'issued_by_staff_id' => $staff->id,
            ]);
        }

        if (! MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('card_no', 'MC-REMIND-PENDING')
            ->exists()) {
            MemberCard::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_type' => CardType::StoredValue,
                'card_no' => 'MC-REMIND-PENDING',
                'status' => MemberCardStatus::PendingActivation,
                'product_snapshot' => ['name' => '待开卡储值卡'],
                'issued_at' => now(),
                'issued_by_staff_id' => $staff->id,
            ]);
        }

        if (! MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('card_no', 'MC-REMIND-ZERO')
            ->exists()) {
            $zeroCard = MemberCard::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_type' => CardType::StoredValue,
                'card_no' => 'MC-REMIND-ZERO',
                'status' => MemberCardStatus::Active,
                'product_snapshot' => ['name' => '零余额储值卡'],
                'cached_balance' => 0,
                'issued_at' => now(),
                'issued_by_staff_id' => $staff->id,
            ]);

            EntitlementLedgerEntry::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $zeroCard->id,
                'member_id' => $member->id,
                'entry_type' => EntitlementLedgerEntryType::Issue,
                'direction' => EntitlementLedgerDirection::Credit,
                'amount_delta' => 0,
                'command_key' => (string) Str::uuid(),
                'reason' => 'Seed zero balance card',
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);
        }
    }
}
