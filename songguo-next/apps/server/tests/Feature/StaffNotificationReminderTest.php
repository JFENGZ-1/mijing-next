<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Notifications\NotificationReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffNotificationReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_anniversary_reminder_returns_members_within_window(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $today = now()->startOfDay();

        $due = $this->createMemberAtSite($site, 'Anniversary Due', joinedAt: $today->copy()->subYears(2));
        $this->createActiveCard($site, $due, 'MC-ANN-DUE');
        $later = $this->createMemberAtSite($site, 'Anniversary Later', joinedAt: $today->copy()->subYears(2)->addDays(30));
        $this->createActiveCard($site, $later, 'MC-ANN-LATER');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary?days=7")
            ->assertOk()
            ->assertJsonPath('data.thresholdDays', 7)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.memberId', $due->id)
            ->assertJsonPath('data.items.0.anniversaryOn', $today->toDateString());
    }

    public function test_no_class_reminder_returns_members_without_recent_appointments(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $inactive = $this->createMemberAtSite($site, 'No Class Member');
        $active = $this->createMemberAtSite($site, 'Recent Class Member');
        $this->createActiveCard($site, $inactive, 'MC-NOCLASS-INACTIVE');
        $this->createActiveCard($site, $active, 'MC-NOCLASS-ACTIVE');

        $session = $this->createSession($site);
        $this->createAppointment($site, $session, $active, AppointmentStatus::Completed);

        $oldSession = $this->createSession($site, now()->subDays(40));
        $this->createAppointment($site, $oldSession, $inactive, AppointmentStatus::Completed);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/no-class?days=30")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.memberId', $inactive->id);
    }

    public function test_no_class_uses_only_past_completed_fulfillment_and_database_pagination(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $member = $this->createMemberAtSite($site, 'Future Booking Member');
        $this->createActiveCard($site, $member, 'MC-FUTURE-BOOKING');

        $oldSession = $this->createSession($site, now()->subDays(45));
        $this->createAppointment($site, $oldSession, $member, AppointmentStatus::Completed);
        $futureSession = $this->createSession($site, now()->addDays(10));
        $this->createAppointment($site, $futureSession, $member, AppointmentStatus::Confirmed);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/no-class?days=30&page=1&perPage=1")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.perPage', 1)
            ->assertJsonPath('data.items.0.memberId', $member->id)
            ->assertJsonPath('data.items.0.lastClassDate', now()->subDays(45)->toDateString())
            ->assertJsonPath('data.items.0.daysSinceLastClass', 45);
    }

    public function test_birthday_reminder_returns_upcoming_birthdays(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $today = now()->startOfDay();

        $due = $this->createMemberAtSite($site, 'Birthday Due');
        $due->crmProfile->update(['birth_date' => $today->copy()->addDays(3)->subYears(25)->toDateString()]);
        $this->createActiveCard($site, $due, 'MC-BDAY-DUE');

        $later = $this->createMemberAtSite($site, 'Birthday Later');
        $later->crmProfile->update(['birth_date' => $today->copy()->addDays(20)->subYears(20)->toDateString()]);
        $this->createActiveCard($site, $later, 'MC-BDAY-LATER');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/birthdays?days=7")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.memberId', $due->id)
            ->assertJsonPath('data.items.0.daysUntilBirthday', 3);
    }

    public function test_recurring_reminders_paginate_in_global_occurrence_order_with_projected_last_class(): void
    {
        $this->travelTo(Carbon::create(2026, 12, 28, 9));
        [$staff, $site] = $this->actAsStaff(['notification.reminder.read']);

        $dates = [
            ['name' => 'December Due', 'date' => Carbon::create(2000, 12, 30)],
            ['name' => 'January First', 'date' => Carbon::create(2000, 1, 1)],
            ['name' => 'January Second', 'date' => Carbon::create(2000, 1, 2)],
        ];
        $members = [];
        foreach ($dates as $index => $row) {
            $member = $this->createMemberAtSite($site, $row['name'], joinedAt: $row['date']);
            $member->crmProfile->update(['birth_date' => $row['date']->toDateString()]);
            $this->createActiveCard($site, $member, 'MC-RECURRING-'.$index);
            $members[] = $member;
        }

        $completed = $this->createSession($site, now()->subDays(10));
        $this->createAppointment($site, $completed, $members[1], AppointmentStatus::Completed);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(NotificationReminderService::class)->anniversary($staff, $site, 7, 'valid', 1, 3);
        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
        DB::disableQueryLog();

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary?days=7&page=1&perPage=1")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.items.0.memberId', $members[0]->id);
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary?days=7&page=2&perPage=1")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberId', $members[1]->id)
            ->assertJsonPath('data.items.0.lastClassDate', now()->subDays(10)->toDateString());
        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/birthdays?days=7&page=3&perPage=1")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.items.0.memberId', $members[2]->id);
    }

    public function test_visitor_reminder_returns_recent_leads_without_cards(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $visitor = $this->createMemberAtSite($site, 'Recent Visitor', status: 'lead', joinedAt: now()->subDays(2));
        $withCard = $this->createMemberAtSite($site, 'Lead With Card', status: 'lead', joinedAt: now()->subDays(2));
        $this->createActiveCard($site, $withCard, 'MC-LEAD-CARD');
        $this->createMemberAtSite($site, 'Old Visitor', status: 'lead', joinedAt: now()->subDays(60));

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/visitors?days=30")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.memberId', $visitor->id)
            ->assertJsonPath('data.items.0.status', 'lead');
    }

    public function test_holiday_due_reminder_returns_cards_with_holiday_ending_soon(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $member = $this->createMemberAtSite($site, 'Holiday Member');
        $dueCard = $this->createActiveCard($site, $member, 'MC-HOLIDAY-DUE', [
            'freeze_state' => [
                'holiday' => [
                    'startedAt' => now()->subDays(5)->toDateString(),
                    'plannedEndAt' => now()->addDays(3)->toDateString(),
                    'startedByStaffId' => 1,
                ],
            ],
        ]);
        $this->createActiveCard($site, $member, 'MC-HOLIDAY-LATER', [
            'freeze_state' => [
                'holiday' => [
                    'startedAt' => now()->subDays(2)->toDateString(),
                    'plannedEndAt' => now()->addDays(20)->toDateString(),
                    'startedByStaffId' => 1,
                ],
            ],
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/holiday-due?days=7")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.memberCardId', $dueCard->id)
            ->assertJsonPath('data.items.0.holidayEndsAt', now()->addDays(3)->toDateString());
    }

    public function test_reminder_endpoints_mask_member_pii_without_crm_read(): void
    {
        [, $site] = $this->actAsStaff(['notification.reminder.read']);
        $today = now()->startOfDay();
        $member = $this->createMemberAtSite($site, 'Masked Anniversary', joinedAt: $today->copy()->subYears(1));
        $this->createActiveCard($site, $member, 'MC-MASKED-ANN');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary?days=7")
            ->assertOk()
            ->assertJsonPath('data.items.0.memberName', 'M*****************')
            ->assertJsonPath('data.items.0.memberAvatarUrl', null);
    }

    public function test_staff_without_reminder_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/no-class")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/birthdays")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/visitors")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/holiday-due")
            ->assertForbidden();
    }

    public function test_reminder_endpoints_are_scoped_to_assigned_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['notification.reminder.read']);
        $today = now()->startOfDay();
        $member = $this->createMemberAtSite($site, 'Scoped Anniversary', joinedAt: $today->copy()->subYears(1));
        $this->createActiveCard($site, $member, 'MC-SCOPED-ANN');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-reminder']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/reports/reminders/anniversary?days=7")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/reminders/anniversary?days=7")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'code' => fake()->unique()->slug(1),
        ]);
        $account = Account::create(['display_name' => 'Reminder Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Reminder Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Reminder', 'code' => 'reminder', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'notification']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(
        Site $site,
        string $name,
        string $status = 'active',
        $joinedAt = null,
    ): Member {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => $status,
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => $joinedAt ?? now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member->load('crmProfile');
    }

    private function createActiveCard(Site $site, Member $member, string $cardNo = 'MC-ACTIVE', array $extra = []): MemberCard
    {
        return MemberCard::create(array_merge([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_no' => $cardNo,
            'card_type' => CardType::Period,
            'status' => MemberCardStatus::Active,
            'member_visibility' => MemberCardVisibility::Visible,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'issued_at' => now(),
            'product_snapshot' => ['name' => 'Test Card'],
        ], $extra));
    }

    private function createSession(Site $site, $startsAt = null): ScheduleSession
    {
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Coach',
            'status' => 'active',
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => 'Yoga',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $coach->id,
        ]);

        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt ?? now()->subDay(),
            'ends_at' => ($startsAt ?? now()->subDay())->copy()->addHour(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }

    private function createAppointment(
        Site $site,
        ScheduleSession $session,
        Member $member,
        AppointmentStatus $status,
    ): Appointment {
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
