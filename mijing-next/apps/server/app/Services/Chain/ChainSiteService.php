<?php

namespace App\Services\Chain;

use App\Models\Site;
use App\Models\Staff;

class ChainSiteService
{
    public function __construct(
        private readonly ChainAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function list(Staff $staff): array
    {
        $accessibleIds = $staff->sites()
            ->where('sites.tenant_id', $staff->tenant_id)
            ->pluck('sites.id');

        $sites = $this->access->tenantSites($staff);
        $brand = app(ChainBrandService::class)->show($staff, $sites->count());

        return [
            'brand' => $brand,
            'sites' => $sites
                ->map(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'code' => $site->code,
                    'status' => $site->status,
                    'address' => $site->address,
                    'phone' => $site->phone,
                    'accessible' => $accessibleIds->contains($site->id),
                ])
                ->values()
                ->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }
}
