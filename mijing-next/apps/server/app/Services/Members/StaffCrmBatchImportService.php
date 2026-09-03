<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffCrmBatchImportService
{
    public function __construct(
        private readonly MobileProtectionService $mobile,
        private readonly MemberCrmFieldPolicyService $fieldPolicy,
        private readonly MemberAuditService $audit,
    ) {}

    /**
     * @param  array{lines?: list<array{name?: string, mobile?: string}>, text?: string, assignToMe?: bool}  $payload
     * @return array{successCount: int, failCount: int, errors: list<array{line: int, raw: string, code: string, message: string}>}
     */
    public function import(Staff $staff, Site $site, array $payload, string $requestId): array
    {
        $staff->loadMissing('tenant');
        $rows = $this->parseRows($payload);
        $errors = [];
        $successCount = 0;
        $seenMobileHashes = [];
        $assignToMe = (bool) ($payload['assignToMe'] ?? true);

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $raw = $row['raw'];

            if ($row['name'] === null || $row['mobile'] === null) {
                $errors[] = $this->lineError($line, $raw, 'INVALID_LINE', '每行至少需要手机号和姓名');

                continue;
            }

            try {
                $this->fieldPolicy->assertUpsertAllowed($staff->tenant, [
                    'name' => $row['name'],
                    'mobile' => $row['mobile'],
                ], isCreate: true);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $code = $exception->getStatusCode() === 422 ? ($exception->getMessage() ?: 'CRM_FIELD_REQUIRED') : 'INVALID_LINE';
                $errors[] = $this->lineError($line, $raw, $code, $this->fieldPolicyMessage($code));

                continue;
            }

            try {
                $normalized = $this->mobile->normalize($row['mobile']);
            } catch (\InvalidArgumentException) {
                $errors[] = $this->lineError($line, $raw, 'INVALID_MOBILE', '手机号格式无效');

                continue;
            }

            $mobileHash = $this->mobile->hashForTenant($normalized, $staff->tenant_id);
            if (isset($seenMobileHashes[$mobileHash])) {
                $errors[] = $this->lineError($line, $raw, 'DUPLICATE_MOBILE', '本批次中手机号重复');

                continue;
            }
            $seenMobileHashes[$mobileHash] = true;

            if (MemberCrmProfile::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('mobile_hash', $mobileHash)
                ->whereHas('member', fn ($member) => $member->whereNull('archived_at'))
                ->exists()) {
                $errors[] = $this->lineError($line, $raw, 'DUPLICATE_MOBILE', '该手机号已存在于当前租户');

                continue;
            }

            try {
                DB::transaction(function () use ($staff, $site, $row, $normalized, $mobileHash, $assignToMe, $requestId) {
                    $contact = [
                        'mobile_ciphertext' => $this->mobile->encrypt($normalized),
                        'mobile_hash' => $mobileHash,
                        'mobile_last4' => substr($normalized, -4),
                        'mobile_source' => 'staff_entered',
                        'mobile_verified_at' => null,
                    ];
                    $member = Member::create([
                        'tenant_id' => $staff->tenant_id,
                        'account_id' => null,
                        'member_no' => 'M'.strtoupper((string) Str::ulid()),
                        'status' => 'lead',
                        'app_access_status' => 'allowed',
                        'source' => 'staff-batch-import',
                        'registration_site_id' => $site->id,
                        'home_site_id' => $site->id,
                        'owner_staff_id' => $assignToMe ? $staff->id : null,
                        'joined_at' => now(),
                        'status_changed_at' => now(),
                        'status_changed_by_staff_id' => $staff->id,
                    ]);
                    MemberCrmProfile::create([
                        'tenant_id' => $staff->tenant_id,
                        'member_id' => $member->id,
                        'name' => $row['name'],
                        ...$contact,
                    ]);
                    DB::table('member_sites')->insert([
                        'tenant_id' => $staff->tenant_id,
                        'member_id' => $member->id,
                        'site_id' => $site->id,
                        'relationship_type' => 'registered',
                        'status' => 'active',
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('member_status_events')->insert([
                        'tenant_id' => $staff->tenant_id,
                        'member_id' => $member->id,
                        'from_status' => null,
                        'to_status' => 'lead',
                        'reason' => '批量导入潜客',
                        'site_id' => $site->id,
                        'actor_staff_id' => $staff->id,
                        'request_id' => $requestId,
                        'occurred_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $successCount++;
            } catch (QueryException) {
                $errors[] = $this->lineError($line, $raw, 'DUPLICATE_MOBILE', '该手机号已存在于当前租户');
            }
        }

        return [
            'successCount' => $successCount,
            'failCount' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array{lines?: list<array{name?: string, mobile?: string}>, text?: string}  $payload
     * @return list<array{raw: string, name: ?string, mobile: ?string}>
     */
    private function parseRows(array $payload): array
    {
        if (isset($payload['lines']) && is_array($payload['lines'])) {
            return collect($payload['lines'])
                ->map(fn (array $line) => [
                    'raw' => trim(($line['mobile'] ?? '').($line['name'] ?? '')),
                    'mobile' => isset($line['mobile']) ? trim((string) $line['mobile']) : null,
                    'name' => isset($line['name']) ? trim((string) $line['name']) : null,
                ])
                ->all();
        }

        $text = trim((string) ($payload['text'] ?? ''));

        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                if (preg_match('/^(\+?[0-9][0-9 -]{6,23})(.*)$/u', $line, $matches)) {
                    $mobile = trim($matches[1]);
                    $name = trim($matches[2]);

                    return ['raw' => $line, 'mobile' => $mobile !== '' ? $mobile : null, 'name' => $name !== '' ? $name : null];
                }

                return ['raw' => $line, 'mobile' => null, 'name' => null];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{line: int, raw: string, code: string, message: string}
     */
    private function lineError(int $line, string $raw, string $code, string $message): array
    {
        return [
            'line' => $line,
            'raw' => $raw,
            'code' => $code,
            'message' => $message,
        ];
    }

    private function fieldPolicyMessage(string $code): string
    {
        return match ($code) {
            'CRM_FIELD_REQUIRED' => '缺少租户要求的必填字段',
            'CRM_FIELD_NOT_EDITABLE' => '字段不可编辑',
            default => '字段校验失败',
        };
    }
}
