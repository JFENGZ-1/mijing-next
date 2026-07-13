<?php

namespace App\Services\Points;

use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class PointsConfigService
{
    private const DEFAULT_POLICY = [
        'earnPerVisit' => 1,
        'earnPerPurchase' => 0,
        'debitEnabled' => true,
        'descriptionText' => '',
    ];

    /**
     * @return array<string, mixed>
     */
    public function get(Staff $staff, Site $site): array
    {
        $tenant = Tenant::query()->findOrFail($staff->tenant_id);
        $policy = $this->normalize($tenant->points_policy ?? []);

        return [
            'tenantId' => $tenant->id,
            'siteId' => $site->id,
            'pointsEnabled' => (bool) $tenant->points_enabled,
            'policy' => $policy,
            'descriptionText' => $tenant->points_description_text,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(Staff $staff, Site $site, array $payload): array
    {
        DB::transaction(function () use ($staff, $payload) {
            $tenant = Tenant::query()->whereKey($staff->tenant_id)->lockForUpdate()->firstOrFail();
            $updates = [];

            if (array_key_exists('pointsEnabled', $payload)) {
                $updates['points_enabled'] = (bool) $payload['pointsEnabled'];
            }
            if (array_key_exists('descriptionText', $payload)) {
                $updates['points_description_text'] = $payload['descriptionText'];
            }
            if (array_key_exists('policy', $payload)) {
                $updates['points_policy'] = $this->normalize($payload['policy']);
            }

            if ($updates !== []) {
                $tenant->update($updates);
            }
        });

        return $this->get($staff, $site);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        return [
            'earnPerVisit' => (int) ($raw['earnPerVisit'] ?? self::DEFAULT_POLICY['earnPerVisit']),
            'earnPerPurchase' => (int) ($raw['earnPerPurchase'] ?? self::DEFAULT_POLICY['earnPerPurchase']),
            'debitEnabled' => (bool) ($raw['debitEnabled'] ?? self::DEFAULT_POLICY['debitEnabled']),
            'descriptionText' => (string) ($raw['descriptionText'] ?? self::DEFAULT_POLICY['descriptionText']),
        ];
    }
}
