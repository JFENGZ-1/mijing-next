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
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_member_code_returns_todays_appointments(): void
    {
        [$staff, $site, $appointment, $member] = $this->seedTodayAppointment();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/check-in/resolve", [
            'code' => "memberNo:{$member->member_no}",
        ])
            ->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $appointment->id);
    }

    public function test_mark_check_in_completes_confirmed_appointment(): void
    {
        [$staff, $site, $appointment] = $this->seedTodayAppointment();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-check-in", [
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Completed->value,
        ]);
    }

    public function test_check_in_endpoints_require_permission(): void
    {
        [$staff, $site, $appointment] = $this->seedTodayAppointment(['schedule.session.read']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/check-in/resolve", ['code' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-check-in", [
            'commandKey' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: Appointment, 3: Member}
     */
    private function seedTodayAppointment(array $permissions = ['booking.fulfillment.check-in', 'crm.member.read']): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Checkin Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active', 'address' => '地址']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Checkin Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Checkin', 'code' => 'checkin', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        BookingPolicy::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => array_merge(BookingPolicyService::defaultPolicy(), [
                'group' => array_merge(BookingPolicyService::defaultPolicy()['group'], [
                    'signMinutesBeforeStart' => 120,
                ]),
            ]),
            'rules' => [],
        ]);

        $memberAccount = Account::create(['display_name' => '会员甲', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $memberAccount->id,
            'member_no' => 'M10001',
            'status' => 'active',
            'home_site_id' => $site->id,
        ]);
        $member->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'relationship_type' => 'home', 'status' => 'active']);

        $room = Room::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'name' => '教室A', 'status' => 'active']);
        $course = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => '团课',
            'course_type' => CourseType::Group,
            'catalog_status' => CourseCatalogStatus::Active,
            'duration_minutes' => 60,
        ]);
        // Keep the fixture on the current site-local calendar day. Using
        // `now()->addHour()` makes this test create tomorrow's session when
        // the suite runs after 23:00, while the endpoint correctly queries
        // today's appointments.
        $startsAt = now()->startOfMinute();
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'room_id' => $room->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'session_kind' => ScheduleSessionKind::Group,
            'status' => ScheduleSessionStatus::Scheduled,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 10,
        ]);
        $appointment = Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        return [$staff, $site, $appointment, $member];
    }
}
