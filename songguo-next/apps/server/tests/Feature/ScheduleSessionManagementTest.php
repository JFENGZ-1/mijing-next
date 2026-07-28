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
 * 排课管理（对标原版 pagesCourse/index 课程管理弹窗）：
 * 删除拦截 / 停课级联退课 / 有预约仍可换课换老师改时间 / 课表背景色。
 */
class ScheduleSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_is_blocked_when_session_has_appointments(): void
    {
        $fixture = $this->seedFixture();
        $this->bookAsMember($fixture);

        $staff = $this->actAsScheduleStaff($fixture);

        $this->postJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$fixture['session']->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_CANCEL_HAS_APPOINTMENTS');

        $this->assertDatabaseHas('schedule_sessions', [
            'id' => $fixture['session']->id,
            'status' => ScheduleSessionStatus::Scheduled->value,
        ]);
    }

    public function test_suspend_cascades_cancel_appointments_and_refunds(): void
    {
        $fixture = $this->seedFixture();
        $appointmentId = $this->bookAsMember($fixture);

        $this->assertSame(9, (int) $fixture['card']->fresh()->cached_remaining_count);

        $this->actAsScheduleStaff($fixture);

        $this->postJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$fixture['session']->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'cancelled',
        ]);
        // 停课强制取消 → 卡次退回
        $this->assertSame(10, (int) $fixture['card']->fresh()->cached_remaining_count);
    }

    public function test_update_allows_course_coach_time_changes_with_bookings(): void
    {
        $fixture = $this->seedFixture();
        $this->bookAsMember($fixture);
        $this->actAsScheduleStaff($fixture);

        $otherCourse = Course::create([
            'tenant_id' => $fixture['tenant']->id,
            'site_id' => $fixture['site']->id,
            'course_type' => CourseType::Group,
            'name' => '普拉提团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $otherCoach = Staff::create([
            'tenant_id' => $fixture['tenant']->id,
            'account_id' => Account::create(['display_name' => 'Coach2', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '王教练',
            'status' => 'active',
        ]);

        $session = $fixture['session'];

        // 换课（原版：确认后仍可换）
        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 1,
            'courseId' => $otherCourse->id,
        ])->assertOk()->assertJsonPath('data.courseId', $otherCourse->id);

        // 换老师
        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 2,
            'coachStaffId' => $otherCoach->id,
        ])->assertOk()->assertJsonPath('data.coachStaffId', $otherCoach->id);

        // 修改时间
        $newStart = now()->addDay()->setTime(16, 0);
        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 3,
            'startsAt' => $newStart->toIso8601String(),
            'endsAt' => $newStart->copy()->addHour()->toIso8601String(),
        ])->assertOk();

        // 容量小于已预约人数 → 拦截
        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 4,
            'capacity' => 0,
        ])->assertStatus(422); // min:1 校验

        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 4,
            'sessionKind' => 'private',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'SCHEDULE_SESSION_UPDATE_BLOCKED');
    }

    public function test_display_color_session_override_and_course_fallback(): void
    {
        $fixture = $this->seedFixture();
        $this->actAsScheduleStaff($fixture);
        $session = $fixture['session'];

        // 课程级颜色 fallback
        $fixture['course']->update(['display_color' => '#0081ff']);
        $from = now()->startOfDay()->toIso8601String();
        $to = now()->addDays(3)->toIso8601String();
        $query = http_build_query(['from' => $from, 'to' => $to]);
        $this->getJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.0.displayColor', '#0081ff')
            ->assertJsonPath('data.items.0.courseDisplayColor', '#0081ff');

        // 排课级覆盖
        $this->patchJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions/{$session->id}", [
            'version' => 1,
            'displayColor' => '#e54d42',
        ])->assertOk()->assertJsonPath('data.displayColor', '#e54d42');

        $this->getJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-sessions?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.0.displayColor', '#e54d42')
            ->assertJsonPath('data.items.0.courseDisplayColor', '#0081ff');
    }

    public function test_session_colors_palette_returns_ten_defaults(): void
    {
        $fixture = $this->seedFixture();
        $this->actAsScheduleStaff($fixture);

        $this->getJson("/api/v1/staff/sites/{$fixture['site']->id}/schedule-session-colors")
            ->assertOk()
            ->assertJsonCount(10, 'data.palette');
    }

    // ================= fixture =================

    /**
     * @return array<string, mixed>
     */
    private function seedFixture(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-MGMT',
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
            'card_no' => 'MC-MGMT-001',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '管理次卡',
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
        $fixture['session'] = ScheduleSession::create([
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

        return $fixture;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function bookAsMember(array $fixture): int
    {
        Sanctum::actingAs($fixture['account'], ['api', 'client:member']);

        return $this->postJson('/api/v1/member/booking/appointments?tenantId='.$fixture['tenant']->id, [
            'sessionId' => $fixture['session']->id,
            'memberCardId' => $fixture['card']->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function actAsScheduleStaff(array $fixture): Staff
    {
        $role = Role::create(['tenant_id' => $fixture['tenant']->id, 'name' => '排课', 'code' => 'schedule', 'status' => 'active']);
        foreach (['schedule.session.read', 'schedule.session.write'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'schedule']);
            $role->permissions()->attach($permission->id);
        }

        $staff = Staff::create([
            'tenant_id' => $fixture['tenant']->id,
            'account_id' => Account::create(['display_name' => 'Scheduler', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '排课员',
            'status' => 'active',
        ]);
        $staff->sites()->attach($fixture['site']->id, ['tenant_id' => $fixture['tenant']->id, 'is_primary' => true]);
        $staff->roles()->attach($role->id, ['tenant_id' => $fixture['tenant']->id, 'site_id' => null]);

        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return $staff;
    }
}
