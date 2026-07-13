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
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffScheduleBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_copy_by_date_range_creates_sessions_on_target_dates(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.copy']);
        $coach = $this->createCoach($site);

        $sourceStart = now()->addWeek()->startOfWeek();
        $session = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            $sourceStart->copy()->setTime(10, 0),
            $sourceStart->copy()->setTime(11, 0),
        );

        $targetStart = $sourceStart->copy()->addWeek();
        $commandKey = (string) Str::uuid();

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", [
            'commandKey' => $commandKey,
            'sourceFrom' => $sourceStart->toIso8601String(),
            'sourceTo' => $sourceStart->copy()->addWeek()->toIso8601String(),
            'targetFrom' => $targetStart->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.commandKey', $commandKey)
            ->assertJsonCount(1, 'data.createdSessionIds');

        $createdId = $response->json('data.createdSessionIds.0');
        $created = ScheduleSession::findOrFail($createdId);

        $this->assertSame($session->course_id, $created->course_id);
        $this->assertSame(0, $created->booked_count);
        $this->assertSame(ScheduleSessionStatus::Scheduled, $created->status);
        $this->assertTrue($created->starts_at->equalTo($session->starts_at->copy()->addWeek()));
    }

    public function test_batch_copy_is_idempotent_by_command_key(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.copy']);
        $coach = $this->createCoach($site);

        $sourceStart = now()->addWeek()->startOfWeek();
        $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            $sourceStart->copy()->setTime(10, 0),
            $sourceStart->copy()->setTime(11, 0),
        );

        $commandKey = (string) Str::uuid();
        $payload = [
            'commandKey' => $commandKey,
            'sourceFrom' => $sourceStart->toIso8601String(),
            'sourceTo' => $sourceStart->copy()->addWeek()->toIso8601String(),
            'targetFrom' => $sourceStart->copy()->addWeeks(2)->toIso8601String(),
        ];

        $first = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", $payload)
            ->assertCreated()
            ->json('data.createdSessionIds');

        $second = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", $payload)
            ->assertOk()
            ->json('data.createdSessionIds');

        $this->assertSame($first, $second);
        $this->assertSame(1, ScheduleSession::query()->where('site_id', $site->id)->where('starts_at', '>=', $sourceStart->copy()->addWeeks(2))->count());
    }

    public function test_batch_copy_rejects_room_conflict(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.copy']);
        $coach = $this->createCoach($site);

        $sourceStart = now()->addWeek()->startOfWeek();
        $startsAt = $sourceStart->copy()->setTime(10, 0);
        $endsAt = $sourceStart->copy()->setTime(11, 0);
        $this->createSession($site, $groupCourse, $coach, $room, $startsAt, $endsAt);

        $targetStart = $sourceStart->copy()->addWeek();
        $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            $targetStart->copy()->setTime(10, 0),
            $targetStart->copy()->setTime(11, 0),
        );

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", [
            'commandKey' => (string) Str::uuid(),
            'sourceFrom' => $sourceStart->toIso8601String(),
            'sourceTo' => $sourceStart->copy()->addWeek()->toIso8601String(),
            'targetFrom' => $targetStart->toIso8601String(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_ROOM_CONFLICT');
    }

    public function test_batch_suspend_suspends_sessions_even_with_bookings(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.suspend']);
        $coach = $this->createCoach($site);

        $booked = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
            bookedCount: 1,
        );
        $empty = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(12, 0),
            now()->addDay()->setTime(13, 0),
        );
        $this->createAppointment($site, $booked);

        $commandKey = (string) Str::uuid();
        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-suspend", [
            'commandKey' => $commandKey,
            'sessionIds' => [$booked->id, $empty->id],
            'reason' => '场馆维护',
        ])
            ->assertOk()
            ->assertJsonPath('data.commandKey', $commandKey)
            ->assertJsonPath('data.succeededSessionIds', [$booked->id, $empty->id])
            ->assertJsonPath('data.failed', []);

        $this->assertSame(ScheduleSessionStatus::Suspended, ScheduleSession::findOrFail($booked->id)->status);
        $this->assertSame(ScheduleSessionStatus::Suspended, ScheduleSession::findOrFail($empty->id)->status);
    }

    public function test_batch_cancel_cancels_empty_sessions_and_reports_blocked(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.cancel']);
        $coach = $this->createCoach($site);

        $booked = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
        );
        $empty = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(12, 0),
            now()->addDay()->setTime(13, 0),
        );
        $this->createAppointment($site, $booked);

        $commandKey = (string) Str::uuid();
        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-cancel", [
            'commandKey' => $commandKey,
            'sessionIds' => [$booked->id, $empty->id],
            'reason' => '清理空课',
        ])
            ->assertOk()
            ->assertJsonPath('data.succeededSessionIds', [$empty->id])
            ->assertJsonPath('data.failed.0.sessionId', $booked->id)
            ->assertJsonPath('data.failed.0.code', 'SCHEDULE_BATCH_CANCEL_HAS_APPOINTMENTS');

        $this->assertSame(ScheduleSessionStatus::Scheduled, ScheduleSession::findOrFail($booked->id)->status);
        $this->assertSame(ScheduleSessionStatus::Cancelled, ScheduleSession::findOrFail($empty->id)->status);
    }

    public function test_batch_cancel_blocks_sessions_with_cancelled_appointment_history(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.batch.cancel']);
        $coach = $this->createCoach($site);
        $session = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
        );
        $this->createAppointment($site, $session, AppointmentStatus::Cancelled);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-cancel", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$session->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.succeededSessionIds', [])
            ->assertJsonPath('data.failed.0.code', 'SCHEDULE_BATCH_CANCEL_HAS_APPOINTMENTS');
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site, $room, $groupCourse] = $this->actAsBatchStaff(['schedule.session.read']);
        $coach = $this->createCoach($site);
        $session = $this->createSession(
            $site,
            $groupCourse,
            $coach,
            $room,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
        );

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", [
            'commandKey' => (string) Str::uuid(),
            'sourceSessionIds' => [$session->id],
            'dayOffset' => 7,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-suspend", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$session->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-cancel", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$session->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_batch_operations_are_isolated_by_tenant_and_site(): void
    {
        [$staff, $site, $room, $groupCourse] = $this->actAsBatchStaff([
            'schedule.batch.copy',
            'schedule.batch.suspend',
            'schedule.batch.cancel',
        ]);
        $coach = $this->createCoach($site);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherCourse = Course::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_type' => CourseType::Group,
            'name' => '外馆课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $otherCoach = Staff::create([
            'tenant_id' => $otherTenant->id,
            'account_id' => Account::create(['display_name' => 'Other Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '外馆教练',
            'status' => 'active',
        ]);
        $otherSession = ScheduleSession::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_id' => $otherCourse->id,
            'coach_staff_id' => $otherCoach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 10,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-suspend", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$otherSession->id],
        ])->assertNotFound();

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-cancel", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$otherSession->id],
        ])->assertNotFound();

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-copy", [
            'commandKey' => (string) Str::uuid(),
            'sourceSessionIds' => [$otherSession->id],
            'dayOffset' => 7,
        ])->assertNotFound();
    }

    private function actAsBatchStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
        ]);
        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'max_capacity' => 12,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        return [$staff, $site, $room, $groupCourse];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Batch Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Batch Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Schedule Batch', 'code' => 'schedule-batch', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'schedule']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createCoach(Site $site): Staff
    {
        return Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
    }

    private function createSession(
        Site $site,
        Course $course,
        Staff $coach,
        ?Room $room,
        $startsAt,
        $endsAt,
        int $bookedCount = 0,
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
            'booked_count' => $bookedCount,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }

    private function createAppointment(
        Site $site,
        ScheduleSession $session,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
    ): Appointment {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-BATCH-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => $status,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
    }
}
