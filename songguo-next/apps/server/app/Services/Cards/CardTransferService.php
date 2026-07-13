<?php

namespace App\Services\Cards;

use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
use App\Models\MemberCard;
use App\Models\Site;
use App\Services\Members\TenantMemberAccessService;
use Illuminate\Support\Facades\DB;

class CardTransferService
{
    public function __construct(
        private readonly CardTransferShareTokenService $tokens,
        private readonly MemberCardReadService $reader,
        private readonly TenantMemberAccessService $members,
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

        return [
            'token' => $token,
            'expiresAt' => now()->createFromTimestamp($payload['exp'])->toIso8601String(),
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'card' => $this->reader->memberWalletSummary($card),
            'claimable' => ! $alreadyClaimed && in_array($card->status, [
                MemberCardStatus::Active,
                MemberCardStatus::PendingActivation,
                MemberCardStatus::Frozen,
            ], true),
            'alreadyClaimed' => $alreadyClaimed,
            'validMessage' => $alreadyClaimed ? '该卡已被领取' : null,
        ];
    }

    /**
     * @return array{memberCard: MemberCard, created: bool}
     */
    public function claim(Account $account, string $token, string $commandKey): array
    {
        $payload = $this->tokens->verify($token);

        return DB::transaction(function () use ($account, $payload, $commandKey) {
            $card = MemberCard::query()
                ->where('tenant_id', $payload['t'])
                ->whereKey($payload['mc'])
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = $card->product_snapshot ?? [];
            $existingCommandKey = $snapshot['transferClaim']['commandKey'] ?? null;
            if ($existingCommandKey === $commandKey) {
                return ['memberCard' => $card->fresh(), 'created' => false];
            }

            abort_if($card->member_id !== $payload['fm'], 409, 'CARD_TRANSFER_ALREADY_CLAIMED');
            abort_unless(in_array($card->status, [
                MemberCardStatus::Active,
                MemberCardStatus::PendingActivation,
                MemberCardStatus::Frozen,
            ], true), 409, 'CARD_TRANSFER_NOT_CLAIMABLE');

            $claimant = $this->members->member($account, $card->tenant_id);
            abort_unless($claimant, 404);
            $this->members->assertAppAccess($claimant);
            abort_if($claimant->id === $card->member_id, 422, 'CARD_TRANSFER_SELF_CLAIM');

            $snapshot['transferClaim'] = [
                'commandKey' => $commandKey,
                'fromMemberId' => $card->member_id,
                'toMemberId' => $claimant->id,
                'claimedAt' => now()->toIso8601String(),
            ];

            $card->member_id = $claimant->id;
            $card->member_visibility = MemberCardVisibility::Visible;
            $card->product_snapshot = $snapshot;
            $card->save();

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
}
