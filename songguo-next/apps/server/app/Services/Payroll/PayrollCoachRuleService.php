<?php

namespace App\Services\Payroll;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\PayrollCoachRule;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class PayrollCoachRuleService
{
    public function __construct(
        private readonly PayrollCoachConfigService $coachConfig,
    ) {}

    public function read(Staff $actor, Site $site, Staff $coach): array
    {
        $this->assertCoachAtSite($actor, $site, $coach);

        $rule = PayrollCoachRule::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $coach->id)
            ->first();

        $matrix = $this->normalizeMatrix($rule?->matrix ?? []);

        return [
            'coach' => $this->coachSummary($coach),
            'coachConfig' => $this->coachConfig->read($actor, $site),
            'matrixVersion' => $rule?->matrix_version ?? 0,
            'groupCourses' => $this->mergeCourseRows($actor, $site, CourseType::Group, $matrix['groupCourses']),
            'privateCourses' => $this->mergeCourseRows($actor, $site, CourseType::Private, $matrix['privateCourses']),
        ];
    }

    public function save(Staff $actor, Site $site, Staff $coach, array $payload): array
    {
        $this->assertCoachAtSite($actor, $site, $coach);

        $matrix = $this->normalizeMatrix([
            'groupCourses' => $payload['groupCourses'] ?? [],
            'privateCourses' => $payload['privateCourses'] ?? [],
        ]);

        $rule = PayrollCoachRule::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $coach->id)
            ->first();

        $nextVersion = ($rule?->matrix_version ?? 0) + 1;

        PayrollCoachRule::updateOrCreate(
            [
                'tenant_id' => $actor->tenant_id,
                'site_id' => $site->id,
                'staff_id' => $coach->id,
            ],
            [
                'matrix' => $matrix,
                'matrix_version' => $nextVersion,
            ],
        );

        return $this->read($actor, $site, $coach);
    }

    /**
     * @return list<array{staffId: int, name: string, employeeNo: string, rulesConfigured: bool, matrixVersion: int}>
     */
    public function coachList(Staff $actor, Site $site): array
    {
        $rules = PayrollCoachRule::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->whereNotNull('staff_id')
            ->get(['staff_id', 'matrix_version'])
            ->keyBy('staff_id');

        return Staff::query()
            ->where('staff.tenant_id', $actor->tenant_id)
            ->where('staff.status', 'active')
            ->whereHas('sites', fn (Builder $sites) => $sites
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id))
            ->orderBy('staff.name')
            ->get(['staff.id', 'staff.name', 'staff.employee_no'])
            ->map(function (Staff $coach) use ($rules) {
                $rule = $rules->get($coach->id);

                return [
                    'staffId' => $coach->id,
                    'name' => $coach->name,
                    'employeeNo' => $coach->employee_no,
                    'rulesConfigured' => $rule !== null,
                    'matrixVersion' => $rule?->matrix_version ?? 0,
                ];
            })
            ->all();
    }

    private function assertCoachAtSite(Staff $actor, Site $site, Staff $coach): void
    {
        abort_unless($coach->tenant_id === $actor->tenant_id, 404);

        abort_unless(
            $coach->sites()
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id)
                ->exists(),
            404,
        );
    }

    /**
     * @return array{staffId: int, name: string, employeeNo: string}
     */
    private function coachSummary(Staff $coach): array
    {
        return [
            'staffId' => $coach->id,
            'name' => $coach->name,
            'employeeNo' => $coach->employee_no,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $savedRows
     * @return list<array<string, mixed>>
     */
    private function mergeCourseRows(Staff $actor, Site $site, CourseType $type, array $savedRows): array
    {
        $savedByCourse = collect($savedRows)->keyBy('courseId');

        return Course::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('site_id', $site->id)
            ->where('course_type', $type)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'coach_staff_id'])
            ->map(function (Course $course) use ($savedByCourse) {
                $saved = $savedByCourse->get($course->id);

                return [
                    'courseId' => $course->id,
                    'courseName' => $course->name,
                    'coachStaffId' => $course->coach_staff_id,
                    'configured' => $saved !== null,
                    'unitPriceCents' => $saved['unitPriceCents'] ?? null,
                    'additionalPriceCents' => $saved['additionalPriceCents'] ?? null,
                    'supplementalRatePercent' => $saved['supplementalRatePercent'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{groupCourses: list<array<string, mixed>>, privateCourses: list<array<string, mixed>>}
     */
    private function normalizeMatrix(array $matrix): array
    {
        return [
            'groupCourses' => $this->normalizeCourseEntries($matrix['groupCourses'] ?? []),
            'privateCourses' => $this->normalizeCourseEntries($matrix['privateCourses'] ?? []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function normalizeCourseEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $courseId = (int) ($entry['courseId'] ?? 0);
            $unitPriceCents = (int) ($entry['unitPriceCents'] ?? 0);

            if ($courseId <= 0 || $unitPriceCents < 0) {
                continue;
            }

            $row = [
                'courseId' => $courseId,
                'unitPriceCents' => $unitPriceCents,
            ];

            if (array_key_exists('additionalPriceCents', $entry) && $entry['additionalPriceCents'] !== null) {
                $row['additionalPriceCents'] = max(0, (int) $entry['additionalPriceCents']);
            }

            if (array_key_exists('supplementalRatePercent', $entry) && $entry['supplementalRatePercent'] !== null) {
                $row['supplementalRatePercent'] = min(99, max(0, (int) $entry['supplementalRatePercent']));
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
