<?php

namespace App\Services\Tenant;

use App\Models\Site;

class TenantLegalConfigService
{
    /**
     * @return array{html: string}
     */
    public function membershipAgreement(Site $site): array
    {
        $site->loadMissing('tenant');

        return [
            'html' => (string) ($site->tenant?->membership_agreement_html ?? ''),
        ];
    }

    /**
     * @param  array{html: string}  $payload
     * @return array{html: string}
     */
    public function updateMembershipAgreement(Site $site, array $payload): array
    {
        $tenant = $site->tenant;
        abort_unless($tenant, 404);

        $tenant->update([
            'membership_agreement_html' => $payload['html'],
        ]);

        return $this->membershipAgreement($site->fresh()->loadMissing('tenant'));
    }
}
