<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\BookingPolicy;
use App\Models\Course;
use App\Models\Member;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberBookingCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_scheduled_sessions_for_enrolled_tenant_site_on_date(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $date = now()->addDay()->toDateString();
        $scheduled = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(12, 0), now()->addDay()->setTime(13, 0), ScheduleSessionStatus::Suspended);
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(14, 0), now()->addDay()->setTime(15, 0), ScheduleSessionStatus::Cancelled);

        $this->actAsMember($account);

        $query = http_build_query(['tenantId' => $tenant->id, 'siteId' => $site->id, 'date' => $date]);
        $response = $this->getJson("/api/v1/member/booking/catalog?{$query}")
            ->assertOk()
            ->assertJsonPath('data.date', $date)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $scheduled->id)
            ->assertJsonPath('data.items.0.courseName', '瑜伽团课')
            ->assertJsonPath('data.items.0.coachName', '李教练')
            ->assertJsonPath('data.items.0.capacity', 12)
            ->assertJsonPath('data.items.0.bookedCount', 0)
            ->assertJsonPath('data.items.0.waitlistEnabled', true)
            ->assertJsonPath('data.items.0.bookable', true)
            ->assertJsonPath('data.items.0.memberAppointmentStatus', null)
            // 教练维度私教预约流需要以 coachStaffId 标识教练（会话详情本就公开该字段）
            ->assertJsonPath('data.items.0.coachStaffId', $scheduled->coach_staff_id);
    }

    public function test_member_catalog_excludes_suspended_and_cancelled_sessions(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $date = now()->addDay()->toDateString();
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), ScheduleSessionStatus::Suspended);
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(12, 0), now()->addDay()->setTime(13, 0), ScheduleSessionStatus::Cancelled);

        $this->actAsMember($account);

        $query = http_build_query(['tenantId' => $tenant->id, 'siteId' => $site->id, 'date' => $date]);
        $this->getJson("/api/v1/member/booking/catalog?{$query}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_policy_window_filters_future_dates_beyond_calendar_display_days(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        BookingPolicy::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['calendarDisplayDays' => 3, 'advanceBookingDays' => 3, 'waitlistEnabled' => true, 'showBookedCount' => true],
                'private' => ['advanceBookingDays' => 3],
            ],
            'rules' => [],
        ]);

        $tooFar = now()->addDays(4)->toDateString();
        $this->actAsMember($account);
        $query = http_build_query(['tenantId' => $tenant->id, 'siteId' => $site->id, 'date' => $tooFar]);

        $this->getJson("/api/v1/member/booking/catalog?{$query}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'BOOKING_DATE_OUT_OF_DISPLAY_WINDOW');
    }

    public function test_member_catalog_marks_sessions_beyond_advance_booking_days_as_not_bookable(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        BookingPolicy::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['calendarDisplayDays' => 7, 'advanceBookingDays' => 2, 'waitlistEnabled' => false, 'showBookedCount' => true],
                'private' => ['advanceBookingDays' => 14],
            ],
            'rules' => [],
        ]);

        $date = now()->addDays(3)->toDateString();
        $this->createSession($site, $groupCourse, $coach, $room, now()->addDays(3)->setTime(10, 0), now()->addDays(3)->setTime(11, 0));

        $this->actAsMember($account);
        $query = http_build_query(['tenantId' => $tenant->id, 'siteId' => $site->id, 'date' => $date]);

        $this->getJson("/api/v1/member/booking/catalog?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.0.bookable', false)
            ->assertJsonPath('data.items.0.waitlistEnabled', false);
    }

    public function test_cross_tenant_member_catalog_is_denied(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-catalog']);
        $date = now()->addDay()->toDateString();

        $this->actAsMember($account);
        $query = http_build_query(['tenantId' => $otherTenant->id, 'siteId' => $site->id, 'date' => $date]);

        $this->getJson("/api/v1/member/booking/catalog?{$query}")->assertNotFound();
    }

    public function test_member_catalog_marks_sessions_already_booked_by_member(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $date = now()->addDay()->toDateString();
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => fake()->uuid(),
            'booked_at' => now(),
        ]);

        $this->actAsMember($account);

        $query = http_build_query(['tenantId' => $tenant->id, 'siteId' => $site->id, 'date' => $date]);
        $this->getJson("/api/v1/member/booking/catalog?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $session->id)
            ->assertJsonPath('data.items.0.memberAppointmentStatus', 'confirmed')
            ->assertJsonPath('data.items.0.bookable', true);
    }

    public function test_member_session_detail_includes_member_appointment_status(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => fake()->uuid(),
            'booked_at' => now(),
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/booking/sessions/{$session->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.memberAppointmentStatus', 'waitlisted')
            ->assertJsonPath('data.bookable', true);
    }

    public function test_member_session_detail_returns_scheduled_session_within_policy(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/booking/sessions/{$session->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.roomName', 'A教室')
            ->assertJsonPath('data.coachStaffId', $coach->id)
            ->assertJsonPath('data.bookable', true)
            ->assertJsonPath('data.memberAppointmentStatus', null);
    }

    public function test_member_session_detail_hides_non_scheduled_sessions(): void
    {
        [$account, $tenant, $member, $site, $groupCourse, $coach, $room] = $this->seedBookingMember();
        $session = $this->createSession($site, $groupCourse, $coach, $room, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), ScheduleSessionStatus::Suspended);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/booking/sessions/{$session->id}?tenantId={$tenant->id}")->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site, 4: Course, 5: Staff, 6: Room}
     */
    private function seedBookingMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Booking Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-BOOK',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $room = Room::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $groupCourse = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);

        return [$account, $tenant, $member, $site, $groupCourse, $coach, $room];
    }

    private function createSession(
        Site $site,
        Course $course,
        Staff $coach,
        ?Room $room,
        $startsAt,
        $endsAt,
        ScheduleSessionStatus $status = ScheduleSessionStatus::Scheduled,
    ): ScheduleSession {
        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room?->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 12,
            'status' => $status,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
