<?php

namespace App\Services\Members;

use App\Models\Site;
use App\Services\Tenant\MemberExperienceConfigService;

class MemberSitePublicService
{
    public function __construct(private readonly MemberExperienceConfigService $memberExperience) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Site $site): array
    {
        $carousel = $this->memberExperience->carousel($site);
        $warmHints = $this->memberExperience->warmHints($site);

        return [
            'id' => $site->id,
            'tenantId' => $site->tenant_id,
            'name' => $site->name,
            'phone' => $site->phone,
            'address' => $site->address,
            'description' => $site->description,
            'logoUrl' => $site->logo_url,
            'businessHours' => $site->business_hours,
            'longitude' => $site->longitude,
            'latitude' => $site->latitude,
            'carousel' => $carousel,
            'warmHints' => $warmHints['hints'],
        ];
    }
}
