<?php

namespace App\Services\Compensation;

use App\Models\CompensationRole;
use App\Models\Course;
use App\Models\CourseCompensationRoleRate;
use App\Models\CourseCompensationRule;
use App\Models\Site;
use App\Services\Catalog\CatalogCommandReceiptService;
use App\Support\DomainActor;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CourseCompensationRuleService
{
    public function __construct(private readonly CatalogCommandReceiptService $receipts) {}

    public function currentForCourse(
        int $tenantId,
        int $siteId,
        int $courseId,
        CarbonInterface|string|null $at = null,
    ): ?CourseCompensationRule {
        $effectiveAt = $at === null ? now() : Carbon::parse($at);

        return CourseCompensationRule::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'scheduled', 'superseded'])
            ->where('effective_at', '<=', $effectiveAt)
            ->where(fn ($query) => $query
                ->whereNull('archived_at')
                ->orWhere('archived_at', '>', $effectiveAt))
            ->with('roleRates.role')
            ->orderByDesc('effective_at')
            ->orderByDesc('version')
            ->first();
    }

    /** Latest editable version for management screens, including future schedules. */
    public function latestForCourse(int $tenantId, int $siteId, int $courseId): ?CourseCompensationRule
    {
        return CourseCompensationRule::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'scheduled'])
            ->with('roleRates.role')
            ->orderByDesc('version')
            ->first();
    }

    public function saveVersion(
        DomainActor $actor,
        Site $site,
        Course $course,
        array $payload,
        ?string $commandKey = null,
        ?int $expectedVersion = null,
        ?string $reason = null,
    ): CourseCompensationRule {
        abort_unless(
            $course->tenant_id === $site->tenant_id && $course->site_id === $site->id,
            404,
        );
        abort_if((int) $payload['sessionFeeCents'] < 0, 422, 'COURSE_COMPENSATION_FEE_INVALID');

        $canonical = $payload;
        $canonical['roleRates'] = collect($payload['roleRates'] ?? [])
            ->sortBy(fn ($rate) => (int) $rate['compensationRoleId'])->values()->all();
        $hash = $this->receipts->payloadHash([
            'courseId' => $course->id, 'payload' => $canonical,
            'expectedVersion' => $expectedVersion, 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $course, $payload, $commandKey, $expectedVersion, $reason, $hash) {
            Course::query()->where('tenant_id', $site->tenant_id)->where('site_id', $site->id)
                ->whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($commandKey !== null && ($receipt = $this->receipts->replay(
                $site, 'course_compensation_rule', 'save', $commandKey, $hash,
            )) !== null) {
                return CourseCompensationRule::query()->with('roleRates.role')->findOrFail($receipt->resource_id);
            }
            $current = CourseCompensationRule::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'scheduled'])
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();
            if ($expectedVersion !== null) {
                abort_unless(($current?->version ?? 0) === $expectedVersion, 409, 'COURSE_COMPENSATION_RULE_VERSION_CONFLICT');
            }

            $roleRates = collect($payload['roleRates'] ?? []);
            $roleIds = $roleRates->pluck('compensationRoleId')->map(fn ($id) => (int) $id)->unique()->values();
            abort_unless($roleIds->count() === $roleRates->count(), 422, 'COURSE_COMPENSATION_ROLE_DUPLICATE');
            $roles = CompensationRole::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->where('status', 'active')
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
            abort_unless($roles->count() === $roleIds->count(), 422, 'COMPENSATION_ROLE_INVALID');

            foreach ($roleRates as $rate) {
                abort_if((int) $rate['rateBps'] < 0 || (int) $rate['rateBps'] > 10000, 422, 'COURSE_COMPENSATION_RATE_INVALID');
            }

            $effectiveAt = isset($payload['effectiveAt']) ? Carbon::parse($payload['effectiveAt']) : now();
            $isFuture = $effectiveAt->isFuture();
            if (! $isFuture) {
                CourseCompensationRule::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('course_id', $course->id)
                    ->whereIn('status', ['active', 'scheduled'])
                    ->update(['status' => 'superseded', 'archived_at' => now()]);
            }

            $rule = CourseCompensationRule::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'course_id' => $course->id,
                'session_fee_cents' => (int) $payload['sessionFeeCents'],
                'version' => ($current?->version ?? 0) + 1,
                'status' => $isFuture ? 'scheduled' : 'active',
                'supersedes_id' => $current?->id,
                'created_by_staff_id' => $actor->staffId(),
                'effective_at' => $effectiveAt,
            ]);

            foreach ($roleRates as $rate) {
                CourseCompensationRoleRate::create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'course_compensation_rule_id' => $rule->id,
                    'compensation_role_id' => (int) $rate['compensationRoleId'],
                    'rate_bps' => (int) $rate['rateBps'],
                ]);
            }

            $rule->load('roleRates.role');
            if ($commandKey !== null) {
                $this->receipts->record(
                    $actor, $site, 'course_compensation_rule', $rule->id, 'save',
                    $commandKey, $hash, $rule->version, $reason,
                );
            }

            return $rule;
        });
    }

    public function present(?CourseCompensationRule $rule): ?array
    {
        if ($rule === null) {
            return null;
        }

        return [
            'id' => $rule->id,
            'courseId' => $rule->course_id,
            'sessionFeeCents' => $rule->session_fee_cents,
            'version' => $rule->version,
            'status' => $rule->status,
            'effectiveAt' => $rule->effective_at?->toIso8601String(),
            'roleRates' => $rule->roleRates->map(fn (CourseCompensationRoleRate $rate) => [
                'compensationRoleId' => $rate->compensation_role_id,
                'roleName' => $rate->role?->name,
                'roleType' => $rate->role?->role_type,
                'rateBps' => $rate->rate_bps,
            ])->values()->all(),
        ];
    }
}
