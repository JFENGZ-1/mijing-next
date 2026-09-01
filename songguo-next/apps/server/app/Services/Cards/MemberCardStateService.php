<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MemberCardStateService
{
    public function __construct(private MemberCardHolidayCalendarService $holidays) {}

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function freeze(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->mutate($staff, $site, $memberCard, $payload['commandKey'], 'freeze', $payload, EntitlementLedgerEntryType::Freeze, function (MemberCard $locked, string $fingerprint) use ($staff, $site, $payload) {
            abort_unless($locked->status === MemberCardStatus::Active, 409, 'MEMBER_CARD_FREEZE_INVALID');
            abort_if($this->isOnHoliday($locked), 409, 'MEMBER_CARD_FREEZE_INVALID');

            $locked->status = MemberCardStatus::Frozen;
            $locked->freeze_state = [
                'frozenAt' => now()->toIso8601String(),
                'frozenByStaffId' => $staff->id,
                'reason' => $payload['reason'],
                'metadata' => ['commandPayloadHash' => $fingerprint],
            ];
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::Freeze,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => ['commandPayloadHash' => $fingerprint],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function unfreeze(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->mutate($staff, $site, $memberCard, $payload['commandKey'], 'unfreeze', $payload, EntitlementLedgerEntryType::FreezeLift, function (MemberCard $locked, string $fingerprint) use ($staff, $site, $payload) {
            abort_unless($locked->status === MemberCardStatus::Frozen, 409, 'MEMBER_CARD_UNFREEZE_INVALID');

            $locked->status = MemberCardStatus::Active;
            $locked->freeze_state = null;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::FreezeLift,
                'direction' => EntitlementLedgerDirection::Neutral,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => ['commandPayloadHash' => $fingerprint],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function startHoliday(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->mutate($staff, $site, $memberCard, $payload['commandKey'], 'holiday_start', $payload, EntitlementLedgerEntryType::HolidayApply, function (MemberCard $locked, string $fingerprint) use ($staff, $site, $payload) {
            abort_unless($locked->status === MemberCardStatus::Active, 409, 'MEMBER_CARD_HOLIDAY_INVALID');
            abort_if($this->isOnHoliday($locked), 409, 'MEMBER_CARD_HOLIDAY_INVALID');

            $startedAt = $payload['beginDate'] ?? now()->toDateString();
            $plannedEndAt = $payload['plannedEndDate'];

            abort_if(
                Carbon::parse($plannedEndAt)->lt(Carbon::parse($startedAt)),
                409,
                'MEMBER_CARD_HOLIDAY_INVALID',
            );
            $this->holidays->assertNoReservedService($locked, $site, $startedAt, $plannedEndAt);

            $locked->freeze_state = [
                'holiday' => [
                    'startedAt' => $startedAt,
                    'plannedEndAt' => $plannedEndAt,
                    'startedByStaffId' => $staff->id,
                ],
            ];
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::HolidayApply,
                'direction' => EntitlementLedgerDirection::Neutral,
                'valid_from_after' => $startedAt,
                'valid_until_after' => $plannedEndAt,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => ['plannedEndAt' => $plannedEndAt, 'commandPayloadHash' => $fingerprint],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function endHoliday(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->mutate($staff, $site, $memberCard, $payload['commandKey'], 'holiday_end', $payload, EntitlementLedgerEntryType::HolidayCancel, function (MemberCard $locked, string $fingerprint) use ($staff, $site, $payload) {
            abort_unless($locked->status === MemberCardStatus::Active, 409, 'MEMBER_CARD_HOLIDAY_INVALID');
            $holiday = $this->holidayState($locked);
            abort_if($holiday === null, 409, 'MEMBER_CARD_HOLIDAY_INVALID');

            $endedAt = $payload['endDate'] ?? now()->toDateString();
            $startedAt = $holiday['startedAt'];
            abort_if(Carbon::parse($endedAt)->lt(Carbon::parse($startedAt)), 409, 'MEMBER_CARD_HOLIDAY_INVALID');

            $ledgerEntryIds = [];
            $extensionDays = $this->inclusiveDaySpan($startedAt, $endedAt);
            $validityEntryId = null;

            if ($locked->card_type === CardType::Period && $locked->valid_until !== null && $extensionDays > 0) {
                $newValidUntil = Carbon::parse($locked->valid_until)->addDays($extensionDays)->toDateString();
                $locked->valid_until = $newValidUntil;

                $validityEntry = EntitlementLedgerEntry::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $locked->id,
                    'member_id' => $locked->member_id,
                    'entry_type' => EntitlementLedgerEntryType::ValidityChange,
                    'direction' => EntitlementLedgerDirection::Neutral,
                    'valid_from_after' => $locked->valid_from?->toDateString(),
                    'valid_until_after' => $newValidUntil,
                    'reason' => 'Holiday extension: '.$payload['reason'],
                    'metadata' => [
                        'extensionDays' => $extensionDays,
                        'holidayStartedAt' => $startedAt,
                        'holidayEndedAt' => $endedAt,
                    ],
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $validityEntryId = $validityEntry->id;
                $ledgerEntryIds[] = $validityEntryId;
            }

            $cancelEntry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::HolidayCancel,
                'direction' => EntitlementLedgerDirection::Neutral,
                'valid_from_after' => $startedAt,
                'valid_until_after' => $endedAt,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => array_filter([
                    'extensionDays' => $extensionDays,
                    'validityEntryId' => $validityEntryId,
                    'commandPayloadHash' => $fingerprint,
                ]),
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);
            $ledgerEntryIds[] = $cancelEntry->id;

            $locked->freeze_state = null;
            $locked->save();

            return [$locked->fresh(), $ledgerEntryIds];
        });
    }

    /**
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    public function extendValidity(Staff $staff, Site $site, MemberCard $memberCard, array $payload): array
    {
        $memberCard->loadMissing('member');
        abort_if($memberCard->member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        return $this->mutate($staff, $site, $memberCard, $payload['commandKey'], 'validity_extend', $payload, EntitlementLedgerEntryType::ValidityChange, function (MemberCard $locked, string $fingerprint) use ($staff, $site, $payload) {
            abort_unless(
                in_array($locked->status, [MemberCardStatus::Active, MemberCardStatus::Frozen], true),
                409,
                'MEMBER_CARD_VALIDITY_EXTENSION_INVALID',
            );
            abort_if($locked->valid_until === null, 409, 'MEMBER_CARD_VALIDITY_EXTENSION_INVALID');

            $newValidUntil = isset($payload['validUntil'])
                ? $payload['validUntil']
                : Carbon::parse($locked->valid_until)->addDays((int) $payload['extendDays'])->toDateString();

            abort_if(
                Carbon::parse($newValidUntil)->lt(Carbon::parse($locked->valid_until)),
                409,
                'MEMBER_CARD_VALIDITY_EXTENSION_INVALID',
            );

            $locked->valid_until = $newValidUntil;
            $locked->save();

            $entry = EntitlementLedgerEntry::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'member_card_id' => $locked->id,
                'member_id' => $locked->member_id,
                'entry_type' => EntitlementLedgerEntryType::ValidityChange,
                'direction' => EntitlementLedgerDirection::Neutral,
                'valid_from_after' => $locked->valid_from?->toDateString(),
                'valid_until_after' => $newValidUntil,
                'command_key' => $payload['commandKey'],
                'reason' => $payload['reason'],
                'metadata' => array_filter([
                    'extendDays' => $payload['extendDays'] ?? null,
                    'requestedValidUntil' => $payload['validUntil'] ?? null,
                    'commandPayloadHash' => $fingerprint,
                ]),
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);

            return [$locked->fresh(), [$entry->id]];
        });
    }

    /**
     * @param  callable(MemberCard,string): array{0: MemberCard, 1: list<int>}  $mutator
     * @return array{memberCard: MemberCard, ledgerEntryIds: list<int>, created: bool}
     */
    private function mutate(
        Staff $staff,
        Site $site,
        MemberCard $memberCard,
        string $commandKey,
        string $action,
        array $payload,
        EntitlementLedgerEntryType $expectedEntryType,
        callable $mutator,
    ): array {
        $fingerprint = $this->commandFingerprint($staff, $site, $memberCard, $action, $payload);

        return DB::transaction(function () use ($staff, $site, $memberCard, $commandKey, $expectedEntryType, $mutator, $fingerprint) {
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                $this->assertReplay($existingEntry, $staff, $site, $memberCard, $expectedEntryType, $fingerprint);
                $ledgerEntryIds = EntitlementLedgerEntry::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('command_key', $commandKey)
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                if (($existingEntry->metadata['validityEntryId'] ?? null) !== null) {
                    array_unshift($ledgerEntryIds, (int) $existingEntry->metadata['validityEntryId']);
                }

                return [
                    'memberCard' => MemberCard::query()
                        ->where('tenant_id', $staff->tenant_id)
                        ->whereKey($existingEntry->member_card_id)
                        ->firstOrFail(),
                    'ledgerEntryIds' => $ledgerEntryIds,
                    'created' => false,
                ];
            }

            $locked = MemberCard::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($memberCard->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A concurrent identical command can only become visible after the
            // card lock is acquired. Recheck before appending immutable ledger rows.
            $existingEntry = EntitlementLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($existingEntry) {
                $this->assertReplay($existingEntry, $staff, $site, $locked, $expectedEntryType, $fingerprint);

                $ledgerEntryIds = EntitlementLedgerEntry::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('command_key', $commandKey)
                    ->orderBy('id')->pluck('id')->all();
                if (($existingEntry->metadata['validityEntryId'] ?? null) !== null) {
                    array_unshift($ledgerEntryIds, (int) $existingEntry->metadata['validityEntryId']);
                }

                return [
                    'memberCard' => $locked->fresh(),
                    'ledgerEntryIds' => $ledgerEntryIds,
                    'created' => false,
                ];
            }

            [$card, $ledgerEntryIds] = $mutator($locked, $fingerprint);

            return [
                'memberCard' => $card,
                'ledgerEntryIds' => $ledgerEntryIds,
                'created' => true,
            ];
        });
    }

    private function assertReplay(
        EntitlementLedgerEntry $entry,
        Staff $staff,
        Site $site,
        MemberCard $memberCard,
        EntitlementLedgerEntryType $expectedEntryType,
        string $fingerprint,
    ): void {
        abort_unless(
            (int) $entry->site_id === (int) $site->id
            && (int) $entry->member_card_id === (int) $memberCard->id
            && (int) $entry->member_id === (int) $memberCard->member_id
            && (int) $entry->actor_staff_id === (int) $staff->id
            && $entry->entry_type === $expectedEntryType
            && hash_equals((string) ($entry->metadata['commandPayloadHash'] ?? ''), $fingerprint),
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );
    }

    private function commandFingerprint(
        Staff $staff,
        Site $site,
        MemberCard $memberCard,
        string $action,
        array $payload,
    ): string {
        unset($payload['commandKey']);
        ksort($payload);

        return hash('sha256', json_encode([
            'tenantId' => (int) $staff->tenant_id,
            'siteId' => (int) $site->id,
            'memberCardId' => (int) $memberCard->id,
            'memberId' => (int) $memberCard->member_id,
            'actorStaffId' => (int) $staff->id,
            'action' => $action,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }

    private function isOnHoliday(MemberCard $card): bool
    {
        return $this->holidayState($card) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function holidayState(MemberCard $card): ?array
    {
        $state = $card->freeze_state;

        return is_array($state) && isset($state['holiday']) && is_array($state['holiday'])
            ? $state['holiday']
            : null;
    }

    private function inclusiveDaySpan(string $from, string $to): int
    {
        return Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1;
    }
}
