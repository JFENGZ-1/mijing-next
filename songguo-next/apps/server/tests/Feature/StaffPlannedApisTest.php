<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\BookingPolicy;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Enums\MemberCardOrderStatus;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\PlatformSubscriptionPlan;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\SiteClosurePeriod;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPlannedApisTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_batch_unsuspend_and_change_course_preflight(): void
    {
        [$staff, $site, $room, $course, $targetCourse] = $this->scheduleContext(['schedule.batch.suspend', 'schedule.session.write', 'schedule.batch.copy']);
        $coach = $this->createCoach($site);
        $session = $this->createSession($site, $course, $coach, $room, suspended: true);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/batch-unsuspend", [
            'commandKey' => (string) Str::uuid(),
            'sessionIds' => [$session->id],
        ])->assertOk()->assertJsonPath('data.succeededSessionIds.0', $session->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/change-course-preflight?".http_build_query([
            'sessionIds' => [$session->id],
            'targetCourseId' => $targetCourse->id,
        ]))->assertOk()->assertJsonPath('data.canProceed', true);
    }

    public function test_schedule_session_unsuspend_and_extras(): void
    {
        [$staff, $site, $room, $course] = $this->scheduleContext(['schedule.session.write', 'schedule.session.read', 'schedule.batch.copy']);
        $coach = $this->createCoach($site);
        $session = $this->createSession($site, $course, $coach, $room, suspended: true);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/unsuspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-session-colors")
            ->assertOk()
            ->assertJsonStructure(['data' => ['palette']]);

        $this->putJson("/api/v1/staff/sites/{$site->id}/schedule-session-colors", [
            'palette' => [['key' => 'blue', 'label' => '蓝色', 'color' => '#1677ff']],
        ])->assertOk();

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-recurring-template?courseId={$course->id}")
            ->assertOk()
            ->assertJsonPath('data.courseId', $course->id);

        $from = now()->startOfWeek()->toDateString();
        $to = now()->endOfWeek()->toDateString();
        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-export-image", compact('from', 'to'))
            ->assertOk()
            ->assertJsonPath('data.placeholder', false)
            ->assertJsonStructure(['data' => ['imageUrl', 'width', 'height']]);

        $urlPath = (string) parse_url($response->json('data.imageUrl'), PHP_URL_PATH);
        $imagePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim($urlPath, '/')));
        $this->assertFileExists($imagePath);
        unlink($imagePath);
    }

    public function test_schedule_display_config_and_copy_preflight(): void
    {
        [$staff, $site, $room, $course] = $this->scheduleContext(['schedule.session.read', 'schedule.session.write', 'schedule.batch.copy']);
        $coach = $this->createCoach($site);
        $session = $this->createSession($site, $course, $coach, $room);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-display-config")
            ->assertOk()
            ->assertJsonStructure(['data' => ['displayTitle', 'copyHint', 'displayTags']]);

        $this->putJson("/api/v1/staff/sites/{$site->id}/schedule-display-config", [
            'displayTitle' => '课表',
            'copyHint' => '复制提示',
            'displayTags' => [['key' => 'hot', 'label' => '热门', 'color' => '#f00']],
        ])->assertOk()->assertJsonPath('data.displayTitle', '课表');

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/copy-preflight?".http_build_query([
            'sourceSessionIds' => [$session->id],
            'dayOffset' => 7,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['canProceed', 'sourceCount']]);
    }

    public function test_member_card_batch_and_reads_writes(): void
    {
        [$staff, $site, $member, $card] = $this->memberCardContext();

        $itemKey = (string) Str::uuid();
        $this->postJson("/api/v1/staff/sites/{$site->id}/member-cards/batch-freeze", [
            'commandKey' => (string) Str::uuid(),
            'items' => [['memberCardId' => $card->id, 'commandKey' => $itemKey, 'reason' => 'batch']],
        ])->assertCreated();

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/freeze-ledger-last")
            ->assertOk()
            ->assertJsonPath('data.memberCardId', $card->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/default-fee")->assertOk();
        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/dynamic-fields")->assertOk();
        $this->getJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/holiday-last")->assertOk();

        $this->patchJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/opening-type", ['openingType' => 'manual'])
            ->assertOk()
            ->assertJsonPath('data.openingType', 'manual');

        $this->patchJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/remark", ['remark' => '备注'])
            ->assertOk()
            ->assertJsonPath('data.remark', '备注');
    }

    public function test_card_product_course_catalog_and_points(): void
    {
        [$staff, $site] = $this->makeStaff([
            'card-product.catalog.read', 'export.job.write', 'course-catalog.read', 'course-catalog.write',
            'points.config.read', 'points.config.write',
        ]);
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => 'stored_value',
            'name' => '储值卡',
            'price' => 100,
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
            'activation_mode' => 'immediate',
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/{$product->id}/group-history")->assertOk();
        $this->getJson("/api/v1/staff/sites/{$site->id}/card-products/face-library")->assertOk();
        $this->postJson("/api/v1/staff/sites/{$site->id}/card-products/export-jobs")->assertCreated();

        $this->getJson("/api/v1/staff/sites/{$site->id}/course-tags")->assertOk();
        $this->putJson("/api/v1/staff/sites/{$site->id}/course-tags", [
            'tags' => [['key' => 'beginner', 'label' => '初级', 'color' => '#0f0']],
        ])->assertOk();
        $this->getJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}/delete-preflight")
            ->assertOk()
            ->assertJsonPath('data.canDelete', true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/points-config")->assertOk();
        $this->putJson("/api/v1/staff/sites/{$site->id}/points-config", [
            'pointsEnabled' => true,
            'policy' => ['earnPerVisit' => 2, 'earnPerPurchase' => 0, 'debitEnabled' => true],
        ])->assertOk()->assertJsonPath('data.pointsEnabled', true);
    }

    public function test_ledger_reconciliation_order_platform_calendar_identity(): void
    {
        [$staff, $site, $member] = $this->memberCardContext([
            'ledger.reconciliation.read', 'ledger.reconciliation.write', 'order.read',
            'report.rankings.read', 'platform.subscription.read',
        ]);
        $order = MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-'.fake()->unique()->numerify('####'),
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
        ]);

        $commandKey = (string) Str::uuid();
        $this->postJson("/api/v1/staff/sites/{$site->id}/ledger-reconciliation-jobs", [
            'commandKey' => $commandKey,
            'fromDate' => now()->subMonth()->toDateString(),
            'toDate' => now()->toDateString(),
            'dryRun' => true,
        ])->assertCreated()->assertJsonPath('data.commandKey', $commandKey);

        $this->getJson("/api/v1/staff/sites/{$site->id}/ledger-reconciliation-jobs")->assertOk();

        $this->postJson("/api/v1/staff/sites/{$site->id}/orders/{$order->id}/internal-notes", [
            'commandKey' => (string) Str::uuid(),
            'body' => '内部备注',
        ])->assertCreated();

        PlatformSubscriptionPlan::create([
            'code' => 'saas-1y',
            'label' => '1年',
            'duration_days' => 365,
            'price_cents' => 98000,
            'original_price_cents' => 128000,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $plan = PlatformSubscriptionPlan::first();

        $this->postJson('/api/v1/staff/platform/subscription/pay', [
            'commandKey' => (string) Str::uuid(),
            'planId' => $plan->id,
        ])->assertCreated()->assertJsonPath('data.demo', true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/reports/calendar/month-options")->assertOk();
        $this->getJson('/api/v1/staff/constants/common-data')->assertOk();
    }

    public function test_member_closure_status_and_public_warm_hint(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'member-planned']);
        $account = Account::create(['status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-PLANNED',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberCrmProfile::create(['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => '会员', 'version' => 1]);
        SiteClosurePeriod::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'begin_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'status' => 'active',
        ]);
        BookingPolicy::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => \App\Services\Booking\BookingPolicyService::defaultPolicy(),
            'rules' => [],
        ]);

        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/sites/{$site->id}/closure-status?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.isClosed', true);

        $this->getJson("/api/v1/public/booking/warm-hint/sites/{$site->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['hints']]);
    }

    /**
     * @param  list<string>  $extraPermissions
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard}
     */
    private function memberCardContext(array $extraPermissions = []): array
    {
        $permissions = array_merge([
            'member-card.read', 'member-card.freeze', 'member-card.balance.adjust',
            'member-card.validity.extend', 'member-card.issue',
        ], $extraPermissions);
        [$staff, $site] = $this->makeStaff($permissions);
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberCrmProfile::create(['tenant_id' => $site->tenant_id, 'member_id' => $member->id, 'name' => '会员', 'version' => 1]);
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => 'stored_value',
            'name' => '储值卡',
            'price' => 100,
            'face_value' => 100,
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
            'activation_mode' => 'immediate',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => 'stored_value',
            'card_no' => 'C'.fake()->unique()->numerify('########'),
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '储值卡', 'faceValue' => 100],
            'cached_balance' => 100,
            'issued_at' => now(),
        ]);

        return [$staff, $site, $member, $card];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: Room, 3: Course, 4?: Course}
     */
    private function scheduleContext(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '教室',
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课A',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $targetCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课B',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        return [$staff, $site, $room, $course, $targetCourse];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '管理员',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Planned', 'code' => 'planned', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'planned']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$tenant->id}"]);

        return [$staff, $site];
    }

    private function createCoach(Site $site): Staff
    {
        return Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '教练',
            'status' => 'active',
        ]);
    }

    private function createSession(
        Site $site,
        Course $course,
        Staff $coach,
        Room $room,
        bool $suspended = false,
    ): ScheduleSession {
        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 10,
            'status' => $suspended ? ScheduleSessionStatus::Suspended : ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
    }
}
