<?php

namespace App\Services\Schedule;

use App\Models\BookingPolicy;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Support\Facades\DB;

class ScheduleSessionColorService
{
    private const DEFAULT_PALETTE = [
        ['key' => 'default', 'label' => '默认', 'color' => '#1677ff'],
        ['key' => 'green', 'label' => '绿色', 'color' => '#52c41a'],
        ['key' => 'orange', 'label' => '橙色', 'color' => '#fa8c16'],
        ['key' => 'purple', 'label' => '紫色', 'color' => '#722ed1'],
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
