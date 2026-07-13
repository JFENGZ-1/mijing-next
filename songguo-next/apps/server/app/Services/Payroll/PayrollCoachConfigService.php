<?php

namespace App\Services\Payroll;

use App\Enums\PayrollCoachMode;
use App\Models\PayrollCoachConfig;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

class PayrollCoachConfigService
{
    public function read(Staff $actor, Site $site): array
    {
        $stored = PayrollCoachConfig::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        return $this->normalize($stored);
    }

    public function save(Staff $actor, Site $site, array $payload): array
    {
        $normalized = $this->normalize($payload);

        if ($normalized['enabled'] && $normalized['mode'] === null) {
            throw ValidationException::withMessages([
                'mode' => ['启用教练工资时必须选择计算方式'],
            ]);
        }

        PayrollCoachConfig::updateOrCreate(
            [
                'tenant_id' => $actor->tenant_id,
                'site_id' => $site->id,
            ],
            [
                'enabled' => $normalized['enabled'],
                'mode' => $normalized['mode'],
            ],
        );

        return $normalized;
    }

    /**
     * @param  PayrollCoachConfig|array<string, mixed>|null  $source
     */
    private function normalize(PayrollCoachConfig|array|null $source): array
    {
        if ($source instanceof PayrollCoachConfig) {
            return [
                'enabled' => $source->enabled,
                'mode' => $source->mode?->value,
            ];
        }

        $payload = is_array($source) ? $source : [];

        $enabled = (bool) ($payload['enabled'] ?? false);
        $mode = $payload['mode'] ?? null;

        if ($mode !== null && ! in_array($mode, array_column(PayrollCoachMode::cases(), 'value'), true)) {
            throw ValidationException::withMessages([
                'mode' => ['教练工资计算方式无效'],
            ]);
        }

        if (! $enabled) {
            $mode = null;
        }

        return [
            'enabled' => $enabled,
            'mode' => $mode,
        ];
    }
}
