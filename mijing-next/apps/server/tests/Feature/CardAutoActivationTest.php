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
use App\Models\CardProduct;
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
 * 会员卡自动开卡（原版开卡时间：首次使用 / 首次上课 / 购卡X天后）。
 */
class CardAutoActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_use_card_activates_on_member_booking(): void
    {
        $fixture = $this->seedFixture(activationMode: 'first-use', cardType: CardType::Period, validityDays: 30);

        $this->actAsMember($fixture['account']);
        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $card = MemberCard::findOrFail($fixture['card']->id);
        $this->assertSame(MemberCardStatus::Active, $card->status);
        $this->assertSame(now()->toDateString(), $card->valid_from?->toDateString());
        // validityDays is an inclusive service-day count: activation day is day 1.
        $this->assertSame(now()->addDays(29)->toDateString(), $card->valid_until?->toDateString());
    }

    public function test_first_class_card_books_while_pending_and_activates_on_check_in(): void
    {
        $fixture = $this->seedFixture(activationMode: 'first-class', cardType: CardType::Count, initialCount: 10);

        $this->actAsMember($fixture['account']);
        $appointmentId = $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');

        // 约课扣费成功，但卡仍待激活（约课不开卡）
        $card = MemberCard::findOrFail($fixture['card']->id);
        $this->assertSame(MemberCardStatus::PendingActivation, $card->status);
        $this->assertSame(9, (int) $card->cached_remaining_count);

        // 到签到窗口内签到 → 激活
        $fixture['session']->update(['starts_at' => now()->addMinutes(5), 'ends_at' => now()->addMinutes(65)]);
        $staff = $this->makeStaffWithPermissions($fixture, ['booking.fulfillment.check-in']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $this->postJson("/api/v1/staff/sites/{$fixture['site']->id}/appointments/{$appointmentId}/mark-check-in", [
            'commandKey' => (string) Str::uuid(),
        ])->assertSuccessful();

        $this->assertSame(MemberCardStatus::Active, MemberCard::findOrFail($fixture['card']->id)->status);
    }

    public function test_delayed_card_lazy_activates_on_booking_after_due(): void
    {
        // 购卡 2 天后自动开卡；发卡已 3 天，调度器未跑 → 预约时懒激活兜底
        $fixture = $this->seedFixture(
            activationMode: 'delayed',
            cardType: CardType::Period,
            validityDays: 30,
            activationDays: 2,
            issuedAt: now()->subDays(3),
        );

        $this->actAsMember($fixture['account']);
        $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame(MemberCardStatus::Active, MemberCard::findOrFail($fixture['card']->id)->status);
    }

    public function test_activate_due_command_activates_delayed_cards(): void
    {
        $due = $this->seedFixture(
            activationMode: 'delayed',
            cardType: CardType::Period,
            validityDays: 30,
            activationDays: 2,
            issuedAt: now()->subDays(5),
        );
        $notDue = $this->seedFixture(
            activationMode: 'delayed',
            cardType: CardType::Period,
            validityDays: 30,
            activationDays: 30,
            issuedAt: now()->subDay(),
        );

        $this->artisan('cards:activate-due')->assertSuccessful();

        $this->assertSame(MemberCardStatus::Active, MemberCard::findOrFail($due['card']->id)->status);
        $this->assertSame(MemberCardStatus::PendingActivation, MemberCard::findOrFail($notDue['card']->id)->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedFixture(
        string $activationMode,
        CardType $cardType,
        ?int $validityDays = null,
        ?int $initialCount = null,
        ?int $activationDays = null,
        $issuedAt = null,
    ): array {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(2)]);
        $account = Account::create(['display_name' => 'Auto Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => fake()->unique()->slug(2), 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
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

        $bookingRules = $activationDays ? ['activationDays' => $activationDays] : [];
        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => $cardType,
            'name' => '自动开卡测试卡',
            'price' => 100,
            'initial_count' => $initialCount,
            'validity_days' => $validityDays,
            'activation_mode' => $activationMode,
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
            'booking_rules' => $bookingRules,
        ]);

        $snapshot = [
            'name' => $product->name,
            'cardType' => $cardType->value,
            'validityDays' => $validityDays,
            'activationMode' => $activationMode,
            'bookingRules' => $bookingRules,
            'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
        ];

        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => $cardType,
            'card_no' => 'MC-AUTO-'.fake()->unique()->numerify('####'),
            'status' => MemberCardStatus::PendingActivation,
            'product_snapshot' => $snapshot,
            'cached_remaining_count' => $initialCount,
            'issued_at' => $issuedAt ?? now(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::Issue,
            'direction' => EntitlementLedgerDirection::Credit,
            'count_delta' => $initialCount,
            'command_key' => (string) Str::uuid(),
            'reason' => 'Test issue',
            'occurred_at' => now(),
        ]);

        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 12,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return compact('tenant', 'account', 'site', 'member', 'room', 'course', 'coach', 'card', 'session');
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<string>  $permissions
     */
    private function makeStaffWithPermissions(array $fixture, array $permissions): Staff
    {
        $role = Role::create(['tenant_id' => $fixture['tenant']->id, 'name' => '前台', 'code' => 'desk-'.fake()->unique()->numerify('###'), 'status' => 'active']);
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
