<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
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

class StaffAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_lists_confirmed_appointments_for_session(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'schedule.session.read',
            'crm.member.read',
            'booking.appointment.create',
        ]);

        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $appointmentId)
            ->assertJsonPath('data.items.0.status', 'confirmed')
            ->assertJsonPath('data.items.0.memberId', $member->id);
    }

    public function test_staff_session_appointments_exclude_waitlisted_and_cancelled(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff(['schedule.session.read']);

        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Cancelled,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_staff_can_book_member_on_session(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff(['booking.appointment.create']);
        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.memberId', $member->id)
            ->assertJsonPath('data.createdByStaffId', $staff->id);

        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
    }

    public function test_staff_can_cancel_appointment(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'booking.appointment.create',
            'booking.appointment.cancel',
        ]);

        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointmentId}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, ScheduleSession::findOrFail($session->id)->booked_count);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site, $member, $card, $session] = $this->actAsBookingStaff(['schedule.session.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_appointment_is_isolated_by_site(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'booking.appointment.create',
            'booking.appointment.cancel',
        ]);

        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);
        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->postJson("/api/v1/staff/sites/{$branchSite->id}/appointments/{$appointmentId}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard, 4: ScheduleSession}
     */
    private function actAsBookingStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-STAFF-BOOK',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-STAFF-BOOK',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
            ],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 12,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return [$staff, $site, $member, $card, $session];
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Booking Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Booking Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Booking', 'code' => 'booking', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
