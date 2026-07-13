<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\BookingPolicy;
use App\Models\Course;
use App\Models\EntitlementLedgerEntry;
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

class StaffWaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_lists_waitlist_ordered_by_booked_at(): void
    {
        [$staff, $site, $session, $waitA, $waitB] = $this->seedWaitlistFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/waitlist")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $waitA->id)
            ->assertJsonPath('data.items.1.id', $waitB->id)
            ->assertJsonPath('data.items.0.status', 'waitlisted');
    }

    public function test_staff_promote_confirms_waitlisted_appointment_and_deducts_ledger(): void
    {
        [$staff, $site, $session, $waitA] = $this->seedWaitlistFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $card = MemberCard::query()->whereKey($waitA->member_card_id)->firstOrFail();
        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$waitA->id}/promote", [
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.ledgerEntryId', fn ($id) => $id !== null);

        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertSame(9, MemberCard::findOrFail($card->id)->cached_remaining_count);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::CountDeduct->value,
            'direction' => EntitlementLedgerDirection::Debit->value,
            'command_key' => $commandKey,
        ]);
    }

    public function test_promote_is_blocked_when_session_still_full(): void
    {
        [$staff, $site, $session, $waitA] = $this->seedWaitlistFixture();
        ScheduleSession::query()->whereKey($session->id)->update(['booked_count' => 1]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$waitA->id}/promote", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_SESSION_FULL');
    }

    public function test_promote_is_idempotent_by_command_key(): void
    {
        [$staff, $site, , $waitA] = $this->seedWaitlistFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $commandKey = (string) Str::uuid();

        $first = $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$waitA->id}/promote", [
            'commandKey' => $commandKey,
        ])->assertOk();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$waitA->id}/promote", [
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, EntitlementLedgerEntry::query()->where('command_key', $commandKey)->count());
    }

    public function test_promote_without_permission_is_denied(): void
    {
        [$staff, $site, , $waitA] = $this->seedWaitlistFixture(['schedule.session.read']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$waitA->id}/promote", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_waitlist_is_isolated_by_site(): void
    {
        [$staff, $site, $session] = $this->seedWaitlistFixture();
        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$branchSite->id}/schedule-sessions/{$session->id}/waitlist")
            ->assertNotFound();
    }

    public function test_confirmed_cancel_auto_promotes_first_waitlisted(): void
    {
        [$staff, $site, $session, $waitA, $waitB, $confirmed] = $this->seedWaitlistFixture(withConfirmed: true);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$confirmed->id}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();

        $this->assertSame(AppointmentStatus::Confirmed, Appointment::findOrFail($waitA->id)->status);
        $this->assertSame(AppointmentStatus::Waitlisted, Appointment::findOrFail($waitB->id)->status);
        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertNotNull(Appointment::findOrFail($waitA->id)->ledger_entry_id);
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: ScheduleSession, 3: Appointment, 4: Appointment, 5?: Appointment}
     */
    private function seedWaitlistFixture(array $permissions = [], bool $withConfirmed = false): array
    {
        $permissions = $permissions ?: [
            'schedule.session.read',
            'booking.waitlist.promote',
            'booking.appointment.create',
            'booking.appointment.cancel',
        ];

        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Waitlist Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Waitlist Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Waitlist', 'code' => 'waitlist', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

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
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 1,
            'booked_count' => $withConfirmed ? 1 : 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        BookingPolicy::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['waitlistEnabled' => true],
                'private' => [],
            ],
            'rules' => [],
        ]);

        $confirmed = null;
        if ($withConfirmed) {
            [$confirmedMember, $confirmedCard, $ledger] = $this->makeMemberWithCard($tenant, $site, $course, 'CONF', 10);
            $confirmed = Appointment::create([
                'tenant_id' => $tenant->id,
                'site_id' => $site->id,
                'session_id' => $session->id,
                'member_id' => $confirmedMember->id,
                'status' => AppointmentStatus::Confirmed,
                'command_key' => (string) Str::uuid(),
                'member_card_id' => $confirmedCard->id,
                'ledger_entry_id' => $ledger->id,
                'booked_at' => now()->subHour(),
            ]);
        }

        [$memberA, $cardA] = $this->makeMemberWithCard($tenant, $site, $course, 'WAIT-A', 10);
        [$memberB, $cardB] = $this->makeMemberWithCard($tenant, $site, $course, 'WAIT-B', 10);

        $waitA = Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $memberA->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $cardA->id,
            'booked_at' => now()->subMinutes(30),
        ]);
        $waitB = Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $memberB->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $cardB->id,
            'booked_at' => now()->subMinutes(10),
        ]);

        if ($withConfirmed) {
            return [$staff, $site, $session, $waitA, $waitB, $confirmed];
        }

        return [$staff, $site, $session, $waitA, $waitB];
    }

    /**
     * @return array{0: Member, 1: MemberCard, 2?: EntitlementLedgerEntry}
     */
    private function makeMemberWithCard(Tenant $tenant, Site $site, Course $course, string $suffix, int $count): array
    {
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-'.$suffix,
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-'.$suffix,
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
            ],
            'cached_remaining_count' => $count,
            'issued_at' => now(),
        ]);
        $ledger = EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'count_delta' => $count,
            'command_key' => (string) Str::uuid(),
            'reason' => 'Test issue',
            'occurred_at' => now(),
        ]);

        return [$member, $card, $ledger];
    }
}
