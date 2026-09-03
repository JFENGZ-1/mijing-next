<?php

namespace App\Services\Exports;

use App\Enums\ExportJobStatus;
use App\Enums\ExportJobType;
use App\Models\ExportJob;
use App\Models\Member;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\MemberCrmFieldPolicyService;
use App\Services\Members\MobileProtectionService;
use App\Services\Members\StaffCrmMemberListService;
use App\Services\Members\StaffMemberAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MemberExportGenerator
{
    /**
     * Legacy shopReport member column labels mapped to export keys.
     *
     * @var array<string, array{header: string, policyKey: string|null}>
     */
    private const COLUMNS = [
        'name' => ['header' => '姓名', 'policyKey' => 'name'],
        'gender' => ['header' => '性别', 'policyKey' => 'gender'],
        'mobile' => ['header' => '手机号', 'policyKey' => 'mobile'],
        'ownerStaffId' => ['header' => '会籍', 'policyKey' => 'ownerStaffId'],
        'birthDate' => ['header' => '生日', 'policyKey' => 'birthDate'],
        'memberNo' => ['header' => '会员号', 'policyKey' => null],
        'nationalId' => ['header' => '证件号', 'policyKey' => 'nationalId'],
        'heightCm' => ['header' => '身高', 'policyKey' => 'heightCm'],
        'weightKg' => ['header' => '体重', 'policyKey' => 'weightKg'],
        'remark' => ['header' => '备注', 'policyKey' => null],
        'joinedAt' => ['header' => '入会日期', 'policyKey' => null],
    ];

    public function __construct(
        private readonly StaffMemberAccessService $access,
        private readonly StaffCrmMemberListService $crmList,
        private readonly MemberCrmFieldPolicyService $fieldPolicy,
        private readonly MobileProtectionService $mobile,
    ) {}

    public function generate(Staff $staff, Site $site, ExportJob $job): string
    {
        $staff->loadMissing('tenant');
        $filters = is_array($job->filters) ? $job->filters : [];
        $selectedColumns = $this->resolveColumns($staff, $filters);
        $filterRequest = $this->filterRequest($filters);

        $query = $this->crmList->applyListFilters(
            $this->crmList->scopedQuery($staff, $site)->with(['crmProfile', 'owner']),
            $filterRequest,
            $staff,
            $site,
        );

        $members = $query->orderBy('members.id')->get();
        $profilesByAccount = $this->memberProfilesByAccount($members);

        $relativePath = sprintf(
            'exports/%d/%d/%d.csv',
            $staff->tenant_id,
            $site->id,
            $job->id,
        );

        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new RuntimeException('EXPORT_FILE_CREATE_FAILED');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(fn (string $key) => self::COLUMNS[$key]['header'], $selectedColumns));

        $canReadMobile = $staff->hasPermission('crm.member.read', $site->id);

        foreach ($members as $member) {
            $profile = $member->crmProfile;
            $accountProfile = $member->account_id ? ($profilesByAccount[$member->account_id] ?? null) : null;
            $row = [];

            foreach ($selectedColumns as $columnKey) {
                $row[] = match ($columnKey) {
                    'name' => $profile?->name ?? '',
                    'gender' => $this->formatGender($profile?->gender),
                    'mobile' => $this->formatMobile($profile, $canReadMobile),
                    'ownerStaffId' => $member->owner?->name ?? '',
                    'birthDate' => $profile?->birth_date?->format('Y-m-d') ?? '',
                    'memberNo' => $member->member_no,
                    'nationalId' => '',
                    'heightCm' => $accountProfile?->height_cm !== null ? (string) $accountProfile->height_cm : '',
                    'weightKg' => $accountProfile?->weight_kg !== null ? (string) $accountProfile->weight_kg : '',
                    'remark' => $profile?->sticky_remark ?? '',
                    'joinedAt' => $member->joined_at?->format('Y-m-d') ?? '',
                    default => '',
                };
            }

            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        if ($content === false || ! Storage::disk('local')->put($relativePath, $content)) {
            throw new RuntimeException('EXPORT_FILE_CREATE_FAILED');
        }

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function resolveColumns(Staff $staff, array $filters): array
    {
        $policyByKey = collect($this->fieldPolicy->fields($staff->tenant))->keyBy('key');
        $requested = collect($filters['columns'] ?? [])
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $key) => array_key_exists($key, self::COLUMNS))
            ->values()
            ->all();

        $candidateKeys = $requested !== [] ? $requested : array_keys(self::COLUMNS);

        return collect($candidateKeys)
            ->filter(function (string $key) use ($policyByKey) {
                $policyKey = self::COLUMNS[$key]['policyKey'];
                if ($policyKey === null) {
                    return true;
                }
                $policy = $policyByKey->get($policyKey);

                return $policy === null || ($policy['isVisible'] ?? true);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterRequest(array $filters): Request
    {
        $query = collect($filters)
            ->except(['columns'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return Request::create('/', 'GET', $query);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Member>  $members
     * @return array<int, MemberProfile>
     */
    private function memberProfilesByAccount($members): array
    {
        $accountIds = $members->pluck('account_id')->filter()->unique()->values()->all();
        if ($accountIds === []) {
            return [];
        }

        return MemberProfile::query()
            ->whereIn('account_id', $accountIds)
            ->get()
            ->keyBy('account_id')
            ->all();
    }

    private function formatGender(?string $gender): string
    {
        return match ($gender) {
            'male' => '男',
            'female' => '女',
            'undisclosed' => '未披露',
            default => '',
        };
    }

    private function formatMobile(?\App\Models\MemberCrmProfile $profile, bool $canReadMobile): string
    {
        if ($profile === null || blank($profile->mobile_hash)) {
            return '';
        }

        if ($canReadMobile && filled($profile->mobile_ciphertext)) {
            try {
                return $this->mobile->decrypt($profile->mobile_ciphertext);
            } catch (\Throwable) {
                return $profile->mobile_last4 ? "*******{$profile->mobile_last4}" : '';
            }
        }

        return $profile->mobile_last4 ? "*******{$profile->mobile_last4}" : '';
    }
}
