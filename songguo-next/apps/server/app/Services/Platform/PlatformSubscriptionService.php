<?php

namespace App\Services\Platform;

use App\Models\PlatformServiceAgreement;
use App\Models\PlatformSubscriptionPlan;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;

class PlatformSubscriptionService
{
    public function pricing(): array
    {
        $plans = PlatformSubscriptionPlan::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'list' => $plans->map(fn (PlatformSubscriptionPlan $plan) => $this->formatPlan($plan))->values()->all(),
        ];
    }

    public function agreement(): array
    {
        $agreement = PlatformServiceAgreement::query()
            ->where('status', 'current')
            ->orderByDesc('effective_at')
            ->firstOrFail();

        return [
            'version' => $agreement->version,
            'title' => $agreement->title,
            'html' => $agreement->html,
            'effectiveAt' => $agreement->effective_at?->toIso8601String(),
            'support' => [
                'customServicer' => $agreement->support_contact_enabled,
                'servicerNickName' => $agreement->support_contact_name,
                'protocolUrl' => $agreement->support_protocol_url,
            ],
        ];
    }

    public function siteStatus(Site $site): array
    {
        $tenant = Tenant::query()->findOrFail($site->tenant_id);
        $agreement = PlatformServiceAgreement::query()
            ->where('status', 'current')
            ->orderByDesc('effective_at')
            ->first();

        $expiresAt = $tenant->subscription_expires_at;
        $daysRemaining = $expiresAt !== null
            ? (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)
            : null;

        $planLabel = null;
        if ($tenant->subscription_plan !== null) {
            $planLabel = PlatformSubscriptionPlan::query()
                ->where('code', $tenant->subscription_plan)
                ->value('label');
        }

        return [
            'siteId' => $site->id,
            'siteName' => $site->name,
            'subscription' => [
                'planCode' => $tenant->subscription_plan,
                'planLabel' => $planLabel,
                'status' => $tenant->subscription_status?->value ?? 'active',
                'expiresAt' => $expiresAt?->toIso8601String(),
                'daysRemaining' => $daysRemaining,
                'softwareExpire' => [
                    'daynum' => $daysRemaining,
                    'expiresAt' => $expiresAt?->toIso8601String(),
                ],
            ],
            'support' => [
                'customServicer' => (bool) ($agreement?->support_contact_enabled),
                'servicerNickName' => $agreement?->support_contact_name,
                'protocolUrl' => $agreement?->support_protocol_url,
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function orders(Staff $staff): array
    {
        $sites = Site::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $items = $sites->map(function (Site $site) {
            $status = $this->siteStatus($site);

            return [
                'orderId' => null,
                'siteId' => $site->id,
                'siteName' => $site->name,
                'planCode' => $status['subscription']['planCode'],
                'planLabel' => $status['subscription']['planLabel'],
                'status' => $status['subscription']['status'],
                'expiresAt' => $status['subscription']['expiresAt'],
                'daysRemaining' => $status['subscription']['daysRemaining'],
            ];
        })->all();

        return ['items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function pay(Staff $staff, array $payload): array
    {
        $plan = PlatformSubscriptionPlan::query()
            ->where('status', 'active')
            ->whereKey((int) $payload['planId'])
            ->firstOrFail();

        return [
            'commandKey' => $payload['commandKey'],
            'planId' => $plan->id,
            'planCode' => $plan->code,
            'amount' => $this->formatPrice($plan->price_cents),
            'currency' => $plan->currency,
            'paymentStatus' => 'demo_paid',
            'paidAt' => now()->toIso8601String(),
            'demo' => true,
        ];
    }

    private function formatPlan(PlatformSubscriptionPlan $plan): array
    {
        return [
            'configId' => $plan->id,
            'yearName' => $plan->label,
            'realPrice' => $this->formatPrice($plan->price_cents),
            'originalPrice' => $this->formatPrice($plan->original_price_cents),
            'durationDays' => $plan->duration_days,
            'priceCents' => $plan->price_cents,
            'originalPriceCents' => $plan->original_price_cents,
            'currency' => $plan->currency,
            'code' => $plan->code,
        ];
    }

    private function formatPrice(int $cents): string
    {
        if ($cents % 100 === 0) {
            return (string) intdiv($cents, 100);
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
