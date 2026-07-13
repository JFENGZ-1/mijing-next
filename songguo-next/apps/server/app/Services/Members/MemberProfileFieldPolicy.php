<?php

namespace App\Services\Members;

use App\Models\MemberProfile;
use App\Models\Tenant;

class MemberProfileFieldPolicy
{
    /**
     * @var array<string, array{label: string, memberEditable: bool, defaultRequired: bool}>
     */
    private const FIELDS = [
        'displayName' => ['label' => '姓名', 'memberEditable' => true, 'defaultRequired' => true],
        'gender' => ['label' => '性别', 'memberEditable' => true, 'defaultRequired' => false],
        'birthDate' => ['label' => '生日', 'memberEditable' => true, 'defaultRequired' => false],
        'heightCm' => ['label' => '身高', 'memberEditable' => true, 'defaultRequired' => false],
        'weightKg' => ['label' => '体重', 'memberEditable' => true, 'defaultRequired' => false],
        'avatarObjectKey' => ['label' => '头像', 'memberEditable' => true, 'defaultRequired' => false],
        'mobile' => ['label' => '手机号', 'memberEditable' => false, 'defaultRequired' => false],
        'nationalId' => ['label' => '身份证', 'memberEditable' => false, 'defaultRequired' => false],
    ];

    /**
     * @return list<string>
     */
    public function editableFieldKeys(): array
    {
        return collect(self::FIELDS)
            ->filter(fn (array $field) => $field['memberEditable'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, memberEditable: bool, required: bool}>
     */
    public function userFields(Tenant $tenant): array
    {
        $requiredOverrides = $this->requiredOverrides($tenant);

        return collect(self::FIELDS)
            ->map(fn (array $field, string $key) => [
                'key' => $key,
                'label' => $field['label'],
                'memberEditable' => $field['memberEditable'],
                'required' => $requiredOverrides[$key] ?? $field['defaultRequired'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keys
     */
    public function assertEditable(array $keys): void
    {
        $editable = $this->editableFieldKeys();
        $blocked = array_values(array_diff($keys, $editable));
        abort_if($blocked !== [], 422, 'PROFILE_FIELD_NOT_EDITABLE');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function missingRequiredFields(Tenant $tenant, ?MemberProfile $profile): array
    {
        return collect($this->userFields($tenant))
            ->filter(fn (array $field) => $field['required'] && ! $this->isFieldPresent($field['key'], $profile))
            ->map(fn (array $field) => ['key' => $field['key'], 'label' => $field['label']])
            ->values()
            ->all();
    }

    public function isFieldPresent(string $key, ?MemberProfile $profile): bool
    {
        if (! $profile) {
            return false;
        }

        return match ($key) {
            'displayName' => ! blank($profile->display_name),
            'gender' => filled($profile->gender),
            'birthDate' => $profile->birth_date !== null,
            'heightCm' => $profile->height_cm !== null,
            'weightKg' => $profile->weight_kg !== null,
            'avatarObjectKey' => filled($profile->avatar_object_key),
            'mobile' => $profile->mobile_verified_at !== null,
            'nationalId' => false,
            default => true,
        };
    }

    /**
     * @return array<string, bool>
     */
    private function requiredOverrides(Tenant $tenant): array
    {
        return match ($tenant->code) {
            default => [],
        };
    }
}
