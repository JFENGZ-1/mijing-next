<?php

namespace App\Services\Cards;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\EntitlementReservation;
use App\Models\MemberCard;
use App\Models\Site;
use App\Services\Catalog\CatalogCommandReceiptService;
use App\Services\Members\TenantMemberAccessService;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class CardTransferService
{
    public function __construct(
        private readonly CardTransferShareTokenService $tokens,
        private readonly MemberCardReadService $reader,
        private readonly TenantMemberAccessService $members,
        private readonly CatalogCommandReceiptService $receipts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $token): array
    {
        $payload = $this->tokens->verify($token);
        $card = $this->cardForPayload($payload);
        $site = Site::query()
            ->where('tenant_id', $card->tenant_id)
            ->whereKey($card->site_id)
            ->firstOrFail();

        $alreadyClaimed = $card->member_id !== $payload['fm'];

        $hasActiveBooking = $this->hasActiveBooking($card);

        return [
            'token' => $token,
            'expiresAt' => now()->createFromTimestamp($payload['exp'])->toIso8601String(),
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'card' => $this->reader->memberWalletSummary($card),
            'claimable' => ! $alreadyClaimed && ! $hasActiveBooking && in_array($card->status, [
                MemberCardStatus::Active,
                MemberCardStatus::PendingActivation,
                MemberCardStatus::Frozen,
            ], true),
            'alreadyClaimed' => $alreadyClaimed,
            'validMessage' => $alreadyClaimed
                ? '该卡已被领取'
                : ($hasActiveBooking ? '该卡存在进行中的预约，暂不可转赠' : null),
        ];
    }

    /**
     * @return array{memberCard: MemberCard, created: bool}
     */
    public function claim(Account $account, string $token, string $commandKey): array
    {
        $payload = $this->tokens->verify($token);

        return DB::transaction(function () use ($account, $payload, $token, $commandKey) {
            $cardSnapshot = MemberCard::query()
                ->where('tenant_id', $payload['t'])
                ->whereKey($payload['mc'])
                ->firstOrFail();
            $site = Site::query()
                ->where('tenant_id', $cardSnapshot->tenant_id)
                ->whereKey($cardSnapshot->site_id)
                ->lockForUpdate()
                ->firstOrFail();
            $card = MemberCard::query()
                ->where('tenant_id', $payload['t'])
                ->whereKey($payload['mc'])
                ->lockForUpdate()
                ->firstOrFail();

            $claimant = $this->members->member($account, $card->tenant_id);
            abort_unless($claimant, 404);
            $this->members->assertAppAccess($claimant);
            $payloadHash = $this->receipts->payloadHash([
                'tenantId' => (int) $payload['t'],
                'siteId' => (int) $site->id,
                'memberCardId' => (int) $payload['mc'],
                'fromMemberId' => (int) $payload['fm'],
                'toMemberId' => (int) $claimant->id,
                'accountId' => (int) $account->id,
                'tokenHash' => hash('sha256', $token),
            ]);
            $receipt = $this->receipts->replay(
                $site,
                'member_card_transfer_claim',
                'claim',
                $commandKey,
                $payloadHash,
            );
            if ($receipt !== null) {
                abort_unless(
                    (int) $receipt->resource_id === (int) $card->id
                    && (int) $card->member_id === (int) $claimant->id,
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );

                return ['memberCard' => $card->fresh(), 'created' => false];
            }

            $snapshot = $card->product_snapshot ?? [];
            $existingCommandKey = $snapshot['transferClaim']['commandKey'] ?? null;
            if ($existingCommandKey === $commandKey) {
                $storedFingerprint = $snapshot['transferClaim']['payloadHash'] ?? null;
                abort_unless(
                    (int) ($snapshot['transferClaim']['fromMemberId'] ?? 0) === (int) $payload['fm']
                    && (int) ($snapshot['transferClaim']['toMemberId'] ?? 0) === (int) $claimant->id
                    && (int) $card->member_id === (int) $claimant->id
                    && ($storedFingerprint === null || hash_equals((string) $storedFingerprint, $payloadHash)),
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );
                // Compatibility upgrade for claims created before durable receipts.
                $this->receipts->record(
                    DomainActor::account((int) $account->id),
                    $site,
                    'member_card_transfer_claim',
                    (int) $card->id,
                    'claim',
                    $commandKey,
                    $payloadHash,
                    1,
                    '会员领取转赠卡',
                );

                return ['memberCard' => $card->fresh(), 'created' => false];
            }

            abort_if($card->member_id !== $payload['fm'], 409, 'CARD_TRANSFER_ALREADY_CLAIMED');
            abort_unless(in_array($card->status, [
                MemberCardStatus::Active,
                MemberCardStatus::PendingActivation,
                MemberCardStatus::Frozen,
            ], true), 409, 'CARD_TRANSFER_NOT_CLAIMABLE');

            abort_if($claimant->id === $card->member_id, 422, 'CARD_TRANSFER_SELF_CLAIM');
            abort_if($this->hasActiveBooking($card), 409, 'CARD_TRANSFER_ACTIVE_BOOKING');

            $snapshot['transferClaim'] = [
                'commandKey' => $commandKey,
                'fromMemberId' => $card->member_id,
                'toMemberId' => $claimant->id,
                'payloadHash' => $payloadHash,
                'tokenHash' => hash('sha256', $token),
                'claimedAt' => now()->toIso8601String(),
            ];

            $card->member_id = $claimant->id;
            $card->member_visibility = MemberCardVisibility::Visible;
            $card->product_snapshot = $snapshot;
            $card->save();

            $this->receipts->record(
                DomainActor::account((int) $account->id),
                $site,
                'member_card_transfer_claim',
                (int) $card->id,
                'claim',
                $commandKey,
                $payloadHash,
                1,
                '会员领取转赠卡',
            );

            return ['memberCard' => $card->fresh(), 'created' => true];
        });
    }

    /**
     * @param  array{t: int, mc: int, fm: int, exp: int}  $payload
     */
    private function cardForPayload(array $payload): MemberCard
    {
        return MemberCard::query()
            ->where('tenant_id', $payload['t'])
            ->whereKey($payload['mc'])
            ->firstOrFail();
    }

    private function hasActiveBooking(MemberCard $card): bool
    {
        if (EntitlementReservation::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('status', 'reserved')
            ->exists()) {
            return true;
        }

        return Appointment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Waitlisted->value,
            ])
            ->exists();
    }
}
