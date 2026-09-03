<?php

namespace App\Services\Payroll;

use App\Enums\PayrollSalesMode;
use App\Models\PayrollSalesConfig;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

class PayrollSalesConfigService
{
    public function read(Staff $actor, Site $site): array
    {
        $stored = PayrollSalesConfig::query()
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
                'mode' => ['启用会籍提成时必须选择计算方式'],
            ]);
        }

        if ($normalized['enabled'] && $normalized['mode'] === PayrollSalesMode::Tiered->value) {
            $this->assertNoTierOverlap($normalized['settings']['newSaleTiers']);
            $this->assertNoTierOverlap($normalized['settings']['renewalTiers']);
        }

        PayrollSalesConfig::updateOrCreate(
            [
                'tenant_id' => $actor->tenant_id,
                'site_id' => $site->id,
            ],
            [
                'enabled' => $normalized['enabled'],
                'mode' => $normalized['mode'],
                'settings' => $normalized['settings'],
            ],
        );

        return $normalized;
    }

    /**
     * @param  PayrollSalesConfig|array<string, mixed>|null  $source
     */
    private function normalize(PayrollSalesConfig|array|null $source): array
    {
        if ($source instanceof PayrollSalesConfig) {
            return [
                'enabled' => $source->enabled,
                'mode' => $source->mode?->value,
                'settings' => $this->normalizeSettings($source->settings ?? [], $source->mode?->value),
            ];
        }

        $payload = is_array($source) ? $source : [];
        $enabled = (bool) ($payload['enabled'] ?? false);
        $mode = $payload['mode'] ?? null;

        if ($mode !== null && ! in_array($mode, array_column(PayrollSalesMode::cases(), 'value'), true)) {
            throw ValidationException::withMessages([
                'mode' => ['会籍提成计算方式无效'],
            ]);
        }

        if (! $enabled) {
            $mode = null;
        }

        $settings = $this->normalizeSettings($payload['settings'] ?? [], $mode);

        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'settings' => $settings,
        ];
    }

    /**
     * @return array{
     *     newSaleRatePercent: int|null,
     *     renewalRatePercent: int|null,
     *     newSaleTiers: list<array{fromAmountCents: int, toAmountCents: int|null, ratePercent: int}>,
     *     renewalTiers: list<array{fromAmountCents: int, toAmountCents: int|null, ratePercent: int}>
     * }
     */
    private function normalizeSettings(array $settings, ?string $mode): array
    {
        $defaults = [
            'newSaleRatePercent' => null,
            'renewalRatePercent' => null,
            'newSaleTiers' => [],
            'renewalTiers' => [],
        ];

        if ($mode === PayrollSalesMode::FlatRate->value) {
            return [
                'newSaleRatePercent' => $this->nullablePercent($settings['newSaleRatePercent'] ?? null),
                'renewalRatePercent' => $this->nullablePercent($settings['renewalRatePercent'] ?? null),
                'newSaleTiers' => [],
                'renewalTiers' => [],
            ];
        }

        if ($mode === PayrollSalesMode::Tiered->value) {
            return [
                'newSaleRatePercent' => null,
                'renewalRatePercent' => null,
                'newSaleTiers' => $this->normalizeTiers($settings['newSaleTiers'] ?? []),
                'renewalTiers' => $this->normalizeTiers($settings['renewalTiers'] ?? []),
            ];
        }

        return $defaults;
    }

    private function nullablePercent(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return min(100, max(0, (int) $value));
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     * @return list<array{fromAmountCents: int, toAmountCents: int|null, ratePercent: int}>
     */
    private function normalizeTiers(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $tier) {
            $from = max(0, (int) ($tier['fromAmountCents'] ?? 0));
            $to = array_key_exists('toAmountCents', $tier) && $tier['toAmountCents'] !== null
                ? max($from, (int) $tier['toAmountCents'])
                : null;
            $rate = min(100, max(0, (int) ($tier['ratePercent'] ?? 0)));

            $normalized[] = [
                'fromAmountCents' => $from,
                'toAmountCents' => $to,
                'ratePercent' => $rate,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{fromAmountCents: int, toAmountCents: int|null, ratePercent: int}>  $tiers
     */
    private function assertNoTierOverlap(array $tiers): void
    {
        if (count($tiers) < 2) {
            return;
        }

        $sorted = $tiers;
        usort($sorted, fn (array $a, array $b): int => $a['fromAmountCents'] <=> $b['fromAmountCents']);

        for ($index = 1; $index < count($sorted); $index++) {
            $previous = $sorted[$index - 1];
            $tier = $sorted[$index];
            $previousEnd = $previous['toAmountCents'] ?? PHP_INT_MAX;

            if ($tier['fromAmountCents'] <= $previousEnd) {
                abort(422, 'PAYROLL_TIER_OVERLAP');
            }
        }
    }
}
