<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\LegalConsent;
use App\Models\LegalDocument;

class MemberRegistrationService
{
    public function status(Account $account): array
    {
        $profile = $account->memberProfile;
        $requiredDocuments = LegalDocument::query()
            ->where('scope_key', 'global')
            ->where('status', 'published')
            ->where('is_required', true)
            ->orderBy('type')
            ->get();
        $acceptedIds = LegalConsent::query()
            ->where('account_id', $account->id)
            ->where('action', 'accepted')
            ->pluck('legal_document_id');

        $missingFields = [];
        if (! $profile || blank($profile->display_name)) {
            $missingFields[] = 'displayName';
        }
        $legalConfigurationReady = $requiredDocuments->isNotEmpty();
        if (! $legalConfigurationReady || $requiredDocuments->pluck('id')->diff($acceptedIds)->isNotEmpty()) {
            $missingFields[] = 'privacyConsent';
        }

        $state = match (true) {
            ! $legalConfigurationReady => 'configuration_required',
            in_array('displayName', $missingFields, true) => 'profile_required',
            in_array('privacyConsent', $missingFields, true) => 'consent_required',
            default => 'complete',
        };

        return [
            'state' => $state,
            'registrationRequired' => $state !== 'complete',
            'missingFields' => $missingFields,
            'legalConfigurationReady' => $legalConfigurationReady,
            'acceptedDocumentIds' => $acceptedIds->values(),
            'profile' => $profile ? [
                'displayName' => $profile->display_name,
                'avatarObjectKey' => $profile->avatar_object_key,
                'mobileMasked' => $profile->mobile_last4 ? "*******{$profile->mobile_last4}" : null,
                'mobileVerified' => (bool) $profile->mobile_verified_at,
                'mobileVerificationMethod' => $profile->mobile_verification_method,
                'gender' => $profile->gender,
                'birthDate' => $profile->birth_date?->format('Y-m-d'),
                'heightCm' => $profile->height_cm,
                'weightKg' => $profile->weight_kg,
                'version' => $profile->version,
            ] : null,
        ];
    }
}
