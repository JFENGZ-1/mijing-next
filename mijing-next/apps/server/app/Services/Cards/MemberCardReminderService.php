<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Models\MemberCard;
use App\Models\MemberCardReminderConfig;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MemberCardReminderService
{
    public const DEFAULT_EXPIRING_WITHIN_DAYS = 30;

    public const DEFAULT_ZERO_BALANCE_THRESHOLD = 0;

    public function configForSite(Staff $staff, Site $site): array
    {
        $stored = MemberCardReminderConfig::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        return $this->normalizeConfig($stored?->config ?? []);
    }

    public function saveConfig(Staff $staff, Site $site, array $payload): array
    {
        $config = $this->normalizeConfig($payload);

        MemberCardReminderConfig::updateOrCreate(
            [
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
            ],
            ['config' => $config],
        );

        return $config;
    }

    public function expiringQuery(Staff $staff, Site $site, ?int $withinDays = null): Builder
    {
        $days = $withinDays ?? $this->configForSite($staff, $site)['expiringWithinDays'];
        $until = Carbon::today()->addDays($days);

        return $this->activeReminderCardsQuery($staff, $site)
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', Carbon::today())
            ->where('valid_until', '<=', $until)
            ->reorder()
            ->orderBy('valid_until')
            ->orderBy('id');
    }

    public function zeroBalanceQuery(Staff $staff, Site $site): Builder
    {
        $threshold = $this->configForSite($staff, $site)['zeroBalanceThreshold'];

        return $this->activeReminderCardsQuery($staff, $site)
            ->where(function (Builder $query) use ($threshold) {
                $query->where(function (Builder $storedValue) use ($threshold) {
                    $storedValue->where('card_type', CardType::StoredValue)
                        ->where(function (Builder $balance) use ($threshold) {
                            $balance->where('cached_balance', '<=', $threshold)
                                ->orWhereNull('cached_balance');
                        });
                })->orWhere(function (Builder $count) {
                    $count->where('card_type', CardType::Count)
                        ->whereNotNull('cached_remaining_count')
                        ->where('cached_remaining_count', '<=', 0);
                });
            });
    }

    public function pendingOpenQuery(Staff $staff, Site $site): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardStatus::PendingActivation)
            ->whereNull('archived_at')
            ->orderByDesc('issued_at');
    }

    public function penalizedQuery(Staff $staff, Site $site): Builder
    {
        $penaltyCardIds = MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereHas('ledgerEntries', fn (Builder $query) => $query
                ->where('entry_type', EntitlementLedgerEntryType::Penalty))
            ->pluck('id');

        return $this->activeReminderCardsQuery($staff, $site)
            ->where(function (Builder $query) use ($penaltyCardIds) {
                $query->where('status', MemberCardStatus::Frozen);
                if ($penaltyCardIds->isNotEmpty()) {
                    $query->orWhereIn('id', $penaltyCardIds);
                }
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reminderItems(Collection $cards, Staff $viewer, Site $site): array
    {
        $canReadMemberNames = $viewer->hasPermission('crm.member.read', $site->id);

        return $cards->map(fn (MemberCard $card) => $this->reminderItem($card, $canReadMemberNames))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function reminderItem(MemberCard $card, bool $canReadMemberNames): array
    {
        $card->loadMissing('member.crmProfile');
        $rawName = $card->member?->crmProfile?->name;

        return [
            'memberCardId' => $card->id,
            'memberId' => $card->member_id,
            'memberName' => $canReadMemberNames
                ? $rawName
                : $this->maskName($rawName),
            'cardNo' => $card->card_no,
            'cardType' => $card->card_type->value,
            'status' => $card->status->value,
            'name' => $card->product_snapshot['name'] ?? null,
            'cachedBalance' => $card->cached_balance !== null
                ? number_format((float) $card->cached_balance, 2, '.', '')
                : null,
            'cachedRemainingCount' => $card->cached_remaining_count,
            'validFrom' => $card->valid_from?->toDateString(),
            'validUntil' => $card->valid_until?->toDateString(),
            'issuedAt' => $card->issued_at?->toIso8601String(),
        ];
    }

    private function activeReminderCardsQuery(Staff $staff, Site $site): Builder
    {
        return MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereIn('status', [MemberCardStatus::Active, MemberCardStatus::Frozen, MemberCardStatus::PendingActivation])
            ->where('member_visibility', MemberCardVisibility::Visible)
            ->whereNull('archived_at')
            ->orderByDesc('issued_at');
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{expiringWithinDays: int, zeroBalanceThreshold: string}
     */
    private function normalizeConfig(array $config): array
    {
        $days = (int) ($config['expiringWithinDays'] ?? self::DEFAULT_EXPIRING_WITHIN_DAYS);
        $threshold = (float) ($config['zeroBalanceThreshold'] ?? self::DEFAULT_ZERO_BALANCE_THRESHOLD);

        return [
            'expiringWithinDays' => max(1, $days),
            'zeroBalanceThreshold' => number_format(max(0, $threshold), 2, '.', ''),
        ];
    }
}
