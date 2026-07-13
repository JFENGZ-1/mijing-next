<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberProfile;

class MemberPurchaseGateService
{
    public function __construct(
        private readonly MemberRegistrationService $registration,
        private readonly MemberProfileFieldPolicy $fieldPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Account $account, Member $member): array
    {
        $member->loadMissing('tenant');
        $tenant = $member->tenant;
        $registration = $this->registration->status($account);
        $profile = $account->memberProfile;

        $missingByKey = [];
        foreach ($registration['missingFields'] as $key) {
            $missingByKey[$key] = $this->registrationMissingField($key);
        }

        foreach ($this->fieldPolicy->missingRequiredFields($tenant, $profile) as $field) {
            $missingByKey[$field['key']] = $field;
        }

        $missingFields = array_values($missingByKey);
        $allowed = $missingFields === [];

        return [
            'allowed' => $allowed,
            'missingFields' => $missingFields,
            'redirectHints' => [
                'profile' => "/member/profile?tenantId={$tenant->id}",
                'cardCatalog' => "/member/card-products?tenantId={$tenant->id}",
            ],
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    private function registrationMissingField(string $key): array
    {
        return match ($key) {
            'displayName' => ['key' => 'displayName', 'label' => '姓名'],
            'privacyConsent' => ['key' => 'privacyConsent', 'label' => '隐私协议'],
            default => ['key' => $key, 'label' => $key],
        };
    }
}
