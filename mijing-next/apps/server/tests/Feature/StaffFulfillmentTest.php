<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
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

class StaffFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_marks_confirmed_appointment_absent(): void
    {
        [$staff, $site, $appointment] = $this->seedConfirmedGroupAppointment();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-absent", [
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'absent')
            ->assertJsonPath('data.absentMarkedAt', fn ($value) => $value !== null);

        $this->assertDatabaseHas('appointment_events', [
            'tenant_id' => $staff->tenant_id,
            'appointment_id' => $appointment->id,
            'event_type' => 'absent_marked',
            'command_key' => $commandKey,
        ]);
    }

    public function test_mark_absent_is_blocked_for_waitlisted_appointment(): void
    {
        [$staff, $site, $appointment] = $this->seedConfirmedGroupAppointment();
        $appointment->update(['status' => AppointmentStatus::Waitlisted, 'ledger_entry_id' => null]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-absent", [
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);
    }

    public function test_absent_penalty_freezes_card_when_policy_enabled(): void
    {
        [$staff, $site, $appointment, $card] = $this->seedConfirmedGroupAppointment(returnCard: true);
        $this->enableAbsentPenalty($site, group: true);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-absent", [
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.penaltyLedgerEntryId', fn ($id) => $id !== null);

        $this->assertSame(MemberCardStatus::Frozen, MemberCard::findOrFail($card->id)->status);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Penalty->value,
        ]);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Freeze->value,
        ]);
    }

    public function test_staff_updates_staff_notes_and_records_audit_event(): void
    {
        [$staff, $site, $appointment] = $this->seedConfirmedGroupAppointment();
        $appointment->update(['staff_notes' => '旧备注']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->patchJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/staff-notes", [
            'staffNotes' => '教练备注：迟到未到',
        ])
            ->assertOk()
            ->assertJsonPath('data.staffNotes', '教练备注：迟到未到');

        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'event_type' => 'staff_note_updated',
        ]);

        $event = AppointmentEvent::query()
            ->where('appointment_id', $appointment->id)
            ->where('event_type', 'staff_note_updated')
            ->firstOrFail();

        $this->assertSame('旧备注', $event->payload['previousText']);
        $this->assertSame('教练备注：迟到未到', $event->payload['newText']);
    }

    public function test_private_reschedule_moves_appointment_and_adjusts_booked_count(): void
    {
        [$staff, $site, $appointment, $sourceSession, $targetSession, $card] = $this->seedPrivateRescheduleFixture();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $ledgerCountBefore = EntitlementLedgerEntry::query()->where('member_card_id', $card->id)->count();
        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/reschedule", [
            'sessionId' => $targetSession->id,
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.sessionId', $targetSession->id)
            ->assertJsonPath('data.rescheduledFromSessionId', $sourceSession->id);

        $this->assertSame(0, ScheduleSession::findOrFail($sourceSession->id)->booked_count);
        $this->assertSame(1, ScheduleSession::findOrFail($targetSession->id)->booked_count);
        $this->assertSame($ledgerCountBefore, EntitlementLedgerEntry::query()->where('member_card_id', $card->id)->count());
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'event_type' => 'rescheduled',
            'command_key' => $commandKey,
        ]);
    }

    public function test_reschedule_blocked_when_target_session_full(): void
    {
        [$staff, $site, $appointment, , $targetSession] = $this->seedPrivateRescheduleFixture();
        ScheduleSession::query()->whereKey($targetSession->id)->update(['booked_count' => 1, 'capacity' => 1]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/reschedule", [
            'sessionId' => $targetSession->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);
    }

    public function test_fulfillment_without_permission_is_denied(): void
    {
        [$staff, $site, $member, $card, $session] = $this->makeStaffFixture([]);
        $appointment = $this->createConfirmedAppointment($staff, $site, $member, $card, $session);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointment->id}/mark-absent", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_fulfillment_is_isolated_by_site(): void
    {
        [$staff, $site, $appointment] = $this->seedConfirmedGroupAppointment();
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$branchSite->id}/appointments/{$appointment->id}/mark-absent", [
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Appointment, 3?: MemberCard}
     */
    private function seedConfirmedGroupAppointment(bool $returnCard = false): array
    {
        [$staff, $site, $member, $card, $session] = $this->makeStaffFixture([
            'booking.fulfillment.absent',
            'booking.fulfillment.notes',
            'booking.appointment.reschedule',
        ]);

        $appointment = $this->createConfirmedAppointment($staff, $site, $member, $card, $session);

        if ($returnCard) {
            return [$staff, $site, $appointment, $card];
        }

        return [$staff, $site, $appointment];
    }

    private function createConfirmedAppointment(
        Staff $staff,
        Site $site,
        Member $member,
        MemberCard $card,
        ScheduleSession $session,
    ): Appointment {
        $appointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'ledger_entry_id' => null,
            'booked_at' => now(),
        ]);
        $session->update(['booked_count' => 1]);

        return $appointment;
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Appointment, 3: ScheduleSession, 4: ScheduleSession, 5: MemberCard}
     */
    private function seedPrivateRescheduleFixture(): array
    {
        [$staff, $site, $member, $card, $sourceSession] = $this->makeStaffFixture(
            ['booking.appointment.reschedule'],
            ScheduleSessionKind::Private,
        );

        $targetSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $sourceSession->course_id,
            'room_id' => $sourceSession->room_id,
            'coach_staff_id' => $sourceSession->coach_staff_id,
            'starts_at' => now()->addDays(2)->setTime(14, 0),
            'ends_at' => now()->addDays(2)->setTime(15, 0),
            'capacity' => 1,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Private,
            'version' => 1,
        ]);

        $appointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $sourceSession->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'ledger_entry_id' => null,
            'booked_at' => now(),
        ]);
        $sourceSession->update(['booked_count' => 1]);

        return [$staff, $site, $appointment, $sourceSession, $targetSession, $card];
    }

    private function enableAbsentPenalty(Site $site, bool $group): void
    {
        $policy = BookingPolicy::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        $payload = $policy?->policy ?? [
            'group' => ['absentPenaltyEnabled' => false],
            'private' => ['absentPenaltyEnabled' => false],
        ];

        if ($group) {
            $payload['group']['absentPenaltyEnabled'] = true;
        } else {
            $payload['private']['absentPenaltyEnabled'] = true;
        }

        BookingPolicy::updateOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id],
            ['version' => 1, 'policy' => $payload, 'rules' => []],
        );
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard, 4: ScheduleSession}
     */
    private function makeStaffFixture(array $permissions, ScheduleSessionKind $kind = ScheduleSessionKind::Group): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Fulfillment Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Fulfillment Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Fulfillment', 'code' => 'fulfillment', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-FULFILL',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $room = Room::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => '私教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $course = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => $kind === ScheduleSessionKind::Private ? CourseType::Private : CourseType::Group,
            'name' => $kind === ScheduleSessionKind::Private ? '私教课' : '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '王教练',
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-FULFILL',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
            ],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => $kind === ScheduleSessionKind::Private ? 1 : 12,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => $kind,
            'version' => 1,
        ]);

        return [$staff, $site, $member, $card, $session];
    }
}
