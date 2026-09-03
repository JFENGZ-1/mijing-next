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
use App\Models\Course;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardValueLot;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Booking\BookingPayableCardService;
use App\Services\Compensation\MemberCardValueLotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberCardStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_freeze_and_unfreeze_active_card(): void
    {
        [$staff, $site, , $card] = $this->actAsStaff(['member-card.freeze']);

        $this->postJson($this->freezePath($site, $card), [
            'reason' => '违规停卡',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Frozen->value)
            ->assertJsonPath('data.freezeState.reason', '违规停卡');

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::Freeze->value,
            'actor_staff_id' => $staff->id,
        ]);
        $this->assertSame(MemberCardStatus::Frozen, MemberCard::findOrFail($card->id)->status);

        $this->postJson($this->unfreezePath($site, $card), [
            'reason' => '解除停卡',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Active->value)
            ->assertJsonPath('data.freezeState', null);

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::FreezeLift->value,
        ]);
    }

    public function test_frozen_card_remains_visible_in_member_wallet_with_frozen_status(): void
    {
        [$account, $tenant, $member, $site] = $this->seedMember();
        $card = $this->createCard($site, $member, CardType::StoredValue, [
            'status' => MemberCardStatus::Frozen,
            'freeze_state' => ['frozenAt' => now()->toIso8601String(), 'reason' => '停卡'],
        ]);

        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/member-cards?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $card->id)
            ->assertJsonPath('data.0.status', MemberCardStatus::Frozen->value);
    }

    public function test_frozen_card_blocks_balance_debit_but_allows_credit(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.freeze', 'member-card.balance.adjust']);
        $card->update(['status' => MemberCardStatus::Frozen]);

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'debit',
            'amount' => 10,
            'reason' => '冻结扣款',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'FROZEN_CARD_DEBIT_BLOCKED');

        $this->postJson($this->balancePath($site, $card), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => '冻结补赠',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.cachedBalance', '1010.00');
    }

    public function test_holiday_extends_period_card_validity_on_end(): void
    {
        Carbon::setTestNow('2026-01-10 12:00:00');
        [, $site, , $card] = $this->actAsStaff(
            ['member-card.holiday.manage'],
            CardType::Period,
            [
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-02-01',
            ],
        );

        $startKey = (string) Str::uuid();
        $this->postJson($this->holidayStartPath($site, $card), [
            'beginDate' => '2026-01-10',
            'plannedEndDate' => '2026-01-20',
            'reason' => '会员请假',
            'commandKey' => $startKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', MemberCardStatus::Active->value)
            ->assertJsonPath('data.freezeState.holiday.startedAt', '2026-01-10');

        $endKey = (string) Str::uuid();
        $this->postJson($this->holidayEndPath($site, $card), [
            'endDate' => '2026-01-15',
            'reason' => '提前销假',
            'commandKey' => $endKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.validUntil', '2026-02-07')
            ->assertJsonPath('data.freezeState', null)
            ->assertJsonCount(2, 'data.ledgerEntryIds');

        $fresh = MemberCard::findOrFail($card->id);
        $this->assertSame('2026-02-07', $fresh->valid_until->toDateString());
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::HolidayCancel->value,
            'command_key' => $endKey,
        ]);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::ValidityChange->value,
            'valid_until_after' => '2026-02-07',
        ]);

        Carbon::setTestNow();
    }

    public function test_holiday_rejects_reserved_service_blocks_usage_and_shifts_paid_period_days_without_dilution(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        [$staff, $site, $member, $card] = $this->actAsStaff(
            ['member-card.holiday.manage'],
            CardType::Period,
            [
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
                'product_snapshot' => ['name' => '年卡', 'validityDays' => 365],
            ],
        );
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '请假测试课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => '2026-01-06 10:00:00',
            'ends_at' => '2026-01-06 11:00:00',
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        $appointment = Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);

        $holidayPayload = [
            'beginDate' => '2026-01-05',
            'plannedEndDate' => '2026-01-07',
            'reason' => '请假三天',
            'commandKey' => (string) Str::uuid(),
        ];
        $this->postJson($this->holidayStartPath($site, $card), $holidayPayload)->assertStatus(409);
        $this->assertNull($card->fresh()->freeze_state);

        $appointment->update(['status' => AppointmentStatus::Cancelled]);
        $this->postJson($this->holidayStartPath($site, $card), $holidayPayload)->assertCreated();
        $this->assertFalse(app(BookingPayableCardService::class)->isEligibleForSession($card->fresh(), $session));
        $this->postJson($this->holidayEndPath($site, $card), [
            'endDate' => '2026-01-07',
            'reason' => '按期销假',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.validUntil', '2027-01-03');

        MemberCardValueLot::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'source_type' => 'purchase',
            'payment_method' => 'online',
            'value_provenance' => 'actual',
            'paid_amount_cents' => 3_650_000,
            'entitlement_days' => 365,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'command_key' => 'holiday-value-'.Str::uuid(),
            'occurred_at' => now(),
        ]);
        $valueLots = app(MemberCardValueLotService::class);
        $afterHoliday = $valueLots->periodDayValue($card->fresh(), '2026-01-08');
        $extendedLastDay = $valueLots->periodDayValue($card->fresh(), '2027-01-03');
        $this->assertSame(4, $afterHoliday['dayOrdinal']);
        $this->assertSame(10_000, $afterHoliday['valueCents']);
        $this->assertSame(364, $extendedLastDay['dayOrdinal']);
        $this->assertSame(10_000, $extendedLastDay['valueCents']);

        Carbon::setTestNow();
    }

    public function test_staff_can_extend_validity_by_days_or_absolute_date(): void
    {
        [, $site, , $card] = $this->actAsStaff(
            ['member-card.validity.extend'],
            CardType::Period,
            [
                'valid_from' => '2026-03-01',
                'valid_until' => '2026-04-01',
            ],
        );

        $this->postJson($this->validityPath($site, $card), [
            'extendDays' => 10,
            'reason' => '补偿延期',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.validUntil', '2026-04-11');

        $this->postJson($this->validityPath($site, $card), [
            'validUntil' => '2026-05-01',
            'reason' => '调整到指定日期',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.validUntil', '2026-05-01');

        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $card->id,
            'entry_type' => EntitlementLedgerEntryType::ValidityChange->value,
            'valid_until_after' => '2026-05-01',
        ]);
    }

    public function test_state_commands_are_idempotent_on_command_key(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.freeze']);
        $commandKey = (string) Str::uuid();

        $first = $this->postJson($this->freezePath($site, $card), [
            'reason' => '重复冻结',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $second = $this->postJson($this->freezePath($site, $card), [
            'reason' => '重复冻结',
            'commandKey' => $commandKey,
        ])->assertOk();

        $this->assertSame($first->json('data.ledgerEntryIds'), $second->json('data.ledgerEntryIds'));
        $this->assertSame(1, EntitlementLedgerEntry::query()->where('command_key', $commandKey)->count());
    }

    public function test_state_command_key_is_bound_to_card_action_actor_and_payload(): void
    {
        [, $site, $member, $card] = $this->actAsStaff(['member-card.freeze']);
        $otherCard = $this->createCard($site, $member, CardType::StoredValue);
        $commandKey = (string) Str::uuid();

        $this->postJson($this->freezePath($site, $card), [
            'reason' => '命令指纹测试',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson($this->freezePath($site, $card), [
            'reason' => '篡改后的理由',
            'commandKey' => $commandKey,
        ])->assertStatus(409);

        $this->postJson($this->freezePath($site, $otherCard), [
            'reason' => '命令指纹测试',
            'commandKey' => $commandKey,
        ])->assertStatus(409);

        $this->assertSame(MemberCardStatus::Frozen, $card->fresh()->status);
        $this->assertSame(MemberCardStatus::Active, $otherCard->fresh()->status);
        $this->assertSame(1, EntitlementLedgerEntry::query()->where('command_key', $commandKey)->count());
    }

    public function test_invalid_state_transitions_return_stable_conflict_codes(): void
    {
        [, $site, , $card] = $this->actAsStaff([
            'member-card.freeze',
            'member-card.holiday.manage',
            'member-card.validity.extend',
        ]);

        $this->postJson($this->unfreezePath($site, $card), [
            'reason' => '未冻结',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_UNFREEZE_INVALID');

        $this->postJson($this->freezePath($site, $card), [
            'reason' => '首次冻结',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson($this->freezePath($site, $card), [
            'reason' => '重复冻结',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_FREEZE_INVALID');

        $this->postJson($this->holidayStartPath($site, $card), [
            'plannedEndDate' => '2026-02-01',
            'reason' => '冻结中请假',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_HOLIDAY_INVALID');

        $card->update(['status' => MemberCardStatus::Active, 'freeze_state' => null]);

        $this->postJson($this->holidayEndPath($site, $card), [
            'reason' => '无请假记录',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_CARD_HOLIDAY_INVALID');
    }

    public function test_staff_without_state_permissions_is_denied(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.read']);

        $payload = ['reason' => '无权限', 'commandKey' => (string) Str::uuid()];

        $this->postJson($this->freezePath($site, $card), $payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson($this->holidayStartPath($site, $card), [
            'plannedEndDate' => '2026-02-01',
            ...$payload,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson($this->validityPath($site, $card), [
            'extendDays' => 1,
            ...$payload,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_state_commands_enforce_tenant_and_site_isolation(): void
    {
        [, $site, , $card] = $this->actAsStaff(['member-card.freeze']);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-state']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherCard = MemberCard::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'member_id' => Member::create([
                'tenant_id' => $otherTenant->id,
                'member_no' => 'MEM-OTHER',
                'registration_site_id' => $otherSite->id,
                'home_site_id' => $otherSite->id,
                'status' => 'active',
            ])->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-OTHER',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '外馆卡'],
            'cached_balance' => 100,
            'issued_at' => now(),
        ]);

        $this->postJson($this->freezePath($site, $otherCard), [
            'reason' => '跨租户',
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    private function freezePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/freeze";
    }

    private function unfreezePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/unfreeze";
    }

    private function holidayStartPath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/holiday/start";
    }

    private function holidayEndPath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/holiday/end";
    }

    private function validityPath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/validity-extensions";
    }

    private function balancePath(Site $site, MemberCard $card): string
    {
        return "/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/balance-adjustments";
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard}
     */
    private function actAsStaff(
        array $permissions,
        CardType $cardType = CardType::StoredValue,
        array $cardOverrides = [],
    ): array {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $member = $this->createMember($site);
        $card = $this->createCard($site, $member, $cardType, $cardOverrides);

        return [$staff, $site, $member, $card];
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site}
     */
    private function seedMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Wallet Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-WALLET',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $tenant, $member, $site];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCard(Site $site, Member $member, CardType $cardType, array $overrides = []): MemberCard
    {
        $defaults = [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => $cardType,
            'card_no' => 'MC-'.fake()->unique()->numerify('#####'),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '测试卡'],
            'issued_at' => now(),
        ];

        if ($cardType === CardType::StoredValue) {
            $defaults['cached_balance'] = 1000;
        }

        return MemberCard::create([...$defaults, ...$overrides]);
    }

    private function createMember(Site $site): Member
    {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => '测试会员',
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Card State', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card State',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cards', 'code' => 'cards', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
