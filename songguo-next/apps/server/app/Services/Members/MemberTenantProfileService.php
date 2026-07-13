<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemberTenantProfileService
{
    public function __construct(
        private readonly MemberRegistrationService $registration,
        private readonly MemberProfileFieldPolicy $fieldPolicy,
        private readonly MemberSelfAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function read(Account $account, Member $member): array
    {
        $member->loadMissing('tenant');
        $registration = $this->registration->status($account);
        $profile = $registration['profile'];

        return [
            'tenantId' => $member->tenant_id,
            'registration' => [
                'state' => $registration['state'],
                'registrationRequired' => $registration['registrationRequired'],
                'missingFields' => $registration['missingFields'],
                'legalConfigurationReady' => $registration['legalConfigurationReady'],
                'acceptedDocumentIds' => $registration['acceptedDocumentIds'],
            ],
            'fieldPolicy' => [
                'fields' => $this->fieldPolicy->userFields($member->tenant),
            ],
            'profile' => $profile ? [
                ...$profile,
                'avatarUrl' => $this->avatarUrl($profile['avatarObjectKey'] ?? null),
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(Request $request, Account $account, Member $member, array $payload): array
    {
        $submittedKeys = collect($payload)
            ->except(['version'])
            ->keys()
            ->values()
            ->all();
        $this->fieldPolicy->assertEditable($submittedKeys);

        DB::transaction(function () use ($request, $account, $member, $payload, $submittedKeys) {
            $attributes = $this->mapAttributes($payload);
            $profile = MemberProfile::where('account_id', $account->id)->first();
            if ($profile) {
                abort_unless(array_key_exists('version', $payload), 422, 'PROFILE_VERSION_REQUIRED');
                $updated = MemberProfile::query()
                    ->whereKey($profile->id)
                    ->where('version', (int) $payload['version'])
                    ->update([...$attributes, 'version' => DB::raw('version + 1')]);
                abort_if($updated !== 1, 409, 'PROFILE_VERSION_CONFLICT');
            } else {
                MemberProfile::create([...$attributes, 'account_id' => $account->id]);
            }

            if (array_key_exists('display_name', $attributes)) {
                $account->update(['display_name' => $attributes['display_name']]);
            }

            $this->syncCrmProfile($member, $attributes);
            $this->audit->record($request, $member, 'member.profile.updated', ['fields' => $submittedKeys]);
        });

        $account->unsetRelation('memberProfile');

        return $this->read($account->fresh(), $member->fresh(['tenant', 'crmProfile']));
    }

    /**
     * @return array{avatarObjectKey: string, avatarUrl: string|null, version: int}
     */
    public function storeAvatar(Request $request, Account $account, Member $member, string $storedPath): array
    {
        $objectKey = $storedPath;

        DB::transaction(function () use ($request, $account, $member, $objectKey) {
            $profile = MemberProfile::where('account_id', $account->id)->first();
            if ($profile) {
                abort_unless($request->filled('version'), 422, 'PROFILE_VERSION_REQUIRED');
                $updated = MemberProfile::query()
                    ->whereKey($profile->id)
                    ->where('version', $request->integer('version'))
                    ->update([
                        'avatar_object_key' => $objectKey,
                        'version' => DB::raw('version + 1'),
                    ]);
                abort_if($updated !== 1, 409, 'PROFILE_VERSION_CONFLICT');
            } else {
                MemberProfile::create([
                    'account_id' => $account->id,
                    'avatar_object_key' => $objectKey,
                ]);
            }

            $this->audit->record($request, $member, 'member.profile.avatar_updated', ['avatarObjectKey' => $objectKey]);
        });

        $profile = MemberProfile::where('account_id', $account->id)->firstOrFail();

        return [
            'avatarObjectKey' => $objectKey,
            'avatarUrl' => $this->avatarUrl($objectKey),
            'version' => $profile->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapAttributes(array $payload): array
    {
        $attributes = [];
        if (array_key_exists('displayName', $payload)) {
            $attributes['display_name'] = $payload['displayName'];
        }
        if (array_key_exists('avatarObjectKey', $payload)) {
            $attributes['avatar_object_key'] = $payload['avatarObjectKey'];
        }
        if (array_key_exists('gender', $payload)) {
            $attributes['gender'] = $payload['gender'];
        }
        if (array_key_exists('birthDate', $payload)) {
            $attributes['birth_date'] = $payload['birthDate'];
        }
        if (array_key_exists('heightCm', $payload)) {
            $attributes['height_cm'] = $payload['heightCm'];
        }
        if (array_key_exists('weightKg', $payload)) {
            $attributes['weight_kg'] = $payload['weightKg'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncCrmProfile(Member $member, array $attributes): void
    {
        $crmUpdates = [];
        if (array_key_exists('display_name', $attributes)) {
            $crmUpdates['name'] = $attributes['display_name'];
        }
        if (array_key_exists('gender', $attributes)) {
            $crmUpdates['gender'] = $attributes['gender'];
        }
        if (array_key_exists('birth_date', $attributes)) {
            $crmUpdates['birth_date'] = $attributes['birth_date'];
        }
        if ($crmUpdates === []) {
            return;
        }

        $crmProfile = MemberCrmProfile::firstOrCreate(
            ['member_id' => $member->id],
            [
                'tenant_id' => $member->tenant_id,
                'name' => $crmUpdates['name'] ?? "会员{$member->id}",
            ],
        );
        $crmProfile->update([...$crmUpdates, 'version' => $crmProfile->version + 1]);
    }

    private function avatarUrl(?string $objectKey): ?string
    {
        if (! $objectKey) {
            return null;
        }
        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return $objectKey;
        }

        return Storage::disk('public')->url($objectKey);
    }
}
