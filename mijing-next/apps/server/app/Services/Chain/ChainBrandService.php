<?php

namespace App\Services\Chain;

use App\Models\Staff;
use App\Models\Tenant;

class ChainBrandService
{
    /**
     * @return array{name: ?string, logoUrl: ?string, chainActivated: bool, siteCount: int}
     */
    public function show(Staff $staff, int $activeSiteCount): array
    {
        $tenant = Tenant::query()->whereKey($staff->tenant_id)->firstOrFail();

        return [
            'name' => $tenant->chain_brand_name,
            'logoUrl' => $tenant->chain_brand_logo_url,
            'chainActivated' => filled($tenant->chain_brand_name) && $activeSiteCount >= 2,
            'siteCount' => $activeSiteCount,
        ];
    }

    /**
     * @param  array{name?: ?string, logoUrl?: ?string}  $payload
     * @return array{name: ?string, logoUrl: ?string, chainActivated: bool, siteCount: int}
     */
    public function upsert(Staff $staff, array $payload, int $activeSiteCount): array
    {
        $tenant = Tenant::query()->whereKey($staff->tenant_id)->firstOrFail();

        if (array_key_exists('name', $payload)) {
            $tenant->chain_brand_name = $payload['name'];
        }
        if (array_key_exists('logoUrl', $payload)) {
            $tenant->chain_brand_logo_url = $payload['logoUrl'];
        }

        $tenant->save();

        return $this->show($staff, $activeSiteCount);
    }
}
