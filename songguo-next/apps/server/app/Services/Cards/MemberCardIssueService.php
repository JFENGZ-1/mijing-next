<?php

namespace App\Services\Cards;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Compensation\MemberCardShareAssignmentService;
use App\Services\Compensation\MemberCardValueLotService;
use App\Services\Wallet\MemberWalletService;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberCardIssueService
{
    public function __construct(
        private MemberCardValueLotService $valueLots,
        private MemberCardShareAssignmentService $shareAssignments,
        private MemberWalletService $wallets,
        private CardProductPaymentMethodService $paymentMethods,
    ) {}

    /**
     * @return array{memberCard: MemberCard, created: bool}
     */
    public function issue(Staff $staff, Site $site, Member $member, array $payload): array
    {
        abort_if(
            (array_key_exists('actualAmount', $payload) || array_key_exists('paidAmountCents', $payload))
                && empty($payload['paymentMethod']),
            422,
            'MEMBER_CARD_PAYMENT_METHOD_REQUIRED',
        );
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');
        abort_unless(
            $site->tenant_id === $staff->tenant_id && $member->tenant_id === $staff->tenant_id,
            404,
        );

        $commandKey = $payload['commandKey'];
        $payloadHash = $this->issuePayloadHash($payload);

        return DB::transaction(function () use ($staff, $site, $member, $payload, $commandKey, $payloadHash) {
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                return $this->existingIssueResult($existingEntry, $site, $member, $payload, $payloadHash);
            }

            $product = CardProduct::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($payload['cardProductId'])
                ->lockForUpdate()
                ->firstOrFail();

            // The product lock serializes identical issue commands. Recheck after it is held.
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existingEntry) {
                return $this->existingIssueResult($existingEntry, $site, $member, $payload, $payloadHash);
            }

            abort_if(
                $product->catalog_status !== CardProductCatalogStatus::Active,
                409,
                'CARD_PRODUCT_NOT_ISSUABLE',
            );

            $product->load('courseScopes');
            $snapshot = $this->buildProductSnapshot($product);
            $openingType = isset($payload['openingType']) ? (string) $payload['openingType'] : null;
            $activationMode = $this->issueActivationMode((string) $product->activation_mode, $openingType);
            if ($openingType !== null) {
                $snapshot['openingType'] = $openingType;
            }
            if ($this->openingTypeOverridesActivation($openingType)) {
                // Card-instance override is a financial/validity fact. It must be
                // frozen in the same issue transaction and win over later product edits.
                $snapshot['activationModeOverride'] = $activationMode;
            }
            $isImmediate = $activationMode === 'immediate';
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
                $payloadHash,
            );

            $order = null;
            if (isset($payload['paymentMethod'])) {
                $paymentMethod = (string) $payload['paymentMethod'];
                abort_unless(
                    in_array($paymentMethod, $this->paymentMethods->methods($product), true),
                    422,
                    'CARD_PRODUCT_PAYMENT_METHOD_NOT_ALLOWED',
                );
                $paidAmountCents = array_key_exists('actualAmount', $payload)
                    ? Money::decimalToCents($payload['actualAmount'])
                    : (array_key_exists('paidAmountCents', $payload)
                        ? (int) $payload['paidAmountCents']
                        : Money::decimalToCents($product->price));
                abort_if($paidAmountCents < 0, 422, 'MEMBER_CARD_PAID_AMOUNT_INVALID');

                $order = MemberCardOrder::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'member_id' => $member->id,
                    'member_card_id' => $memberCard->id,
                    'order_no' => 'ORD-'.strtoupper((string) Str::ulid()),
                    'amount' => Money::centsToDecimal($paidAmountCents),
                    'payment_method' => $paymentMethod,
                    'paid_amount_cents' => $paidAmountCents,
                    'paid_at' => now(),
                    'status' => MemberCardOrderStatus::Paid,
                    'command_key' => $commandKey,
                    'created_by_staff_id' => $staff->id,
                    'metadata' => [
                        'channel' => $paymentMethod === 'online'
                            ? 'manual_staff_confirmed_online'
                            : 'staff_issue_balance',
                        'collectionConfirmation' => $paymentMethod === 'online'
                            ? 'manual_staff_confirmed'
                            : 'wallet_atomic_debit',
                        'gatewayTransactionId' => null,
                        'confirmedByActor' => ['type' => 'staff', 'id' => $staff->id],
                        'confirmationReason' => $payload['reason'] ?? null,
                        'cardProductId' => $product->id,
                        'productVersion' => $product->version,
                        'issuePayloadHash' => $payloadHash,
                    ],
                ]);
                if ($paymentMethod === 'balance' && $paidAmountCents > 0) {
                    $this->wallets->debitForOrder(
                        DomainActor::staff($staff),
                        $member,
                        $site,
                        $order,
                        $paidAmountCents,
                        'wallet:staff-issue:'.$order->id,
                    );
                }
                $this->valueLots->recordForOrder($order, $memberCard);
            } else {
                $this->valueLots->recordForIssue($memberCard, $payload);
            }

            if (array_key_exists('shareAssignments', $payload)) {
                $this->shareAssignments->replace(
                    $memberCard,
                    $site,
                    $payload['shareAssignments'] ?? [],
                    DomainActor::staff($staff),
                    'member-card:'.$memberCard->id.':share-assignment',
                );
            } else {
                $this->shareAssignments->assignOwnerDefaults($memberCard, $member, $site);
            }

            return ['memberCard' => $memberCard->fresh(), 'order' => $order?->fresh(), 'created' => true];
        });
    }

    private function existingIssueResult(
        EntitlementLedgerEntry $entry,
        Site $site,
        Member $member,
        array $payload,
        string $payloadHash,
    ): array {
        $card = MemberCard::query()
            ->where('tenant_id', $site->tenant_id)
            ->whereKey($entry->member_card_id)
            ->firstOrFail();
        abort_unless(
            $entry->entry_type === EntitlementLedgerEntryType::Issue
            && (int) $entry->site_id === $site->id
            && (int) $entry->member_id === $member->id
            && $card->site_id === $site->id
            && $card->member_id === $member->id
            && $card->card_product_id === (int) $payload['cardProductId'],
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );
        $storedHash = ($entry->metadata ?? [])['issuePayloadHash'] ?? null;
        abort_if($storedHash !== null && ! hash_equals((string) $storedHash, $payloadHash), 409, 'IDEMPOTENCY_KEY_REUSED');

        $order = MemberCardOrder::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('command_key', $entry->command_key)
            ->first();
        if (isset($payload['paymentMethod'])) {
            $expectedCents = array_key_exists('actualAmount', $payload)
                ? Money::decimalToCents($payload['actualAmount'])
                : (array_key_exists('paidAmountCents', $payload) ? (int) $payload['paidAmountCents'] : null);
            abort_unless(
                $order !== null
                && $order->member_card_id === $card->id
                && $order->payment_method === $payload['paymentMethod']
                && ($expectedCents === null || $order->paid_amount_cents === $expectedCents),
                409,
                'IDEMPOTENCY_KEY_REUSED',
            );
        } else {
            abort_if($order !== null, 409, 'IDEMPOTENCY_KEY_REUSED');
        }

        return ['memberCard' => $card, 'order' => $order, 'created' => false];
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
            $payloadHash = $this->purchasePayloadHash($account, $site, $member, $product);
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                return $this->existingPurchaseIssueResult($existingEntry, $account, $site, $member, $product, $payloadHash);
            }

            abort_unless(
                $product->tenant_id === $member->tenant_id && $product->site_id === $site->id,
                404,
            );

            $lockedProduct = CardProduct::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existingEntry) {
                return $this->existingPurchaseIssueResult($existingEntry, $account, $site, $member, $lockedProduct, $payloadHash);
            }

            $lockedProduct->load('courseScopes');
            $snapshot = $this->buildProductSnapshot($lockedProduct);
            $isImmediate = $lockedProduct->activation_mode === 'immediate';
            $status = $isImmediate ? MemberCardStatus::Active : MemberCardStatus::PendingActivation;
            [$cachedBalance, $cachedCount, $validFrom, $validUntil] = $this->openingEntitlements(
                $lockedProduct,
                $snapshot,
                [],
                $isImmediate,
            );

            $memberCard = MemberCard::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_product_id' => $lockedProduct->id,
                'card_type' => $lockedProduct->card_type,
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
                'direction' => $lockedProduct->card_type === CardType::Period
                    ? EntitlementLedgerDirection::Neutral
                    : EntitlementLedgerDirection::Credit,
                'amount_delta' => $cachedBalance,
                'count_delta' => $cachedCount,
                'valid_from_after' => $validFrom,
                'valid_until_after' => $validUntil,
                'command_key' => $commandKey,
                'reason' => 'Member purchase',
                'metadata' => ['purchasePayloadHash' => $payloadHash],
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
                $validUntil = now()->addDays($days - 1)->toDateString();
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
                ? [
                    null,
                    null,
                    now()->toDateString(),
                    now()->addDays(max(0, (int) $product->validity_days - 1))->toDateString(),
                ]
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
        string $payloadHash,
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
            'metadata' => ['issuePayloadHash' => $payloadHash],
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
            'allowedPaymentMethods' => $this->paymentMethods->methods($product),
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
        return Money::centsToDecimal(Money::decimalToCents($value));
    }

    private function issuePayloadHash(array $payload): string
    {
        $shares = collect($payload['shareAssignments'] ?? [])->map(fn ($assignment) => [
            'staffId' => (int) $assignment['staffId'],
            'compensationRoleId' => (int) $assignment['compensationRoleId'],
            'allocationBps' => (int) ($assignment['allocationBps'] ?? 10000),
            'effectiveFrom' => $assignment['effectiveFrom'] ?? null,
            'effectiveUntil' => $assignment['effectiveUntil'] ?? null,
        ])->sortBy(fn ($assignment) => implode(':', [
            $assignment['compensationRoleId'], $assignment['staffId'],
            $assignment['effectiveFrom'] ?? '', $assignment['effectiveUntil'] ?? '',
        ]))->values()->all();

        return hash('sha256', json_encode([
            'cardProductId' => (int) $payload['cardProductId'],
            'openingBalanceCents' => array_key_exists('openingBalance', $payload)
                ? Money::decimalToCents($payload['openingBalance'])
                : null,
            'openingCount' => array_key_exists('openingCount', $payload) ? (int) $payload['openingCount'] : null,
            'openingType' => $payload['openingType'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'paymentMethod' => $payload['paymentMethod'] ?? null,
            'paidAmountCents' => array_key_exists('actualAmount', $payload)
                ? Money::decimalToCents($payload['actualAmount'])
                : (array_key_exists('paidAmountCents', $payload) ? (int) $payload['paidAmountCents'] : null),
            'hasExplicitShareAssignments' => array_key_exists('shareAssignments', $payload),
            'shareAssignments' => $shares,
        ], JSON_THROW_ON_ERROR));
    }

    private function issueActivationMode(string $productMode, ?string $openingType): string
    {
        $candidate = $this->openingTypeOverridesActivation($openingType)
            ? (string) $openingType
            : $productMode;

        return match ($candidate) {
            'first_use', 'first-use', 'on_first_use' => 'first-use',
            'first_class', 'first-class', 'on_first_class' => 'first-class',
            'keep_pending', 'manual' => 'manual',
            'delayed' => 'delayed',
            default => 'immediate',
        };
    }

    private function openingTypeOverridesActivation(?string $openingType): bool
    {
        return $openingType !== null && ! in_array($openingType, ['new', 'legacy'], true);
    }

    private function purchasePayloadHash(
        Account $account,
        Site $site,
        Member $member,
        CardProduct $product,
    ): string {
        return hash('sha256', json_encode([
            'accountId' => $account->id,
            'tenantId' => $member->tenant_id,
            'siteId' => $site->id,
            'memberId' => $member->id,
            'cardProductId' => $product->id,
        ], JSON_THROW_ON_ERROR));
    }

    private function existingPurchaseIssueResult(
        EntitlementLedgerEntry $entry,
        Account $account,
        Site $site,
        Member $member,
        CardProduct $product,
        string $payloadHash,
    ): array {
        $card = MemberCard::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($entry->member_card_id)
            ->firstOrFail();
        $storedHash = ($entry->metadata ?? [])['purchasePayloadHash'] ?? null;
        abort_unless(
            $entry->entry_type === EntitlementLedgerEntryType::Purchase
            && (int) $entry->site_id === $site->id
            && (int) $entry->member_id === $member->id
            && (int) $entry->actor_account_id === $account->id
            && $card->site_id === $site->id
            && $card->member_id === $member->id
            && $card->card_product_id === $product->id
            && ($storedHash === null || hash_equals((string) $storedHash, $payloadHash)),
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );

        return ['memberCard' => $card, 'created' => false];
    }
}
