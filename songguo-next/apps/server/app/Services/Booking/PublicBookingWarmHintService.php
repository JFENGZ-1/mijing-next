<?php

namespace App\Services\Booking;

use App\Models\Site;
use App\Services\Tenant\MemberExperienceConfigService;

class PublicBookingWarmHintService
{
    public function __construct(
        private readonly MemberExperienceConfigService $memberExperience,
    ) {}

    /**
     * @return array{hints: list<array<string, mixed>>}
     */
    public function forSite(Site $site, ?int $courseType = null): array
    {
        $warmHints = $this->memberExperience->warmHints($site);
        $hints = $warmHints['hints'];

        if ($courseType !== null) {
            $hints = array_values(array_filter(
                $hints,
                fn (array $hint) => (int) ($hint['courseType'] ?? 0) === $courseType,
            ));
        }

        return ['hints' => $hints];
    }
}
