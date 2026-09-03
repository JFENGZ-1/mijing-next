<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_year_stats_aggregate_attendance_by_course_type_and_status(): void
    {
        [$account, $tenant, $member, $groupSession, $privateSession] = $this->seedFixture();
        $year = 2026;

        $this->seedAppointment($member, $groupSession, AppointmentStatus::Completed, $account->id);
        $this->seedAppointment($member, $groupSession, AppointmentStatus::Absent, $account->id);
        $this->seedAppointment($member, $privateSession, AppointmentStatus::Completed, $account->id);
        $this->seedAppointment($member, $privateSession, AppointmentStatus::Cancelled, $account->id);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/stats/year?tenantId={$tenant->id}&year={$year}")
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.totalCount', 2)
            ->assertJsonPath('data.teamTimes', 1)
            ->assertJsonPath('data.teamAbsent', 1)
            ->assertJsonPath('data.privateTimes', 1)
            ->assertJsonPath('data.privateAbsent', 0)
            ->assertJsonPath('data.cancelledCount', 1)
            ->assertJsonPath('data.months.6.month', 7)
            ->assertJsonPath('data.months.6.teamTimes', 1)
            ->assertJsonPath('data.months.6.privateTimes', 1);
    }

    public function test_month_stats_aggregate_attendance_for_requested_month(): void
    {
        [$account, $tenant, $member, $groupSession, $privateSession] = $this->seedFixture();
        $year = 2026;
        $month = 7;

        $this->seedAppointment($member, $groupSession, AppointmentStatus::Confirmed, $account->id);
        $this->seedAppointment($member, $privateSession, AppointmentStatus::Absent, $account->id);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/stats/month?tenantId={$tenant->id}&year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.confirmedCount', 1)
            ->assertJsonPath('data.teamAbsent', 0)
            ->assertJsonPath('data.privateAbsent', 1);
    }

    public function test_cross_tenant_stats_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedFixture();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-stats']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/stats/year?tenantId={$otherTenant->id}&year=2026")
            ->assertNotFound();

        $this->getJson("/api/v1/member/stats/month?tenantId={$otherTenant->id}&year=2026&month=7")
            ->assertNotFound();
    }

    public function test_month_appointments_list_filters_by_course_kind_and_paginates(): void
    {
        [$account, $tenant, $member, $groupSession, $privateSession] = $this->seedFixture();
        $year = 2026;
        $month = 7;

        $groupAppointment = $this->seedAppointment($member, $groupSession, AppointmentStatus::Completed, $account->id);
        $this->seedAppointment($member, $privateSession, AppointmentStatus::Completed, $account->id);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/stats/month/appointments?tenantId={$tenant->id}&year={$year}&month={$month}&courseKind=group&page=1&perPage=20")
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.courseKind', 'group')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $groupAppointment->id)
            ->assertJsonPath('data.items.0.courseName', '团课')
            ->assertJsonPath('data.items.0.courseType', 'group')
            ->assertJsonPath('data.pagination.page', 1)
            ->assertJsonPath('data.pagination.hasNext', false);

        $this->getJson("/api/v1/member/stats/month/appointments?tenantId={$tenant->id}&year={$year}&month={$month}&courseKind=all")
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_cross_tenant_month_appointments_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedFixture();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-month-appts']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/stats/month/appointments?tenantId={$otherTenant->id}&year=2026&month=7")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member, 3: ScheduleSession, 4: ScheduleSession}
     */
    private function seedFixture(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Stats Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-STATS',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        $groupCourse = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $privateCourse = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'name' => '私教',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $room = Room::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'A 教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '教练',
            'status' => 'active',
        ]);
        $startsAt = Carbon::create(2026, 7, 15, 10, 0, 0);

        $groupSession = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 12,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        $privateSession = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $privateCourse->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt->copy()->addDay(),
            'ends_at' => $startsAt->copy()->addDay()->addHour(),
            'capacity' => 1,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Private,
            'version' => 1,
        ]);

        return [$account, $tenant, $member, $groupSession, $privateSession];
    }

    private function seedAppointment(
        Member $member,
        ScheduleSession $session,
        AppointmentStatus $status,
        int $accountId,
    ): Appointment {
        return Appointment::create([
            'tenant_id' => $member->tenant_id,
            'site_id' => $session->site_id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => $status,
            'command_key' => (string) Str::uuid(),
            'booked_by_account_id' => $accountId,
            'booked_at' => now(),
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
