<?php

namespace App\Services\Catalog;

use App\Enums\CardProductCourseScopeKind;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\CoachPrivateProfile;
use App\Models\Course;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use App\Services\Booking\PrivateCoachAvailabilityService;
use Illuminate\Support\Facades\DB;

/**
 * 私教档案（对标原版 drainer）：私教按教练维度配置预约时段，不走固定排课。
 * 统一模式（uniform）自动维护一门隐藏课目用于挂会员卡扣费。
 */
class CoachPrivateProfileService
{
    public function __construct(
        private readonly BookingPolicyService $bookingPolicy,
        private readonly PrivateCoachAvailabilityService $availability,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Staff $staff, Site $site): array
    {
        return CoachPrivateProfile::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->with('coach:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (CoachPrivateProfile $profile) => $this->present($profile))
            ->all();
    }

    public function find(Staff $staff, Site $site, int $profileId): CoachPrivateProfile
    {
        $profile = CoachPrivateProfile::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($profileId)
            ->with('coach:id,name')
            ->first();

        abort_unless($profile !== null, 404, 'NOT_FOUND');

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(Staff $staff, Site $site, array $payload): array
    {
        return DB::transaction(function () use ($staff, $site, $payload) {
            $coach = $this->assertCoach($staff->tenant_id, (int) $payload['coachStaffId']);

            $exists = CoachPrivateProfile::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->where('coach_staff_id', $coach->id)
                ->exists();
            abort_if($exists, 422, 'COACH_PRIVATE_PROFILE_EXISTS');

            $profile = CoachPrivateProfile::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'coach_staff_id' => $coach->id,
                'tag_text' => $payload['tagText'] ?? null,
                'experience' => $payload['experience'] ?? null,
                'specialty' => $payload['specialty'] ?? null,
                'booking_windows' => $this->normalizeWindows($payload['bookingWindows'] ?? []),
                'subject_mode' => $payload['subjectMode'] ?? 'uniform',
                'uniform_duration_minutes' => (int) ($payload['uniformDurationMinutes'] ?? 60),
                'version' => 1,
            ]);

            $this->syncUniformCourse($staff, $site, $profile->fresh('coach'));

            return $this->present($profile->fresh(['coach']));
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(Staff $staff, Site $site, CoachPrivateProfile $profile, array $payload): array
    {
        return DB::transaction(function () use ($staff, $site, $profile, $payload) {
            abort_if((int) $payload['version'] !== $profile->version, 409, 'VERSION_CONFLICT');

            // 对齐原版「请设置私教科目」拦截：切成课目模式时该教练须已有课目，
            // 服务端兜底防"课目模式 + 零课目"非法态（约私教课目会无课可选）。
            if (($payload['subjectMode'] ?? null) === 'per_course') {
                abort_unless($this->hasActiveSubjects($profile), 422, '请设置私教科目');
            }

            $attributes = ['version' => $profile->version + 1];
            foreach ([
                'tagText' => 'tag_text',
                'experience' => 'experience',
                'specialty' => 'specialty',
                'subjectMode' => 'subject_mode',
            ] as $input => $column) {
                if (array_key_exists($input, $payload)) {
                    $attributes[$column] = $payload[$input];
                }
            }
            if (array_key_exists('bookingWindows', $payload)) {
                $attributes['booking_windows'] = $this->normalizeWindows($payload['bookingWindows']);
            }
            if (array_key_exists('uniformDurationMinutes', $payload)) {
                $attributes['uniform_duration_minutes'] = (int) $payload['uniformDurationMinutes'];
            }

            $profile->update($attributes);
            $this->syncUniformCourse($staff, $site, $profile->fresh('coach'));

            return $this->present($profile->fresh(['coach']));
        });
    }

    /**
     * 一次性保存（对标原版 savePrivateCourse）：档案 + 课目增删改 + 每课目卡扣费。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveFull(Staff $staff, Site $site, array $payload): array
    {
        return DB::transaction(function () use ($staff, $site, $payload) {
            // 对齐原版 savePrivateCourse 校验：课目模式必须随包带至少一门课目
            // （编辑时未带 courses 则要求沿用中的现存课目非空），防"课目模式 + 零课目"。
            if (($payload['subjectMode'] ?? 'uniform') === 'per_course') {
                if (array_key_exists('courses', $payload)) {
                    abort_if(count($payload['courses'] ?? []) === 0, 422, '请设置私教科目');
                } elseif (! empty($payload['profileId'])) {
                    $current = CoachPrivateProfile::query()->find((int) $payload['profileId']);
                    abort_unless($current !== null && $this->hasActiveSubjects($current), 422, '请设置私教科目');
                } else {
                    abort(422, '请设置私教科目');
                }
            }

            // 1) 档案 upsert
            if (! empty($payload['profileId'])) {
                $profile = $this->find($staff, $site, (int) $payload['profileId']);
                abort_if((int) ($payload['version'] ?? 0) !== $profile->version, 409, 'VERSION_CONFLICT');
                $profile->update([
                    'tag_text' => $payload['tagText'] ?? null,
                    'experience' => $payload['experience'] ?? null,
                    'specialty' => $payload['specialty'] ?? null,
                    'booking_windows' => $this->normalizeWindows($payload['bookingWindows'] ?? []),
                    'subject_mode' => $payload['subjectMode'] ?? 'uniform',
                    'uniform_duration_minutes' => (int) ($payload['uniformDurationMinutes'] ?? 60),
                    'version' => $profile->version + 1,
                ]);
            } else {
                $coach = $this->assertCoach($staff->tenant_id, (int) $payload['coachStaffId']);
                $exists = CoachPrivateProfile::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('site_id', $site->id)
                    ->where('coach_staff_id', $coach->id)
                    ->exists();
                abort_if($exists, 422, 'COACH_PRIVATE_PROFILE_EXISTS');

                $profile = CoachPrivateProfile::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'coach_staff_id' => $coach->id,
                    'tag_text' => $payload['tagText'] ?? null,
                    'experience' => $payload['experience'] ?? null,
                    'specialty' => $payload['specialty'] ?? null,
                    'booking_windows' => $this->normalizeWindows($payload['bookingWindows'] ?? []),
                    'subject_mode' => $payload['subjectMode'] ?? 'uniform',
                    'uniform_duration_minutes' => (int) ($payload['uniformDurationMinutes'] ?? 60),
                    'version' => 1,
                ]);
            }

            $this->syncUniformCourse($staff, $site, $profile->fresh('coach'));
            $profile = $profile->fresh('coach');

            // 2) 课目 diff（per_course 模式；uniform 模式提交空列表即归档全部课目）
            if (array_key_exists('courses', $payload)) {
                $this->reconcileSubjectCourses($staff, $site, $profile, $payload['courses'] ?? []);
            }

            // 3) 统一模式卡扣费
            if (array_key_exists('uniformFeeList', $payload) && $profile->uniform_course_id !== null) {
                $this->applyFees($staff, $site, (int) $profile->uniform_course_id, $payload['uniformFeeList'] ?? []);
            }

            return $this->present($profile->fresh(['coach']));
        });
    }

    /**
     * 课目增删改 + 各自 feeList（对标原版 courseList）。
     *
     * @param  list<array<string, mixed>>  $courses
     */
    private function reconcileSubjectCourses(Staff $staff, Site $site, CoachPrivateProfile $profile, array $courses): void
    {
        $existing = Course::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('course_type', CourseType::Private)
            ->where('coach_staff_id', $profile->coach_staff_id)
            ->where('hidden_in_catalog', false)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->get()
            ->keyBy('id');

        $keptIds = [];
        foreach ($courses as $index => $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $duration = (int) ($item['durationMinutes'] ?? 60);
            if ($name === '' || $duration < 1) {
                continue;
            }

            $courseId = isset($item['id']) ? (int) $item['id'] : null;
            if ($courseId !== null && $existing->has($courseId)) {
                $existing[$courseId]->update([
                    'name' => $name,
                    'duration_minutes' => $duration,
                    'sort_order' => $index,
                ]);
                $keptIds[] = $courseId;
            } else {
                $course = Course::create([
                    'tenant_id' => $profile->tenant_id,
                    'site_id' => $profile->site_id,
                    'course_type' => CourseType::Private,
                    'name' => $name,
                    'duration_minutes' => $duration,
                    'coach_staff_id' => $profile->coach_staff_id,
                    'hidden_in_catalog' => false,
                    'catalog_status' => CourseCatalogStatus::Active,
                    'sort_order' => $index,
                    'version' => 1,
                    'created_by_staff_id' => $staff->id,
                ]);
                $courseId = $course->id;
                $keptIds[] = $courseId;
            }

            if (array_key_exists('feeList', $item)) {
                $this->applyFees($staff, $site, $courseId, $item['feeList'] ?? []);
            }
        }

        // 未保留的课目归档（对标原版删除课目）
        foreach ($existing as $course) {
            if (! in_array($course->id, $keptIds, true)) {
                $course->update([
                    'catalog_status' => CourseCatalogStatus::Archived,
                    'archived_at' => now(),
                ]);
            }
        }
    }

    /**
     * 以课程为轴 reconcile 各卡的 courseScopes（对标原版 feeList）。
     *
     * @param  list<array<string, mixed>>  $feeList
     */
    private function applyFees(Staff $staff, Site $site, int $courseId, array $feeList): void
    {
        $wanted = [];
        foreach ($feeList as $fee) {
            $cardId = (int) ($fee['cardProductId'] ?? 0);
            if ($cardId < 1) {
                continue;
            }
            $wanted[$cardId] = isset($fee['deductAmount']) && $fee['deductAmount'] !== null && $fee['deductAmount'] !== ''
                ? (float) $fee['deductAmount']
                : null;
        }

        // 校验卡归属（租户+门店）
        if ($wanted !== []) {
            $validCount = CardProduct::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereIn('id', array_keys($wanted))
                ->count();
            abort_unless($validCount === count($wanted), 404, 'NOT_FOUND');
        }

        // 当前关联此课程的 scopes
        $current = CardProductCourseScope::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('scope_kind', CardProductCourseScopeKind::Single)
            ->where('scope_key', (string) $courseId)
            ->get();

        foreach ($current as $scope) {
            if (! array_key_exists($scope->card_product_id, $wanted)) {
                $scope->delete();

                continue;
            }
            $scope->update(['price_override' => $wanted[$scope->card_product_id]]);
            unset($wanted[$scope->card_product_id]);
        }

        $courseName = Course::query()->whereKey($courseId)->value('name');
        foreach ($wanted as $cardId => $amount) {
            CardProductCourseScope::create([
                'tenant_id' => $staff->tenant_id,
                'card_product_id' => $cardId,
                'scope_kind' => CardProductCourseScopeKind::Single,
                'scope_key' => (string) $courseId,
                'display_name' => $courseName,
                'price_override' => $amount,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * 时间槽（对标原版 getDrainerTimeList）：按课目时长与预约设置「私教预约时间间隔」生成当天候选开始时间。
     * 槽可约条件：落在当天预约窗口内、未过期、且不与教练「有有效预约」的 session 重叠。
     *
     * @return array{date: string, durationMinutes: int, slots: list<array{start: string, startsAt: string, available: bool}>}
     */
    public function timeSlots(
        Site $site,
        CoachPrivateProfile $profile,
        string $date,
        int $courseId = 0,
        ?int $excludeSessionId = null,
    ): array {
        $course = $this->resolveCourseForTimeSlots($profile, $courseId);
        abort_unless($course !== null, 422, 'COACH_PRIVATE_SUBJECT_REQUIRED');

        $duration = max(1, (int) $course->duration_minutes);
        $policy = $this->bookingPolicy->policyForTenantSite($profile->tenant_id, $profile->site_id);
        $private = $policy['private'];
        $slotStep = max(5, (int) ($private['slotIntervalMinutes'] ?? 30));
        $grayOut = (bool) ($private['grayOutBookedSlots'] ?? true);
        $day = \Carbon\Carbon::parse($date)->startOfDay();

        $coachSessions = $this->availability->coachSessionsOnDay($profile, $day);
        $slotList = $this->availability->buildSlotsForDay(
            $day,
            $coachSessions,
            $private,
            $duration,
            $slotStep,
            $profile->booking_windows ?? [],
            $excludeSessionId,
            $grayOut,
        );

        return [
            'date' => $date,
            'durationMinutes' => $duration,
            'slotIntervalMinutes' => $slotStep,
            'grayOutBookedSlots' => $grayOut,
            'slots' => $slotList,
        ];
    }

    /**
     * 私教代约：解析课目 + 校验可约时段/冲突，动态创建（或复用）capacity=1 的 private session。
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveBookableSession(
        ?Staff $staff,
        Site $site,
        CoachPrivateProfile $profile,
        array $payload,
    ): \App\Models\ScheduleSession {
        // 约私教课目与约私教是两码事，由 courseId 有无分流，与 subject_mode 解耦：
        // 传了 courseId → 必须是该教练名下未隐藏课目；
        // 未传 → 统一隐藏课（统一时长统一定价），课目未配置时也能约私教。
        $courseId = (int) ($payload['courseId'] ?? 0);
        if ($courseId > 0) {
            $course = Course::query()
                ->where('tenant_id', $profile->tenant_id)
                ->where('site_id', $profile->site_id)
                ->where('course_type', CourseType::Private)
                ->where('coach_staff_id', $profile->coach_staff_id)
                ->where('hidden_in_catalog', false)
                ->where('catalog_status', CourseCatalogStatus::Active)
                ->whereKey($courseId)
                ->first();
            abort_unless($course !== null, 422, 'COACH_PRIVATE_SUBJECT_REQUIRED');
        } else {
            $course = Course::query()->whereKey($profile->uniform_course_id)->first();
            abort_unless($course !== null, 422, 'COACH_PRIVATE_SUBJECT_REQUIRED');
        }

        $startsAt = \Carbon\Carbon::parse($payload['date'].' '.$payload['start'].':00');
        $endsAt = $startsAt->copy()->addMinutes($course->duration_minutes);

        // 可约时段校验（booking_windows：days 1=周一…7=周日）
        $weekday = $startsAt->dayOfWeekIso;
        $startStr = $startsAt->format('H:i');
        $endStr = $endsAt->format('H:i');
        $inWindow = collect($profile->booking_windows ?? [])->contains(function ($window) use ($weekday, $startStr, $endStr) {
            return in_array($weekday, $window['days'] ?? [], true)
                && $startStr >= ($window['start'] ?? '00:00')
                && $endStr <= ($window['end'] ?? '24:00');
        });
        abort_unless($inWindow, 422, 'COACH_PRIVATE_TIME_OUT_OF_WINDOW');

        // 教练时间冲突（已有未取消的排课重叠）。
        // 「预约已全取消」的孤儿 session 不占用时间段（对齐原版：取消即释放）：
        // 仅当重叠 session 仍存在有效预约（confirmed/completed/waitlisted）才算真冲突。
        $overlaps = \App\Models\ScheduleSession::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('coach_staff_id', $profile->coach_staff_id)
            ->whereIn('status', ['scheduled', 'suspended'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with(['appointments' => fn ($query) => $query->whereIn('status', [
                \App\Enums\AppointmentStatus::Confirmed,
                \App\Enums\AppointmentStatus::Completed,
                \App\Enums\AppointmentStatus::Waitlisted,
            ])])
            ->get();

        foreach ($overlaps as $conflict) {
            $sameSlot = $conflict->course_id === $course->id
                && $conflict->starts_at->equalTo($startsAt)
                && $conflict->ends_at->equalTo($endsAt);
            if ($sameSlot) {
                return $conflict;
            }
        }

        $policy = $this->bookingPolicy->policyForTenantSite($profile->tenant_id, $profile->site_id);
        $this->availability->assertBookableSlot(
            $startsAt,
            $endsAt,
            $overlaps,
            $policy['private'],
            (bool) ($payload['acknowledgeGroupOverlap'] ?? false),
        );

        return \App\Models\ScheduleSession::create([
            'tenant_id' => $profile->tenant_id,
            'site_id' => $profile->site_id,
            'course_id' => $course->id,
            'coach_staff_id' => $profile->coach_staff_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 1,
            'booked_count' => 0,
            'status' => 'scheduled',
            'session_kind' => 'private',
            'version' => 1,
            'created_by_staff_id' => $staff?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function memberPresentation(CoachPrivateProfile $profile): array
    {
        $full = $this->present($profile);

        return [
            'id' => $full['id'],
            'coachStaffId' => $full['coachStaffId'],
            'coachName' => $full['coachName'],
            'subjectMode' => $full['subjectMode'],
            'uniformDurationMinutes' => $full['uniformDurationMinutes'],
            'courses' => $full['courses'],
        ];
    }

    public function delete(Staff $staff, Site $site, CoachPrivateProfile $profile): void
    {
        DB::transaction(function () use ($profile) {
            if ($profile->uniform_course_id !== null) {
                Course::query()
                    ->where('tenant_id', $profile->tenant_id)
                    ->whereKey($profile->uniform_course_id)
                    ->update([
                        'catalog_status' => CourseCatalogStatus::Archived,
                        'archived_at' => now(),
                    ]);
            }
            $profile->delete();
        });
    }

    /**
     * 统一模式：确保存在隐藏课目（挂卡扣费）；时长/教练名变化时同步。
     */
    /** 该档案教练名下是否存在有效私教课目（未隐藏、上架中） */
    private function hasActiveSubjects(CoachPrivateProfile $profile): bool
    {
        return Course::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('course_type', CourseType::Private)
            ->where('coach_staff_id', $profile->coach_staff_id)
            ->where('hidden_in_catalog', false)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->exists();
    }

    private function syncUniformCourse(Staff $staff, Site $site, CoachPrivateProfile $profile): void
    {
        $courseName = ($profile->coach?->name ?? '教练').'私教';

        if ($profile->uniform_course_id === null) {
            $course = Course::create([
                'tenant_id' => $profile->tenant_id,
                'site_id' => $profile->site_id,
                'course_type' => CourseType::Private,
                'name' => $courseName,
                'duration_minutes' => $profile->uniform_duration_minutes,
                'coach_staff_id' => $profile->coach_staff_id,
                'hidden_in_catalog' => true,
                'catalog_status' => CourseCatalogStatus::Active,
                'version' => 1,
                'created_by_staff_id' => $staff->id,
            ]);
            $profile->update(['uniform_course_id' => $course->id]);

            return;
        }

        Course::query()
            ->where('tenant_id', $profile->tenant_id)
            ->whereKey($profile->uniform_course_id)
            ->update([
                'name' => $courseName,
                'duration_minutes' => $profile->uniform_duration_minutes,
                'coach_staff_id' => $profile->coach_staff_id,
                'catalog_status' => CourseCatalogStatus::Active,
                'archived_at' => null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CoachPrivateProfile $profile): array
    {
        // 课目：该教练名下未隐藏的私教课（per_course 模式的课目列表）
        $courseModels = Course::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('course_type', CourseType::Private)
            ->where('coach_staff_id', $profile->coach_staff_id)
            ->where('hidden_in_catalog', false)
            ->where('catalog_status', CourseCatalogStatus::Active)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'duration_minutes', 'version']);

        // 每课目（含统一隐藏课）的卡扣费（对标原版 feeList）
        $courseIds = $courseModels->pluck('id')->all();
        if ($profile->uniform_course_id !== null) {
            $courseIds[] = (int) $profile->uniform_course_id;
        }
        $feesByCourseId = [];
        if ($courseIds !== []) {
            $scopes = CardProductCourseScope::query()
                ->where('tenant_id', $profile->tenant_id)
                ->where('scope_kind', CardProductCourseScopeKind::Single)
                ->whereIn('scope_key', array_map('strval', $courseIds))
                ->with('cardProduct:id,name')
                ->get();
            foreach ($scopes as $scope) {
                $feesByCourseId[(int) $scope->scope_key][] = [
                    'cardProductId' => $scope->card_product_id,
                    'cardName' => $scope->cardProduct?->name,
                    'deductAmount' => $scope->price_override !== null ? (string) $scope->price_override : null,
                ];
            }
        }

        $courses = $courseModels
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'durationMinutes' => $course->duration_minutes,
                'version' => $course->version,
                'feeList' => $feesByCourseId[$course->id] ?? [],
            ])
            ->all();

        return [
            'id' => $profile->id,
            'coachStaffId' => $profile->coach_staff_id,
            'coachName' => $profile->coach?->name,
            'tagText' => $profile->tag_text,
            'experience' => $profile->experience,
            'specialty' => $profile->specialty,
            'bookingWindows' => $profile->booking_windows ?? [],
            'subjectMode' => $profile->subject_mode,
            'uniformDurationMinutes' => $profile->uniform_duration_minutes,
            'uniformCourseId' => $profile->uniform_course_id,
            'uniformFeeList' => $profile->uniform_course_id !== null
                ? ($feesByCourseId[(int) $profile->uniform_course_id] ?? [])
                : [],
            'courses' => $courses,
            'version' => $profile->version,
        ];
    }

    /**
     * 时间槽课目：与代约一致优先可售课目；改约时节上挂的课目可能已隐藏，仍按其时长出槽。
     */
    private function resolveCourseForTimeSlots(CoachPrivateProfile $profile, int $courseId): ?Course
    {
        $coachCourses = fn () => Course::query()
            ->where('tenant_id', $profile->tenant_id)
            ->where('site_id', $profile->site_id)
            ->where('course_type', CourseType::Private)
            ->where('coach_staff_id', $profile->coach_staff_id);

        if ($courseId > 0) {
            $course = (clone $coachCourses)()
                ->where('hidden_in_catalog', false)
                ->where('catalog_status', CourseCatalogStatus::Active)
                ->whereKey($courseId)
                ->first();
            if ($course !== null) {
                return $course;
            }

            return (clone $coachCourses)()->whereKey($courseId)->first();
        }

        return Course::query()->whereKey($profile->uniform_course_id)->first();
    }

    private function assertCoach(int $tenantId, int $coachStaffId): Staff
    {
        $coach = Staff::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($coachStaffId)
            ->where('status', 'active')
            ->first();

        abort_unless($coach !== null, 422, 'COACH_PRIVATE_COACH_INVALID');

        return $coach;
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{days: list<int>, start: string, end: string}>
     */
    private function normalizeWindows(array $raw): array
    {
        $windows = [];
        foreach ($raw as $window) {
            if (! is_array($window)) {
                continue;
            }
            $days = collect($window['days'] ?? [])
                ->map(fn ($day) => (int) $day)
                ->filter(fn (int $day) => $day >= 1 && $day <= 7)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $start = (string) ($window['start'] ?? '');
            $end = (string) ($window['end'] ?? '');
            if ($days === [] || ! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
                continue;
            }
            abort_if($start >= $end, 422, 'COACH_PRIVATE_WINDOW_INVALID');
            $windows[] = ['days' => $days, 'start' => $start, 'end' => $end];
        }

        return $windows;
    }
}
