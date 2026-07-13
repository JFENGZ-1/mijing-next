<?php

namespace Database\Seeders;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Cards\MemberCardBookingRulesPatchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MemberCardSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $patched = $this->patchExistingStoredValueSnapshots();
        if ($patched > 0) {
            $this->command?->info("MemberCardSeeder: patched booking rules on {$patched} stored-value card(s).");
        }

        $site = Site::query()->find(1);
        $product = $site
            ? CardProduct::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->where('name', '储值卡 1000')
                ->first()
            : null;
        $member = $site
            ? Member::query()
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

        if (! $site || ! $product || ! $member || ! $staff) {
            $this->command?->warn('MemberCardSeeder skipped: required local fixtures missing.');

            return;
        }

        $product->load('courseScopes');
        $snapshot = $this->buildProductSnapshot($product);

        $existingCard = MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('member_id', $member->id)
            ->where('card_product_id', $product->id)
            ->first();

        if ($existingCard !== null) {
            $this->command?->info('MemberCardSeeder: member already has issued cards; skipped.');

            return;
        }

        if (MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('member_id', $member->id)
            ->exists()) {
            $this->command?->info('MemberCardSeeder: member already has issued cards; skipped.');

            return;
        }

        $memberCard = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-SEED-'.strtoupper(Str::random(8)),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => $snapshot,
            'cached_balance' => $product->face_value,
            'issued_at' => now(),
            'issued_by_staff_id' => $staff->id,
        ]);

        EntitlementLedgerEntry::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $memberCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => $product->face_value,
            'command_key' => (string) Str::uuid(),
            'reason' => 'Local seed issue',
            'actor_staff_id' => $staff->id,
            'occurred_at' => now(),
        ]);

        $this->command?->info("MemberCardSeeder: issued sample card {$memberCard->card_no} for member {$member->id}.");
    }

    private function patchExistingStoredValueSnapshots(): int
    {
        return app(MemberCardBookingRulesPatchService::class)->patchStoredValueSnapshots();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductSnapshot(CardProduct $product): array
    {
        return [
            'cardProductId' => $product->id,
            'cardType' => $product->card_type->value,
            'name' => $product->name,
            'price' => $this->decimalString($product->price),
            'faceValue' => $product->face_value !== null ? $this->decimalString($product->face_value) : null,
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
            'validityMode' => $product->validity_mode,
            'activationMode' => $product->activation_mode,
            'productVersion' => $product->version,
            'scopeConfig' => $product->scope_config,
            'bookingRules' => $product->booking_rules,
            'courseScopes' => $product->courseScopes
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CardProductCourseScope $scope) => [
                    'scopeKind' => $scope->scope_kind->value,
                    'scopeKey' => $scope->scope_key,
                    'displayName' => $scope->display_name,
                    'priceOverride' => $scope->price_override !== null
                        ? $this->decimalString($scope->price_override)
                        : null,
                    'sortOrder' => $scope->sort_order,
                ])
                ->all(),
        ];
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
