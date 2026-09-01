<?php

namespace App\Services\Wallet;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberWallet;
use App\Models\MemberWalletLedgerEntry;
use App\Models\Site;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Illuminate\Support\Facades\DB;

class MemberWalletService
{
    public function walletFor(Member $member, bool $lock = false): MemberWallet
    {
        $query = MemberWallet::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        $wallet = $query->first();
        if ($wallet !== null) {
            return $wallet;
        }

        MemberWallet::query()->insertOrIgnore([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'balance_cents' => 0,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = MemberWallet::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function summary(Member $member, int $ledgerLimit = 20): array
    {
        $wallet = $this->walletFor($member);

        return [
            'memberId' => $member->id,
            'tenantId' => $member->tenant_id,
            'balanceCents' => $wallet->balance_cents,
            'balance' => Money::centsToDecimal($wallet->balance_cents),
            'currency' => 'CNY',
            'version' => $wallet->version,
            'updatedAt' => $wallet->updated_at?->toIso8601String(),
            'ledgerEntries' => MemberWalletLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('member_wallet_id', $wallet->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(max(0, min($ledgerLimit, 100)))
                ->get()
                ->map(fn (MemberWalletLedgerEntry $entry) => $this->presentEntry($entry))
                ->all(),
        ];
    }

    public function adjust(DomainActor $actor, Site $site, Member $member, array $payload): array
    {
        abort_unless($member->tenant_id === $site->tenant_id, 404);
        $amountCents = (int) $payload['amountCents'];
        abort_if($amountCents === 0, 422, 'WALLET_ADJUSTMENT_ZERO');

        return DB::transaction(function () use ($actor, $site, $member, $payload, $amountCents) {
            $existing = MemberWalletLedgerEntry::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('command_key', $payload['commandKey'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertAdjustmentReplayMatches($existing, $actor, $site, $member, $amountCents, $payload);

                return ['wallet' => $this->walletFor($member), 'entry' => $existing, 'created' => false];
            }

            $wallet = $this->walletFor($member, true);
            $existing = MemberWalletLedgerEntry::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('command_key', $payload['commandKey'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertAdjustmentReplayMatches($existing, $actor, $site, $member, $amountCents, $payload);

                return ['wallet' => $wallet, 'entry' => $existing, 'created' => false];
            }
            if (array_key_exists('version', $payload)) {
                abort_unless((int) $payload['version'] === $wallet->version, 409, 'WALLET_VERSION_CONFLICT');
            }
            $nextBalance = $wallet->balance_cents + $amountCents;
            abort_if($nextBalance < 0, 409, 'INSUFFICIENT_WALLET_BALANCE');

            $wallet->update(['balance_cents' => $nextBalance, 'version' => $wallet->version + 1]);
            $entry = MemberWalletLedgerEntry::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_wallet_id' => $wallet->id,
                'member_id' => $member->id,
                'entry_type' => 'staff_adjustment',
                'direction' => $amountCents > 0 ? 'credit' : 'debit',
                'amount_cents' => abs($amountCents),
                'balance_after_cents' => $nextBalance,
                'command_key' => $payload['commandKey'],
                'actor_staff_id' => $actor->staffId(),
                'metadata' => [
                    ...$actor->metadata(),
                    'commandFingerprint' => $this->fingerprint([
                        'kind' => 'staff_adjustment',
                        'siteId' => $site->id,
                        'memberId' => $member->id,
                        'amountCents' => $amountCents,
                        'reason' => $payload['reason'],
                    ]),
                ],
                'reason' => $payload['reason'],
                'occurred_at' => now(),
            ]);

            return ['wallet' => $wallet->fresh(), 'entry' => $entry, 'created' => true];
        });
    }

    public function debitForPurchase(
        Account $actor,
        Member $member,
        Site $site,
        MemberCardOrder $order,
        int $amountCents,
        string $commandKey,
    ): MemberWalletLedgerEntry {
        return $this->debitForOrder(
            DomainActor::account($actor->id),
            $member,
            $site,
            $order,
            $amountCents,
            $commandKey,
        );
    }

    public function debitForOrder(
        DomainActor $actor,
        Member $member,
        Site $site,
        MemberCardOrder $order,
        int $amountCents,
        string $commandKey,
    ): MemberWalletLedgerEntry {
        abort_unless(
            $member->tenant_id === $site->tenant_id
            && $order->tenant_id === $site->tenant_id
            && $order->member_id === $member->id,
            404,
        );
        abort_if($amountCents < 1, 422, 'WALLET_PURCHASE_AMOUNT_INVALID');

        return DB::transaction(function () use ($actor, $member, $site, $order, $amountCents, $commandKey) {
            $existing = MemberWalletLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertDebitReplayMatches($existing, $actor, $member, $site, $order, $amountCents);

                return $existing;
            }

            $wallet = $this->walletFor($member, true);
            $existing = MemberWalletLedgerEntry::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertDebitReplayMatches($existing, $actor, $member, $site, $order, $amountCents);

                return $existing;
            }
            abort_if($wallet->balance_cents < $amountCents, 409, 'INSUFFICIENT_WALLET_BALANCE');
            $nextBalance = $wallet->balance_cents - $amountCents;
            $wallet->update(['balance_cents' => $nextBalance, 'version' => $wallet->version + 1]);

            return MemberWalletLedgerEntry::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $site->id,
                'member_wallet_id' => $wallet->id,
                'member_id' => $member->id,
                'entry_type' => 'card_purchase',
                'direction' => 'debit',
                'amount_cents' => $amountCents,
                'balance_after_cents' => $nextBalance,
                'command_key' => $commandKey,
                'order_id' => $order->id,
                'actor_account_id' => $actor->type === 'account' ? $actor->id : null,
                'actor_staff_id' => $actor->staffId(),
                'reason' => '会员钱包购卡',
                'metadata' => [
                    ...$actor->metadata(),
                    'commandFingerprint' => $this->fingerprint([
                        'kind' => 'card_purchase',
                        'siteId' => $site->id,
                        'memberId' => $member->id,
                        'orderId' => $order->id,
                        'amountCents' => $amountCents,
                    ]),
                ],
                'occurred_at' => now(),
            ]);
        });
    }

