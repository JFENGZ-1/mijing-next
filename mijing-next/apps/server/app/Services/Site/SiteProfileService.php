<?php

namespace App\Services\Site;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SiteProfileService
{
    public function read(Site $site): array
    {
        return $this->serialize($site);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Site $site, array $payload): array
    {
        if (array_key_exists('version', $payload) && (int) $payload['version'] !== $site->version) {
            abort(409, 'VERSION_CONFLICT');
        }

        $attributes = [];

        foreach ([
            'name' => 'name',
            'phone' => 'phone',
            'address' => 'address',
            'logoUrl' => 'logo_url',
            'description' => 'description',
            'region' => 'region',
            'businessHours' => 'business_hours',
            'longitude' => 'longitude',
            'latitude' => 'latitude',
            'timezone' => 'timezone',
        ] as $input => $column) {
            if (array_key_exists($input, $payload)) {
                $attributes[$column] = $payload[$input];
            }
        }

        if ($attributes === []) {
            return $this->serialize($site);
        }

        $updated = Site::query()
            ->whereKey($site->id)
            ->where('tenant_id', $site->tenant_id)
            ->where('version', $payload['version'] ?? $site->version)
            ->update([...$attributes, 'version' => DB::raw('version + 1')]);

        abort_if($updated !== 1, 409, 'VERSION_CONFLICT');

        return $this->serialize($site->fresh());
    }

    private function serialize(Site $site): array
    {
        $logoUrl = $site->logo_url;
        $logoUrlWeb = $logoUrl && ! str_starts_with($logoUrl, 'http')
            ? Storage::disk('public')->url($logoUrl)
            : $logoUrl;

        return [
            'id' => $site->id,
            'name' => $site->name,
            'phone' => $site->phone,
            'address' => $site->address,
            'logoUrl' => $logoUrl,
            'logoUrlWeb' => $logoUrlWeb,
            'description' => $site->description,
            'region' => $site->region,
            'businessHours' => $site->business_hours ?? [],
            'longitude' => $site->longitude,
            'latitude' => $site->latitude,
            'timezone' => $site->timezone,
            'version' => $site->version,
        ];
    }
}
