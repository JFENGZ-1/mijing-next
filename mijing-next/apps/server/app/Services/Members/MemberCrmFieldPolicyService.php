<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Tenant;
use Illuminate\Http\Request;

class MemberCrmFieldPolicyService
{
    /**
     * Legacy `userField[n]` order from 管理端/pageMember/information/index.js.
     *
     * @var array<string, array{label: string, legacyIndex: int, defaultVisible: bool, defaultRequired: bool, defaultStaffEditable: bool}>
     */
    private const FIELDS = [
        'mobile' => ['label' => '手机号', 'legacyIndex' => 0, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'name' => ['label' => '姓名', 'legacyIndex' => 1, 'defaultVisible' => true, 'defaultRequired' => true, 'defaultStaffEditable' => true],
        'gender' => ['label' => '性别', 'legacyIndex' => 2, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'birthDate' => ['label' => '生日', 'legacyIndex' => 3, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'nationalId' => ['label' => '身份证', 'legacyIndex' => 4, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'heightCm' => ['label' => '身高', 'legacyIndex' => 5, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'weightKg' => ['label' => '体重', 'legacyIndex' => 6, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
        'ownerStaffId' => ['label' => '会籍顾问', 'legacyIndex' => 7, 'defaultVisible' => true, 'defaultRequired' => false, 'defaultStaffEditable' => true],
    ];

    /**
     * @return list<array{key: string, label: string, legacyIndex: int, isRequired: bool, isVisible: bool, staffEditable: bool}>
     */
    public function fields(Tenant $tenant): array
    {
        $overrides = $this->fieldOverrides($tenant);

        return collect(self::FIELDS)
            ->map(fn (array $field, string $key) => [
                'key' => $key,
                'label' => $field['label'],
                'legacyIndex' => $field['legacyIndex'],
                'isRequired' => $overrides[$key]['isRequired'] ?? $field['defaultRequired'],
                'isVisible' => $overrides[$key]['isVisible'] ?? $field['defaultVisible'],
                'staffEditable' => $overrides[$key]['staffEditable'] ?? $field['defaultStaffEditable'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertUpsertAllowed(Tenant $tenant, array $payload, bool $isCreate, ?Member $member = null): void
    {
        $fields = collect($this->fields($tenant))->keyBy('key');
        $requestKeys = $this->requestKeysFromPayload($payload);

        foreach ($requestKeys as $fieldKey) {
            $policy = $fields->get($fieldKey);
            abort_if($policy === null, 422, 'CRM_FIELD_NOT_EDITABLE');
            abort_if(! $policy['staffEditable'], 422, 'CRM_FIELD_NOT_EDITABLE');
        }

        $profile = $member?->crmProfile;
        foreach ($fields as $fieldKey => $policy) {
            if (! $policy['isVisible'] || ! $policy['isRequired']) {
                continue;
            }

            if (! $this->isFieldPresent($fieldKey, $payload, $profile, $isCreate, $member)) {
                abort(422, 'CRM_FIELD_REQUIRED');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function requestKeysFromPayload(array $payload): array
    {
        $keys = [];
        foreach (['name', 'mobile', 'gender', 'birthDate', 'nationalId', 'heightCm', 'weightKg', 'ownerStaffId'] as $key) {
            if (array_key_exists($key, $payload)) {
                $keys[] = $key;
            }
        }
        if (array_key_exists('assignToMe', $payload)) {
            $keys[] = 'ownerStaffId';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isFieldPresent(string $key, array $payload, ?MemberCrmProfile $profile, bool $isCreate, ?Member $member = null): bool
    {
        return match ($key) {
            'mobile' => $this->hasMobile($payload, $profile, $isCreate),
            'name' => filled($payload['name'] ?? ($isCreate ? null : $profile?->name)),
            'gender' => filled($payload['gender'] ?? ($isCreate ? null : $profile?->gender)),
            'birthDate' => filled($payload['birthDate'] ?? ($isCreate ? null : $profile?->birth_date?->format('Y-m-d'))),
            'nationalId' => array_key_exists('nationalId', $payload)
                ? filled($payload['nationalId'])
                : (! $isCreate && filled($profile?->national_id_hash)),
            'heightCm' => array_key_exists('heightCm', $payload)
                ? filled($payload['heightCm'])
                : (! $isCreate && $profile?->height_cm !== null),
            'weightKg' => array_key_exists('weightKg', $payload)
                ? filled($payload['weightKg'])
                : (! $isCreate && $profile?->weight_kg !== null),
            'ownerStaffId' => $this->hasOwnerStaff($payload, $isCreate, $member),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasMobile(array $payload, ?MemberCrmProfile $profile, bool $isCreate): bool
    {
        if (array_key_exists('mobile', $payload)) {
            return filled($payload['mobile']);
        }

        return ! $isCreate && filled($profile?->mobile_hash);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasOwnerStaff(array $payload, bool $isCreate, ?Member $member = null): bool
    {
        if (array_key_exists('ownerStaffId', $payload)) {
            return filled($payload['ownerStaffId']);
        }
        if (array_key_exists('assignToMe', $payload)) {
            return (bool) $payload['assignToMe'];
        }

        return ! $isCreate && filled($member?->owner_staff_id);
    }

    /**
     * @param  list<array{key: string, isRequired?: bool, isVisible?: bool, staffEditable?: bool}>  $updates
     * @return list<array{key: string, label: string, legacyIndex: int, isRequired: bool, isVisible: bool, staffEditable: bool}>
     */
    public function updateFields(Tenant $tenant, array $updates): array
    {
        $overrides = $this->fieldOverrides($tenant);

        foreach ($updates as $update) {
            $key = $update['key'];
            $current = $overrides[$key] ?? [];
            $merged = array_merge($current, array_filter([
                'isRequired' => $update['isRequired'] ?? null,
                'isVisible' => $update['isVisible'] ?? null,
                'staffEditable' => $update['staffEditable'] ?? null,
            ], fn ($value) => $value !== null));

            $this->assertFieldPolicyAllowed($key, $merged);
            $overrides[$key] = $merged;
        }

        $tenant->forceFill(['crm_field_policy' => ['fields' => $overrides]])->save();

        return $this->fields($tenant->refresh());
    }

    /**
     * @param  array{isRequired?: bool, isVisible?: bool, staffEditable?: bool}  $policy
     */
    private function assertFieldPolicyAllowed(string $key, array $policy): void
    {
        $isVisible = $policy['isVisible'] ?? self::FIELDS[$key]['defaultVisible'];
        $isRequired = $policy['isRequired'] ?? self::FIELDS[$key]['defaultRequired'];

        abort_if($key === 'name' && (! $isVisible || ! $isRequired), 422, 'CRM_FIELD_POLICY_LOCKED');
        abort_if($key === 'mobile' && $isVisible && ! $isRequired, 422, 'CRM_FIELD_POLICY_LOCKED');
    }

    /**
     * @return array<string, array{isRequired?: bool, isVisible?: bool, staffEditable?: bool}>
     */
    private function fieldOverrides(Tenant $tenant): array
    {
        $policy = $tenant->crm_field_policy;
        if (! is_array($policy)) {
            return [];
        }

        $fields = $policy['fields'] ?? $policy;

        return is_array($fields) ? $fields : [];
    }
}