    public function presentEntry(MemberWalletLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entryType' => $entry->entry_type,
            'direction' => $entry->direction,
            'amountCents' => $entry->amount_cents,
            'balanceAfterCents' => $entry->balance_after_cents,
            'orderId' => $entry->order_id,
            'reason' => $entry->reason,
            'occurredAt' => $entry->occurred_at?->toIso8601String(),
        ];
    }

    private function assertAdjustmentReplayMatches(
        MemberWalletLedgerEntry $entry,
        DomainActor $actor,
        Site $site,
        Member $member,
        int $amountCents,
        array $payload,
    ): void {
        $fingerprint = $this->fingerprint([
            'kind' => 'staff_adjustment',
            'siteId' => $site->id,
            'memberId' => $member->id,
            'amountCents' => $amountCents,
            'reason' => $payload['reason'],
        ]);
        $metadata = $entry->metadata ?? [];
        $storedFingerprint = $metadata['commandFingerprint'] ?? null;
        $matches = (int) $entry->site_id === $site->id
            && (int) $entry->member_id === $member->id
            && $entry->entry_type === 'staff_adjustment'
            && $entry->direction === ($amountCents > 0 ? 'credit' : 'debit')
            && $entry->amount_cents === abs($amountCents)
            && $entry->reason === $payload['reason']
            && ($storedFingerprint === null || hash_equals((string) $storedFingerprint, $fingerprint))
            && ($metadata['actorType'] ?? $actor->type) === $actor->type
            && (int) ($metadata['actorId'] ?? $actor->id ?? 0) === (int) ($actor->id ?? 0);
        abort_unless($matches, 409, 'IDEMPOTENCY_KEY_REUSED');
    }

    private function assertDebitReplayMatches(
        MemberWalletLedgerEntry $entry,
        DomainActor $actor,
        Member $member,
        Site $site,
        MemberCardOrder $order,
        int $amountCents,
    ): void {
        $fingerprint = $this->fingerprint([
            'kind' => 'card_purchase',
            'siteId' => $site->id,
            'memberId' => $member->id,
            'orderId' => $order->id,
            'amountCents' => $amountCents,
        ]);
        $metadata = $entry->metadata ?? [];
        $storedFingerprint = $metadata['commandFingerprint'] ?? null;
        $matches = (int) $entry->site_id === $site->id
            && (int) $entry->member_id === $member->id
            && (int) $entry->order_id === $order->id
            && $entry->entry_type === 'card_purchase'
            && $entry->direction === 'debit'
            && $entry->amount_cents === $amountCents
            && ($storedFingerprint === null || hash_equals((string) $storedFingerprint, $fingerprint))
            && ($metadata['actorType'] ?? $actor->type) === $actor->type
            && (int) ($metadata['actorId'] ?? $actor->id ?? 0) === (int) ($actor->id ?? 0);
        abort_unless($matches, 409, 'IDEMPOTENCY_KEY_REUSED');
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
