<?php

namespace Database\Seeders\Support;

use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;

final class DemoSeedTarget
{
    public static function site(): ?Site
    {
        $siteId = config('demo.target_site_id');

        return $siteId
            ? Site::query()->find($siteId)
            : Site::query()->find(1);
    }

    /** @return Collection<int, Site> */
    public static function sites(): Collection
    {
        $siteId = config('demo.target_site_id');

        return Site::query()
            ->where('status', 'active')
            ->when($siteId, fn ($query) => $query->whereKey($siteId))
            ->orderBy('id')
            ->get();
    }
}
