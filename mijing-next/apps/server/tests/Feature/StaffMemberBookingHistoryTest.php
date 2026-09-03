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

class StaffMemberBookingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_lists_member_upcoming_booking_history(): void
    {
        [$staff, $site, $member, $appointment] = $this->seedHistoryFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history?scope=upcoming")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $appointment->id)
            ->assertJsonPath('data.items.0.status', 'confirmed')
            ->assertJsonPath('data.items.0.courseName', '瑜伽团课')
            ->assertJsonPath('data.items.0.staffNotes', 'CRM note');
    }

    public function test_staff_lists_member_past_booking_history(): void
    {
        [$staff, $site, $member, $appointment] = $this->seedHistoryFixture();
        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history?scope=past")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'cancelled');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history?scope=upcoming")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_past_scope_includes_confirmed_appointments_after_session_start(): void
    {
        [$staff, $site, $member, $appointment, $session] = $this->seedHistoryFixture();
        $session->update([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(30),
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history?scope=past")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $appointment->id)
            ->assertJsonPath('data.items.0.status', 'confirmed');

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history?scope=upcoming")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_staff_upcoming_lists_coach_sessions(): void
    {
        [$staff, $site, , , $session] = $this->seedHistoryFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/upcoming")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.sessionId', $session->id)
            ->assertJsonPath('data.items.0.courseName', '瑜伽团课');
    }

    public function test_staff_upcoming_excludes_other_coach_sessions(): void
    {
        [$staff, $site] = $this->seedHistoryFixture(returnOnlyStaffSite: true);
        $otherCoach = Staff::create([
            'tenant_id' => $staff->tenant_id,
            'account_id' => Account::create(['display_name' => 'Other Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '王教练',
            'status' => 'active',
        ]);
        $course = Course::firstOrFail();
        $room = Room::firstOrFail();
        ScheduleSession::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $otherCoach->id,
            'starts_at' => now()->addDays(2)->setTime(14, 0),
            'ends_at' => now()->addDays(2)->setTime(15, 0),
            'capacity' => 8,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking/upcoming")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_member_history_is_isolated_by_tenant_and_site(): void
    {
        [$staff, $site, $member] = $this->seedHistoryFixture();
        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-history']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Main',
            'code' => 'other-main',
            'status' => 'active',
        ]);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'MEM-OTHER',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$branchSite->id}/members/{$member->id}/booking-history")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$otherMember->id}/booking-history")
            ->assertNotFound();
    }

    public function test_staff_without_history_permission_is_denied(): void
    {
        [$staff, $site, $member] = $this->seedHistoryFixture(permissions: ['crm.member.read']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}/booking-history")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: Appointment, 4: ScheduleSession}|array{0: Staff, 1: Site}
     */
    private function seedHistoryFixture(
        array $permissions = ['booking.member-history.list', 'booking.staff-upcoming.read'],
        bool $returnOnlyStaffSite = false,
    ): array {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'History Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'History', 'code' => 'history', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-HIST',
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
        $course = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 12,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-HIST',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '次卡'],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);
        $appointment = Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
            'staff_notes' => 'CRM note',
        ]);

        if ($returnOnlyStaffSite) {
            return [$staff, $site];
        }

        return [$staff, $site, $member, $appointment, $session];
    }
}
