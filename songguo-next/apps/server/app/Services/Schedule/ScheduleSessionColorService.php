<?php

namespace App\Services\Schedule;

use App\Models\BookingPolicy;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Support\Facades\DB;

class ScheduleSessionColorService
{
    // 对标原版课表背景色板：10 款标记色（仅用于生成课程表图片时区分课程类别）。
    private const DEFAULT_PALETTE = [
        ['key' => 'red', 'label' => '红色', 'color' => '#e54d42'],
        ['key' => 'orange', 'label' => '桔色', 'color' => '#f37b1d'],
        ['key' => 'yellow', 'label' => '黄色', 'color' => '#fbbd08'],
        ['key' => 'olive', 'label' => '橄榄', 'color' => '#8dc63f'],
        ['key' => 'green', 'label' => '绿色', 'color' => '#39b54a'],
        ['key' => 'cyan', 'label' => '青色', 'color' => '#1cbbb4'],
        ['key' => 'blue', 'label' => '蓝色', 'color' => '#0081ff'],
        ['key' => 'purple', 'label' => '紫色', 'color' => '#6739b6'],
        ['key' => 'pink', 'label' => '粉色', 'color' => '#e03997'],
        ['key' => 'grey', 'label' => '灰色', 'color' => '#8799a3'],
    ];

    public function __construct(
        private readonly BookingPolicyService $bookingPolicies,
    ) {}

    /**
     * @return array{palette: list<array{key: string, label: string, color: string}>}
     */
    public function forSite(Staff $staff, Site $site): array
    {
        $stored = BookingPolicy::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        $palette = $stored?->rules['sessionColorPalette'] ?? self::DEFAULT_PALETTE;

        return ['palette' => $this->normalizePalette($palette)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{palette: list<array{key: string, label: string, color: string}>}
     */
    public function save(Staff $staff, Site $site, array $payload): array
    {
        $palette = $this->normalizePalette($payload['palette'] ?? []);

        DB::transaction(function () use ($staff, $site, $palette) {
            $policy = BookingPolicy::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->lockForUpdate()
                ->first();

            if (! $policy) {
                $defaults = BookingPolicyService::defaultPolicy();
                BookingPolicy::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'version' => 1,
                    'policy' => $defaults,
                    'rules' => ['sessionColorPalette' => $palette],
                ]);

                return;
            }

            $rules = $policy->rules ?? [];
            $rules['sessionColorPalette'] = $palette;
            $policy->update([
                'rules' => $rules,
                'version' => $policy->version + 1,
            ]);
        });

        return $this->forSite($staff, $site->fresh());
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{key: string, label: string, color: string}>
     */
    private function normalizePalette(array $raw): array
    {
        if ($raw === []) {
            return self::DEFAULT_PALETTE;
        }

        return collect($raw)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $key = trim((string) ($item['key'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));
                if ($key === '' || $label === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => $label,
                    'color' => trim((string) ($item['color'] ?? '#1677ff')) ?: '#1677ff',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
