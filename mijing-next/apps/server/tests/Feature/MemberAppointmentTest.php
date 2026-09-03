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
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_books_group_session_with_stored_value_card_deducts_balance(): void
    {
        [$account, $tenant, $member, $site, $course, $coach, $room, , $session] = $this->seedBookingFixture();
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-BOOK-SV-001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '演示储值卡',
                'bookingRules' => ['defaultPrice' => '88.00'],
                'courseScopes' => [
                    ['scopeKind' => 'single', 'scopeKey' => (string) $course->id],
                ],
            ],
            'cached_balance' => 1000,
            'issued_at' => now(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'amount_delta' => 1000,
            'command_key' => (string) Str::uuid(),
            'reason' => 'Test issue',
            'occurred_at' => now(),
        ]);

        $commandKey = (string) Str::uuid();
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.memberCardId', $card->id);

        $this->assertSame('912.00', number_format((float) MemberCard::findOrFail($card->id)->cached_balance, 2, '.', ''));
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::BalanceAdjust->value,
            'direction' => EntitlementLedgerDirection::Debit->value,
            'amount_delta' => 88,
        ]);
    }

    public function test_stored_value_card_without_booking_price_is_rejected(): void
    {
        [$account, $tenant, $member, $site, $course, , , , $session] = $this->seedBookingFixture();
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-BOOK-SV-NO-PRICE',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '无扣费规则储值卡',
                'courseScopes' => [
                    ['scopeKind' => 'single', 'scopeKey' => (string) $course->id],
                ],
            ],
            'cached_balance' => 1000,
            'issued_at' => now(),
        ]);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_CARD_PRICE_UNKNOWN');
    }

    public function test_member_books_group_session_with_count_card_deducts_ledger_and_increments_booked_count(): void
    {
        [$account, $tenant, $member, $site, $course, $coach, $room, $card, $session] = $this->seedBookingFixture();
        $commandKey = (string) Str::uuid();

        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.sessionId', $session->id)
            ->assertJsonPath('data.memberCardId', $card->id);

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Confirmed->value,
            'command_key' => $commandKey,
        ]);
        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertSame(9, MemberCard::findOrFail($card->id)->cached_remaining_count);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::CountDeduct->value,
            'direction' => EntitlementLedgerDirection::Debit->value,
            'count_delta' => 1,
        ]);
    }

    public function test_create_is_idempotent_by_command_key(): void
    {
        [$account, $tenant, , , , , , $card, $session] = $this->seedBookingFixture();
        $commandKey = (string) Str::uuid();
        $this->actAsMember($account);

        $payload = [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => $commandKey,
        ];

        $first = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, $payload)->assertCreated();
        $second = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Appointment::query()->count());
        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
    }

    public function test_cancel_within_cutoff_refunds_count_and_decrements_booked_count(): void
    {
        [$account, $tenant, $member, $site, $course, $coach, $room, $card, $session] = $this->seedBookingFixture();
        $this->actAsMember($account);
        $bookKey = (string) Str::uuid();

        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => $bookKey,
        ])->json('data.id');

        $cancelKey = (string) Str::uuid();
        $this->postJson("/api/v1/member/booking/appointments/{$appointmentId}/cancel?tenantId={$tenant->id}", [
            'commandKey' => $cancelKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertSame(10, MemberCard::findOrFail($card->id)->cached_remaining_count);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Reversal->value,
            'command_key' => $cancelKey,
        ]);
    }

    // 会员自约受「开课前 N 分钟停止预约」限制（员工代约不受限，见 StaffAppointmentTest）
    public function test_booking_past_cutoff_is_rejected(): void
    {
        [$account, $tenant, , $site, $course, $coach, $room, $card] = $this->seedBookingFixture([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['bookingCutoffMinutesBeforeStart' => 120],
                'private' => [],
            ],
            'rules' => [],
        ]);
        $session = ScheduleSession::query()->firstOrFail();
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BOOKING_CUTOFF_PASSED');
    }

    public function test_cancel_outside_cutoff_is_rejected(): void
    {
        [$account, $tenant, , $site, $course, $coach, $room, $card] = $this->seedBookingFixture([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => [
                    'cancelCutoffMinutesBeforeStart' => 120,
                    'bookingCutoffMinutesBeforeStart' => 0,
                    'waitlistEnabled' => true,
                ],
                'private' => ['cancelCutoffMinutesBeforeStart' => 120],
            ],
            'rules' => [],
        ]);
        $session = ScheduleSession::query()->firstOrFail();
        $this->actAsMember($account);

        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->postJson("/api/v1/member/booking/appointments/{$appointmentId}/cancel?tenantId={$tenant->id}", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BOOKING_CANCEL_CUTOFF_PASSED');
    }

    public function test_full_session_waitlists_when_enabled(): void
    {
        [$account, $tenant, , $site, $course, $coach, $room, $card, $session] = $this->seedBookingFixture([
            'capacity' => 1,
            'booked_count' => 1,
        ]);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['waitlistEnabled' => true],
                'private' => [],
            ],
            'rules' => [],
        ]);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted')
            ->assertJsonPath('data.ledgerEntryId', null);

        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertSame(10, MemberCard::findOrFail($card->id)->cached_remaining_count);
    }

    public function test_cancel_waitlist_does_not_refund_or_change_booked_count(): void
    {
        [$account, $tenant, , $site, , , , $card, $session] = $this->seedBookingFixture([
            'capacity' => 1,
            'booked_count' => 1,
        ]);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['waitlistEnabled' => true],
                'private' => [],
            ],
            'rules' => [],
        ]);
        $this->actAsMember($account);

        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $ledgerBefore = EntitlementLedgerEntry::query()->where('member_card_id', $card->id)->count();

        $this->postJson("/api/v1/member/booking/appointments/{$appointmentId}/cancel?tenantId={$tenant->id}", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
        $this->assertSame(10, MemberCard::findOrFail($card->id)->cached_remaining_count);
        $this->assertSame($ledgerBefore, EntitlementLedgerEntry::query()->where('member_card_id', $card->id)->count());
    }

    public function test_full_session_rejected_when_waitlist_disabled(): void
    {
        [$account, $tenant, , , , , , $card, $session] = $this->seedBookingFixture([
            'capacity' => 1,
            'booked_count' => 1,
        ]);
        BookingPolicy::create([
            'tenant_id' => $session->tenant_id,
            'site_id' => $session->site_id,
            'version' => 1,
            'policy' => [
                'group' => ['waitlistEnabled' => false],
                'private' => [],
            ],
            'rules' => [],
        ]);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_SESSION_FULL');
    }

    public function test_insufficient_count_is_blocked(): void
    {
        [$account, $tenant, , , , , , $card, $session] = $this->seedBookingFixture();
        MemberCard::query()->whereKey($card->id)->update(['cached_remaining_count' => 0]);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_COUNT');
    }

    public function test_frozen_card_is_blocked(): void
    {
        [$account, $tenant, , , , , , $card, $session] = $this->seedBookingFixture();
        MemberCard::query()->whereKey($card->id)->update(['status' => MemberCardStatus::Frozen]);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_CARD_NOT_PAYABLE');
    }

    public function test_payable_cards_lists_eligible_wallet_cards(): void
    {
        [$account, $tenant, , , $course, , , $card, $session] = $this->seedBookingFixture();
        $this->actAsMember($account);

        $this->getJson("/api/v1/member/booking/sessions/{$session->id}/payable-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $card->id)
            ->assertJsonPath('data.items.0.remainingCount', 10);
    }

    public function test_cross_tenant_appointment_is_denied(): void
    {
        [$account, $tenant, , , , , , $card, $session] = $this->seedBookingFixture();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-appt']);
        $this->actAsMember($account);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$otherTenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $sessionOverrides
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site, 4: Course, 5: Staff, 6: Room, 7: MemberCard, 8: ScheduleSession}
     */
    private function seedBookingFixture(array $sessionOverrides = []): array
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
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-BOOK-001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '瑜伽次卡',
                'courseScopes' => [
                    ['scopeKind' => 'single', 'scopeKey' => (string) $course->id],
                ],
            ],
            'cached_remaining_count' => 10,
            'issued_at' => now(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'count_delta' => 10,
            'command_key' => (string) Str::uuid(),
            'reason' => 'Test issue',
            'occurred_at' => now(),
        ]);
        $startsAt = $sessionOverrides['starts_at'] ?? now()->addDay()->setTime(10, 0);
        $endsAt = $sessionOverrides['ends_at'] ?? now()->addDay()->setTime(11, 0);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $sessionOverrides['capacity'] ?? 12,
            'booked_count' => $sessionOverrides['booked_count'] ?? 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return [$account, $tenant, $member, $site, $course, $coach, $room, $card, $session];
    }

    public function test_member_lists_upcoming_appointments_without_staff_notes(): void
    {
        [$account, $tenant, $member, $site, , , , $card, $session] = $this->seedBookingFixture();
        $this->actAsMember($account);

        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id, [
            'sessionId' => $session->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        Appointment::query()->whereKey($appointmentId)->update(['staff_notes' => '仅员工可见']);

        $this->getJson('/api/v1/member/booking/appointments?tenantId='.$tenant->id.'&scope=upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $appointmentId)
            ->assertJsonPath('data.items.0.courseName', '瑜伽团课')
            ->assertJsonPath('data.items.0.coachName', '李教练')
            ->assertJsonPath('data.items.0.startsAt', $session->starts_at?->toIso8601String())
            ->assertJsonPath('data.items.0.endsAt', $session->ends_at?->toIso8601String())
            ->assertJsonMissingPath('data.items.0.staffNotes');
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
