<?php

namespace App\Services\Cards;

use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\Account;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class MemberCardLifecycleService
{
    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function archive(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->staffMutate($staff, $site, $memberCard, $payload['commandKey'], function (MemberCard $locked) use ($staff, $site, $payload) {
            abort_if(
                in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
                    || $locked->archived_at !== null,
                409,
                'MEMBER_CARD_ARCHIVE_INVALID',
            );

            $previousStatus = $locked->status;
            $voided = $this->hasConsumptionLedger($locked);
            $newStatus = $voided ? MemberCardStatus::Voided : MemberCardStatus::Archived;
            $entryType = $voided ? EntitlementLedgerEntryType::Void : EntitlementLedgerEntryType::Archive;

            $locked->status = $newStatus;
            $locked->archived_at = now();
            $locked->freeze_state = null;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => $entryType,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => [
                    'previousStatus' => $previousStatus->value,
                    'voided' => $voided,
                ],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function restore(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->staffMutate($staff, $site, $memberCard, $payload['commandKey'], function (MemberCard $locked) use ($staff, $site, $payload) {
            abort_unless($locked->status === MemberCardStatus::Archived, 409, 'MEMBER_CARD_RESTORE_INVALID');
            abort_if($locked->archived_at === null, 409, 'MEMBER_CARD_RESTORE_INVALID');
            abort_if($this->hasConsumptionLedger($locked), 409, 'MEMBER_CARD_RESTORE_INVALID');

            $previousStatus = $this->resolveArchivePreviousStatus($locked) ?? MemberCardStatus::Active;

            $locked->status = $previousStatus;
            $locked->archived_at = null;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::ArchiveRestore,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => [
                    'restoredStatus' => $previousStatus->value,
                ],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function hide(Member $member, Account $account, MemberCard $memberCard, array $payload): array
    {
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->memberMutate($member, $memberCard, $payload['commandKey'] ?? null, function (MemberCard $locked, Account $actor) use ($member, $payload) {
            abort_if(
                $locked->archived_at !== null
                    || in_array($locked->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true),
                409,
                'MEMBER_CARD_VISIBILITY_INVALID',
            );
            abort_unless(
                in_array($locked->status, [
                    MemberCardStatus::Active,
                    MemberCardStatus::PendingActivation,
                    MemberCardStatus::Frozen,
                ], true),
                409,
                'MEMBER_CARD_VISIBILITY_INVALID',
            );
            abort_if($locked->member_visibility === MemberCardVisibility::Hidden, 409, 'MEMBER_CARD_VISIBILITY_INVALID');

            $locked->member_visibility = MemberCardVisibility::Hidden;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $locked->site_id,
                'member_card_id' => $locked->id,
                'member_id' => $member->id,
                'entry_type' => EntitlementLedgerEntryType::VisibilityChange,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'] ?? null,
                'reason' => $payload['reason'] ?? 'Member hid card from wallet',
                'metadata' => ['visibility' => MemberCardVisibility::Hidden->value],
                'actor_account_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        }, $account);
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function restoreVisibility(Member $member, Account $account, MemberCard $memberCard, array $payload): array
    {
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->memberMutate($member, $memberCard, $payload['commandKey'] ?? null, function (MemberCard $locked, Account $actor) use ($member, $payload) {
            abort_unless($locked->member_visibility === MemberCardVisibility::Hidden, 409, 'MEMBER_CARD_VISIBILITY_INVALID');

            $locked->member_visibility = MemberCardVisibility::Visible;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $locked->site_id,
                'member_card_id' => $locked->id,
                'member_id' => $member->id,
                'entry_type' => EntitlementLedgerEntryType::VisibilityChange,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'] ?? null,
                'reason' => $payload['reason'] ?? 'Member restored card visibility',
                'metadata' => ['visibility' => MemberCardVisibility::Visible->value],
                'actor_account_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        }, $account);
    }

    /**
     * @param  callable(MemberCard): array{0: MemberCard, 1: list<int>}  $mutator
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    private function staffMutate(Staff $staff, Site $site, MemberCard $memberCard, string $commandKey, callable $mutator): array
    {
        return DB::transaction(function () use ($staff, $site, $memberCard, $commandKey, $mutator) {
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
                    'ledgerEntryIds' => EntitlementLedgerEntry::query()
                        ->where('tenant_id', $staff->tenant_id)
                        ->where('command_key', $commandKey)
                        ->orderBy('id')
                        ->pluck('id')
                        ->all(),
                    'created' => false,
                ];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            [$card, $ledgerEntryIds] = $mutator($locked);

            return [
                'memberCard' => $card,
                'ledgerEntryIds' => $ledgerEntryIds,
                'created' => true,
            ];
        });
    }

    /**
     * @param  callable(MemberCard, Account): array{0: MemberCard, 1: list<int>}  $mutator
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    private function memberMutate(Member $member, MemberCard $memberCard, ?string $commandKey, callable $mutator, Account $account): array
    {
        return DB::transaction(function () use ($member, $memberCard, $commandKey, $mutator, $account) {
            if ($commandKey !== null) {
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
                        'ledgerEntryIds' => [$existingEntry->id],
                        'created' => false,
                    ];
                }
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('member_id', $member->id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            [$card, $ledgerEntryIds] = $mutator($locked, $account);

            return [
                'memberCard' => $card,
                'ledgerEntryIds' => $ledgerEntryIds,
                'created' => true,
            ];
        });
    }

    private function hasConsumptionLedger(MemberCard $card): bool
    {
        return EntitlementLedgerEntry::query()
            ->where('member_card_id', $card->id)
            ->where(function ($query) {
                $query->where('entry_type', EntitlementLedgerEntryType::CountDeduct)
                    ->orWhere(function ($nested) {
                        $nested->where('entry_type', EntitlementLedgerEntryType::BalanceAdjust)
                            ->where('direction', EntitlementLedgerDirection::Debit);
                    });
            })
            ->exists();
    }

    private function resolveArchivePreviousStatus(MemberCard $card): ?MemberCardStatus
    {
        $entry = EntitlementLedgerEntry::query()
            ->where('member_card_id', $card->id)
            ->whereIn('entry_type', [
                EntitlementLedgerEntryType::Archive,
                EntitlementLedgerEntryType::Void,
            ])
            ->orderByDesc('id')
            ->first();

        $previous = $entry?->metadata['previousStatus'] ?? null;

        return is_string($previous) ? MemberCardStatus::tryFrom($previous) : null;
    }
}
