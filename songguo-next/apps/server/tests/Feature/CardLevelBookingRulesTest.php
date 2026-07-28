<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
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

/**
 * 卡级预约规则（原版会员卡「高级选项」）：
 * 仅约束会员自约/自取消；员工代约不受限。
 */
class CardLevelBookingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_day_limit_blocks_second_member_booking(): void
    {
        $fixture = $this->seedFixture(['bookingLimit' => ['perDay' => 1]]);
        $sessionTwo = $this->makeSession($fixture, now()->addDay()->setTime(14, 0), now()->addDay()->setTime(15, 0));

        $this->actAsMember($fixture['account']);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $sessionTwo->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_BOOKING_LIMIT_REACHED');
    }

    public function test_repeat_booking_deny_blocks_same_course(): void
    {
        $fixture = $this->seedFixture(['repeatBooking' => ['mode' => 'deny']]);
        $sessionTwo = $this->makeSession($fixture, now()->addDays(2)->setTime(10, 0), now()->addDays(2)->setTime(11, 0));

        $this->actAsMember($fixture['account']);

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $sessionTwo->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_REPEAT_BOOKING_DENIED');
    }

    public function test_time_range_restriction_blocks_out_of_window_session(): void
    {
        $fixture = $this->seedFixture(['timeRanges' => [['start' => '12:00', 'end' => '18:00']]]);

        $this->actAsMember($fixture['account']);

        // 默认 session 10:00 开课，不在 12:00-18:00 时段内
        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CARD_BOOKING_TIME_RESTRICTED');
    }

    public function test_absence_deduct_action_deducts_count_on_mark_absent(): void
    {
        // 原版「扣除」动作：次卡旷课达阈值扣 2 次
        $fixture = $this->seedFixture([
            'absencePenalty' => ['monthThreshold' => 1, 'action' => 'deduct', 'deductValue' => 2],
        ]);

        $this->actAsMember($fixture['account']);
        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');

        $staff = $this->makeStaffWithPermissions($fixture, [
            'schedule.session.read',
            'booking.fulfillment.absent',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $this->postJson("/api/v1/staff/sites/{$fixture['site']->id}/appointments/{$appointmentId}/mark-absent", [
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();

        // 10 次 - 预约扣 1 次 - 处罚扣 2 次 = 7 次
        $this->assertSame(7, (int) MemberCard::findOrFail($fixture['card']->id)->cached_remaining_count);
        $this->assertDatabaseHas('entitlement_ledger_entries', [
            'member_card_id' => $fixture['card']->id,
            'entry_type' => 'penalty',
            'count_delta' => 2,
            'reason' => '旷课处罚扣除',
        ]);
    }

    public function test_staff_assisted_booking_bypasses_card_rules(): void
    {
        $fixture = $this->seedFixture(['bookingLimit' => ['perDay' => 1]]);
        $sessionTwo = $this->makeSession($fixture, now()->addDay()->setTime(14, 0), now()->addDay()->setTime(15, 0));

        $staff = $this->makeStaffWithPermissions($fixture, [
            'schedule.session.read',
            'crm.member.read',
            'booking.appointment.create',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $siteId = $fixture['site']->id;

        $this->postJson("/api/v1/staff/sites/{$siteId}/schedule-sessions/{$fixture['session']->id}/appointments", [
            'memberId' => $fixture['member']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        // 卡规则 perDay=1，但员工代约不受限
        $this->postJson("/api/v1/staff/sites/{$siteId}/schedule-sessions/{$sessionTwo->id}/appointments", [
            'memberId' => $fixture['member']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $bookingRules
     * @return array<string, mixed>
     */
    private function seedFixture(array $bookingRules): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Rule Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-RULE',
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
            'card_no' => 'MC-RULE-001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '规则次卡',
                'bookingRules' => $bookingRules,
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

        $fixture = compact('tenant', 'account', 'site', 'member', 'room', 'course', 'coach', 'card');
        $fixture['session'] = $this->makeSession($fixture, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0));

        return $fixture;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function makeSession(array $fixture, $startsAt, $endsAt): ScheduleSession
    {
        return ScheduleSession::create([
            'tenant_id' => $fixture['tenant']->id,
            'site_id' => $fixture['site']->id,
            'course_id' => $fixture['course']->id,
            'room_id' => $fixture['room']->id,
            'coach_staff_id' => $fixture['coach']->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 12,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<string>  $permissions
     */
    private function makeStaffWithPermissions(array $fixture, array $permissions): Staff
    {
        $role = Role::create(['tenant_id' => $fixture['tenant']->id, 'name' => '前台', 'code' => 'desk', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }

        $staff = Staff::create([
            'tenant_id' => $fixture['tenant']->id,
            'account_id' => Account::create(['display_name' => 'Desk Staff', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '前台小王',
            'status' => 'active',
        ]);
        $staff->sites()->attach($fixture['site']->id, ['tenant_id' => $fixture['tenant']->id, 'is_primary' => true]);
        $staff->roles()->attach($role->id, ['tenant_id' => $fixture['tenant']->id, 'site_id' => null]);

        return $staff;
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
