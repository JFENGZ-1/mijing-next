<?php

namespace App\Services\Cards;

use App\Enums\EntitlementLedgerEntryType;
use App\Models\EntitlementLedgerEntry;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class MemberCardExtrasService
{
    public function lastHoliday(Staff $staff, Site $site, MemberCard $card): array
    {
        $entry = EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_card_id', $card->id)
            ->whereIn('entry_type', [
                EntitlementLedgerEntryType::HolidayApply->value,
                EntitlementLedgerEntryType::HolidayCancel->value,
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        return [
            'memberCardId' => $card->id,
            'entry' => $entry ? [
                'id' => $entry->id,
                'entryType' => $entry->entry_type->value,
                'reason' => $entry->reason,
                'occurredAt' => $entry->occurred_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function lastFreezeLedger(Staff $staff, Site $site, MemberCard $card): array
    {
        $entry = EntitlementLedgerEntry::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_card_id', $card->id)
            ->whereIn('entry_type', [
                EntitlementLedgerEntryType::Freeze->value,
                EntitlementLedgerEntryType::FreezeLift->value,
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        return [
            'memberCardId' => $card->id,
            'entry' => $entry ? [
                'id' => $entry->id,
                'entryType' => $entry->entry_type->value,
                'reason' => $entry->reason,
                'occurredAt' => $entry->occurred_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function defaultFee(Staff $staff, Site $site, MemberCard $card): array
    {
        $snapshot = $card->product_snapshot;
        $fee = $snapshot['defaultFee'] ?? $snapshot['faceValue'] ?? null;

        return [
            'memberCardId' => $card->id,
            'defaultFee' => $fee === null ? null : number_format((float) $fee, 2, '.', ''),
            'currency' => 'CNY',
        ];
    }

    public function dynamicFields(Staff $staff, Site $site, MemberCard $card): array
    {
        $tenant = Tenant::query()->findOrFail($staff->tenant_id);
        $policy = $tenant->crm_field_policy ?? [];
        $snapshot = $card->product_snapshot;
        $custom = is_array($snapshot['dynamicFields'] ?? null) ? $snapshot['dynamicFields'] : [];

        return [
            'memberCardId' => $card->id,
            'fields' => collect($policy['fields'] ?? [])
                ->map(function ($field) use ($custom) {
                    if (! is_array($field)) {
                        return null;
                    }

                    $key = (string) ($field['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($field['label'] ?? $key),
                        'value' => $custom[$key] ?? null,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public function setOpeningType(Staff $staff, Site $site, MemberCard $card, array $payload): array
    {
        return DB::transaction(function () use ($card, $payload) {
            $snapshot = $card->product_snapshot;
            $snapshot['openingType'] = $payload['openingType'];
            $card->update(['product_snapshot' => $snapshot]);

            return [
                'memberCardId' => $card->id,
                'openingType' => $payload['openingType'],
            ];
        });
    }

    public function updateRemark(Staff $staff, Site $site, MemberCard $card, array $payload): array
    {
        return DB::transaction(function () use ($card, $payload) {
            $snapshot = $card->product_snapshot;
            $snapshot['staffRemark'] = $payload['remark'];
            $card->update(['product_snapshot' => $snapshot]);

            return [
                'memberCardId' => $card->id,
                'remark' => $payload['remark'],
            ];
        });
    }
}
