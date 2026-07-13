<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\Staff;

class NotificationChannelConfigService
{
    /**
     * @var list<array{legacyId: int, key: string, label: string, group: string}>
     */
    private const CHANNEL_DEFINITIONS = [
        ['legacyId' => 1, 'key' => 'member_appointment_success', 'label' => '会员预约成功', 'group' => 'admin'],
        ['legacyId' => 2, 'key' => 'private_appointment_reminder', 'label' => '私教课前提醒（会员）', 'group' => 'private'],
        ['legacyId' => 4, 'key' => 'private_appointment_cancel', 'label' => '私教取消提醒（会员）', 'group' => 'private'],
        ['legacyId' => 5, 'key' => 'group_appointment_reminder', 'label' => '团课课前提醒（会员）', 'group' => 'group'],
        ['legacyId' => 7, 'key' => 'group_appointment_waitlist', 'label' => '团课候补提醒', 'group' => 'group'],
        ['legacyId' => 8, 'key' => 'group_appointment_cancel', 'label' => '团课取消提醒', 'group' => 'group'],
        ['legacyId' => 9, 'key' => 'group_appointment_success', 'label' => '团课预约成功', 'group' => 'group'],
        ['legacyId' => 12, 'key' => 'staff_appointment_reminder', 'label' => '课前提醒（教练）', 'group' => 'admin'],
        ['legacyId' => 14, 'key' => 'staff_appointment_cancel', 'label' => '取消提醒（教练）', 'group' => 'admin'],
        ['legacyId' => 15, 'key' => 'member_card_expire', 'label' => '会员卡到期提醒', 'group' => 'admin'],
        ['legacyId' => 18, 'key' => 'member_card_low_balance', 'label' => '会员卡余额不足', 'group' => 'admin'],
        ['legacyId' => 19, 'key' => 'member_birthday', 'label' => '会员生日提醒', 'group' => 'admin'],
    ];

    /**
     * @return array{
     *     channels: list<array<string, mixed>>,
     *     managerRecipients: list<array<string, mixed>>
     * }
     */
    public function config(Site $site): array
    {
        $stored = $site->notification_channel_config ?? [];
        $channelStates = is_array($stored['channels'] ?? null) ? $stored['channels'] : [];
        $managerStaffIds = collect($stored['managerStaffIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $managers = Staff::query()
            ->where('tenant_id', $site->tenant_id)
            ->whereIn('id', $managerStaffIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        return [
            'channels' => collect(self::CHANNEL_DEFINITIONS)
                ->map(fn (array $definition) => [
                    'legacyId' => $definition['legacyId'],
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'group' => $definition['group'],
                    'enabled' => (bool) ($channelStates[$definition['key']] ?? true),
                ])
                ->values()
                ->all(),
            'managerRecipients' => collect($managerStaffIds)
                ->map(function (int $staffId) use ($managers) {
                    $staff = $managers->get($staffId);
                    if (! $staff) {
                        return null;
                    }

                    return [
                        'id' => $staff->id,
                        'displayName' => $staff->name,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     channels?: list<array{key: string, enabled: bool}>,
     *     managerStaffIds?: list<int>
     * }  $payload
     * @return array{
     *     channels: list<array<string, mixed>>,
     *     managerRecipients: list<array<string, mixed>>
     * }
     */
    public function update(Site $site, array $payload): array
    {
        $current = $site->notification_channel_config ?? [];
        $channelStates = is_array($current['channels'] ?? null) ? $current['channels'] : [];
        $allowedKeys = collect(self::CHANNEL_DEFINITIONS)->pluck('key')->all();

        foreach ($payload['channels'] ?? [] as $channel) {
            abort_unless(in_array($channel['key'], $allowedKeys, true), 422, 'VALIDATION_FAILED');
            $channelStates[$channel['key']] = (bool) $channel['enabled'];
        }

        $managerStaffIds = array_key_exists('managerStaffIds', $payload)
            ? collect($payload['managerStaffIds'])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : ($current['managerStaffIds'] ?? []);

        if ($managerStaffIds !== []) {
            $validCount = Staff::query()
                ->where('tenant_id', $site->tenant_id)
                ->whereIn('id', $managerStaffIds)
                ->where('status', 'active')
                ->whereHas('sites', fn ($query) => $query->whereKey($site->id))
                ->count();
            abort_if($validCount !== count($managerStaffIds), 422, 'VALIDATION_FAILED');
        }

        $site->update([
            'notification_channel_config' => [
                'channels' => $channelStates,
                'managerStaffIds' => $managerStaffIds,
            ],
        ]);

        return $this->config($site->fresh());
    }
}
