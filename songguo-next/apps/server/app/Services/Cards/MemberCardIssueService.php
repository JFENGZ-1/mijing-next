<?php

namespace App\Services\Cards;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberCardIssueService
{
    /**
     * @return array{memberCard: MemberCard, created: bool}
     */
    public function issue(Staff $staff, Site $site, Member $member, array $payload): array
    {
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        $commandKey = $payload['commandKey'];

        return DB::transaction(function () use ($staff, $site, $member, $payload, $commandKey) {
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                return [
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $staff->tenant_id)
                        ->whereKey($existingEntry->member_card_id)
                        ->firstOrFail(),
                    'created' => false,
                ];
            }

            $product = CardProduct::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($payload['cardProductId'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $product->catalog_status !== CardProductCatalogStatus::Active,
                409,
                'CARD_PRODUCT_NOT_ISSUABLE',
            );

            $product->load('courseScopes');
            $snapshot = $this->buildProductSnapshot($product);
            $isImmediate = $product->activation_mode === 'immediate';
            $status = $isImmediate ? MemberCardStatus::Active : MemberCardStatus::PendingActivation;
            [$cachedBalance, $cachedCount, $validFrom, $validUntil] = $this->openingEntitlements(
                $product,
                $snapshot,
                $payload,
                $isImmediate,
            );

            $memberCard = MemberCard::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_product_id' => $product->id,
                'card_type' => $product->card_type,
                'card_no' => 'MC-'.strtoupper((string) Str::ulid()),
                'status' => $status,
                'product_snapshot' => $snapshot,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'cached_balance' => $cachedBalance,
                'cached_remaining_count' => $cachedCount,
                'issued_at' => now(),
                'issued_by_staff_id' => $staff->id,
            ]);

            $this->appendIssueLedger(
                $staff->tenant_id,
                $site->id,
                $memberCard,
                $member,
                $product->card_type,
                $cachedBalance,
                $cachedCount,
                $validFrom,
                $validUntil,
                $commandKey,
                $staff->id,
            );

            return ['memberCard' => $memberCard->fresh(), 'created' => true];
        });
    }

    /**
     * @return array{memberCard: MemberCard, created: bool}
     */
    public function purchaseIssue(
        Account $account,
        Site $site,
        Member $member,
        CardProduct $product,
        string $commandKey,
    ): array {
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return DB::transaction(function () use ($account, $site, $member, $product, $commandKey) {
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                return [
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $member->tenant_id)
                        ->whereKey($existingEntry->member_card_id)
                        ->firstOrFail(),
                    'created' => false,
                ];
            }

            abort_unless(
                $product->tenant_id === $member->tenant_id && $product->site_id === $site->id,
                404,
            );

            $product->load('courseScopes');
            $snapshot = $this->buildProductSnapshot($product);
            $isImmediate = $product->activation_mode === 'immediate';
            $status = $isImmediate ? MemberCardStatus::Active : MemberCardStatus::PendingActivation;
            [$cachedBalance, $cachedCount, $validFrom, $validUntil] = $this->openingEntitlements(
                $product,
                $snapshot,
                [],
                $isImmediate,
            );

            $memberCard = MemberCard::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_product_id' => $product->id,
                'card_type' => $product->card_type,
                'card_no' => 'MC-'.strtoupper((string) Str::ulid()),
                'status' => $status,
                'product_snapshot' => $snapshot,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'cached_balance' => $cachedBalance,
                'cached_remaining_count' => $cachedCount,
                'issued_at' => now(),
            ]);

            EntitlementLedgerEntry::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $memberCard->id,
                'member_id' => $member->id,
                'entry_type' => EntitlementLedgerEntryType::Purchase,
                'direction' => $product->card_type === CardType::Period
                    ? EntitlementLedgerDirection::Neutral
                    : EntitlementLedgerDirection::Credit,
                'amount_delta' => $cachedBalance,
                'count_delta' => $cachedCount,
                'valid_from_after' => $validFrom,
                'valid_until_after' => $validUntil,
                'command_key' => $commandKey,
                'reason' => 'Member purchase',
                'actor_account_id' => $account->id,
                'occurred_at' => now(),
            ]);

            return ['memberCard' => $memberCard->fresh(), 'created' => true];
        });
    }

    /**
     * @return array{memberCard: MemberCard, activated: bool}
     */
    public function activate(Account $account, MemberCard $memberCard, ?string $commandKey = null): array
    {
        return DB::transaction(function () use ($account, $memberCard, $commandKey) {
            $locked = MemberCard::query()
                ->where('tenant_id', $memberCard->tenant_id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === MemberCardStatus::Active) {
                return ['memberCard' => $locked, 'activated' => false];
            }

            abort_unless(
                $locked->status === MemberCardStatus::PendingActivation,
                409,
                'MEMBER_CARD_ACTIVATION_INVALID',
            );

            $snapshot = $locked->product_snapshot;
            $cardType = CardType::from($snapshot['cardType'] ?? $locked->card_type->value);
            $updates = ['status' => MemberCardStatus::Active];
            $validFrom = null;
            $validUntil = null;

            if ($cardType === CardType::Period) {
                $days = (int) ($snapshot['validityDays'] ?? 0);
                abort_if($days < 1, 409, 'MEMBER_CARD_ACTIVATION_INVALID');
                $validFrom = now()->toDateString();
                $validUntil = now()->addDays($days)->toDateString();
                $updates['valid_from'] = $validFrom;
                $updates['valid_until'] = $validUntil;
            }

            $locked->update($updates);

            if ($cardType === CardType::Period) {
                EntitlementLedgerEntry::create([
                    'tenant_id' => $locked->tenant_id,
                    'site_id' => $locked->site_id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::ValidityChange,
                    'direction' => EntitlementLedgerDirection::Neutral,
                    'valid_from_after' => $validFrom,
                    'valid_until_after' => $validUntil,
                    'command_key' => $commandKey,
                    'reason' => 'Member activation',
                    'actor_account_id' => $account->id,
                    'occurred_at' => now(),
                ]);
            }

            return ['memberCard' => $locked->fresh(), 'activated' => true];
        });
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?string, 3: ?string}
     */
    private function openingEntitlements(
        CardProduct $product,
        array $snapshot,
        array $payload,
        bool $isImmediate,
    ): array {
        return match ($product->card_type) {
            CardType::StoredValue => [
                $this->decimalString($payload['openingBalance'] ?? $product->face_value),
                null,
                null,
                null,
            ],
            CardType::Count => [
                null,
                (int) ($payload['openingCount'] ?? $product->initial_count),
                null,
                null,
            ],
            CardType::Period => $isImmediate
                ? [null, null, now()->toDateString(), now()->addDays((int) $product->validity_days)->toDateString()]
                : [null, null, null, null],
        };
    }

    private function appendIssueLedger(
        int $tenantId,
        int $siteId,
        MemberCard $memberCard,
        Member $member,
        CardType $cardType,
        ?string $cachedBalance,
        ?int $cachedCount,
        ?string $validFrom,
        ?string $validUntil,
        string $commandKey,
        int $staffId,
    ): void {
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenantId,
            'site_id' => $siteId,
            'member_card_id' => $memberCard->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => $cardType === CardType::Period
                ? EntitlementLedgerDirection::Neutral
                : EntitlementLedgerDirection::Credit,
            'amount_delta' => $cachedBalance,
            'count_delta' => $cachedCount,
            'valid_from_after' => $validFrom,
            'valid_until_after' => $validUntil,
            'command_key' => $commandKey,
            'reason' => 'Staff issue',
            'actor_staff_id' => $staffId,
            'occurred_at' => now(),
        ]);
    }

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
