<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'username' => 'catalog.admin',
            'name' => '目录超管',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
    }

    public function test_catalog_routes_require_admin_and_reject_mismatched_tenant_site_or_resource_scope(): void
    {
        [$tenantA, $siteA] = $this->scope('觅境一区', 'mijing-a', '滨江店', 'binjiang');
        [$tenantB, $siteB] = $this->scope('觅境二区', 'mijing-b', '朝阳店', 'chaoyang');

        $this->getJson($this->url($tenantA, $siteA, '/card-products'))->assertUnauthorized();

        $this->actingAsAdmin();
        $this->getJson($this->url($tenantA, $siteB, '/card-products'))->assertNotFound();
        $this->getJson($this->url($tenantB, $siteA, '/courses'))->assertNotFound();

        $product = $this->postJson($this->url($tenantA, $siteA, '/card-products'), [
            ...$this->cardPayload('跨作用域测试卡', 'stored_value'),
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
        $this->putJson($this->url($tenantB, $siteB, '/card-products/'.$product->json('data.id')), [
            ...$this->cardPayload('越权修改', 'stored_value'),
            'version' => 1,
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_admin_manages_three_card_types_and_courses_with_idempotent_audited_commands(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $coach = $this->staff($tenant, $site, '陈教练', 'COACH-001');

        $productIds = [];
        foreach ([
            ['name' => '两千元储值卡', 'type' => 'stored_value'],
            ['name' => '十次课卡', 'type' => 'count'],
            ['name' => '年度期限卡', 'type' => 'period'],
        ] as $definition) {
            $payload = [
                ...$this->cardPayload($definition['name'], $definition['type']),
                'commandKey' => (string) Str::uuid(),
                'reason' => '初始化三类卡项',
            ];
            $response = $this->postJson($this->url($tenant, $site, '/card-products'), $payload)
                ->assertCreated()
                ->assertJsonPath('data.cardType', $definition['type'])
                ->assertJsonPath('data.version', 1)
                ->assertJsonPath('data.allowedPaymentMethods.1', 'balance');
            $this->postJson($this->url($tenant, $site, '/card-products'), $payload)
                ->assertCreated()
                ->assertJsonPath('data.id', $response->json('data.id'));
            $productIds[$definition['type']] = (int) $response->json('data.id');
        }

        $this->getJson($this->url($tenant, $site, '/card-products?perPage=2&page=1'))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.lastPage', 2);
        $this->assertDatabaseCount('card_products', 3);
        $this->assertDatabaseHas('card_products', [
            'id' => $productIds['stored_value'],
            'created_by_staff_id' => null,
        ]);

        $storedValueId = $productIds['stored_value'];
        $updateKey = (string) Str::uuid();
        $updatePayload = [
            ...$this->cardPayload('两千元储值卡（旗舰）', 'stored_value'),
            'price' => '1980.00',
            'version' => 1,
            'commandKey' => $updateKey,
            'reason' => '调整销售价格',
        ];
        $this->putJson($this->url($tenant, $site, "/card-products/{$storedValueId}"), $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.priceCents', 198000)
            ->assertJsonPath('data.version', 2);
        $this->putJson($this->url($tenant, $site, "/card-products/{$storedValueId}"), $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->putJson($this->url($tenant, $site, "/card-products/{$storedValueId}"), [
            ...$this->cardPayload('不可变类型', 'count'),
            'version' => 2,
            'commandKey' => (string) Str::uuid(),
        ])->assertUnprocessable();

        $archiveKey = (string) Str::uuid();
        $archivePayload = ['version' => 2, 'reason' => '季节性下架卡项', 'commandKey' => $archiveKey];
        $this->postJson($this->url($tenant, $site, "/card-products/{$storedValueId}/archive"), $archivePayload)
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived')
            ->assertJsonPath('data.version', 3);
        $this->postJson($this->url($tenant, $site, "/card-products/{$storedValueId}/archive"), $archivePayload)
            ->assertOk()
            ->assertJsonPath('data.version', 3);
        $this->postJson($this->url($tenant, $site, "/card-products/{$storedValueId}/restore"), [
            'version' => 3,
            'reason' => '审批后恢复销售',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.version', 4);

        $courseKey = (string) Str::uuid();
        $coursePayload = [
            'name' => '燃脂搏击',
            'description' => '团体课程',
            'courseType' => 'group',
            'durationMinutes' => 60,
            'difficulty' => 2,
            'minCapacity' => 2,
            'maxCapacity' => 20,
            'coachStaffId' => $coach->id,
            'commandKey' => $courseKey,
            'reason' => '建立课程目录',
        ];
        $course = $this->postJson($this->url($tenant, $site, '/courses'), $coursePayload)
            ->assertCreated()
            ->assertJsonPath('data.coachStaffId', $coach->id)
            ->assertJsonPath('data.version', 1);
        $courseId = (int) $course->json('data.id');
        $this->postJson($this->url($tenant, $site, '/courses'), $coursePayload)
            ->assertCreated()
            ->assertJsonPath('data.id', $courseId);

        $this->putJson($this->url($tenant, $site, "/courses/{$courseId}"), [
            ...$coursePayload,
            'name' => '进阶燃脂搏击',
            'version' => 1,
            'commandKey' => (string) Str::uuid(),
            'reason' => '更新课程名称',
        ])->assertOk()
            ->assertJsonPath('data.name', '进阶燃脂搏击')
            ->assertJsonPath('data.version', 2);
        ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $courseId,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->subDay()->startOfHour(),
            'ends_at' => now()->subDay()->startOfHour()->addHour(),
            'capacity' => 20,
            'booked_count' => 0,
            'status' => 'completed',
            'session_kind' => 'group',
            'created_by_staff_id' => $coach->id,
        ]);
        $this->postJson($this->url($tenant, $site, "/courses/{$courseId}/archive"), [
            'version' => 2,
            'reason' => '课程临时下架',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived')
            ->assertJsonPath('data.version', 3);
        $this->postJson($this->url($tenant, $site, "/courses/{$courseId}/restore"), [
            'version' => 3,
            'reason' => '课程重新开放',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.version', 4);

        $this->getJson($this->url($tenant, $site, '/courses?query=进阶&perPage=1'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $courseId);
        $this->deleteJson($this->url($tenant, $site, "/card-products/{$storedValueId}"))->assertStatus(405);
        $this->deleteJson($this->url($tenant, $site, "/courses/{$courseId}"))->assertStatus(405);
        $this->assertDatabaseHas('card_products', ['id' => $storedValueId]);
        $this->assertDatabaseHas('courses', ['id' => $courseId]);

        $this->assertDatabaseHas('catalog_change_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'resource_type' => 'card_product',
            'action' => 'archive',
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'reason' => '季节性下架卡项',
        ]);
        $this->assertDatabaseHas('super_admin_audit_logs', [
            'super_admin_id' => $this->admin->id,
            'method' => 'POST',
            'status_code' => 201,
        ]);
    }

    public function test_course_coach_must_belong_to_explicit_site(): void
    {
        $this->actingAsAdmin();
        [$tenant, $siteA] = $this->scope('觅境运动', 'mijing', '滨江店', 'binjiang');
        $siteB = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '西湖店',
            'code' => 'xihu',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $otherSiteCoach = $this->staff($tenant, $siteB, '异店教练', 'COACH-XH');

        $this->postJson($this->url($tenant, $siteA, '/courses'), [
            'name' => '错误归属私教课',
            'courseType' => 'private',
            'durationMinutes' => 60,
            'coachStaffId' => $otherSiteCoach->id,
            'commandKey' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('courses', ['name' => '错误归属私教课']);
    }

    private function cardPayload(string $name, string $type): array
    {
        return [
            'name' => $name,
            'description' => '超管目录治理测试',
            'cardType' => $type,
            'price' => $type === 'period' ? '36500.00' : '2000.00',
            'faceValue' => $type === 'stored_value' ? '2000.00' : null,
            'initialCount' => $type === 'count' ? 10 : null,
            'validityDays' => 365,
            'validityMode' => 'days',
            'activationMode' => 'immediate',
            'saleStatus' => 'on_sale',
            'allowedPaymentMethods' => ['online', 'balance'],
        ];
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->admin, ['api', 'client:admin', 'admin:platform']);
    }

    private function scope(string $tenantName, string $tenantCode, string $siteName, string $siteCode): array
    {
        $tenant = Tenant::query()->create([
            'name' => $tenantName,
            'code' => $tenantCode,
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $siteName,
            'code' => $siteCode,
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);

        return [$tenant, $site];
    }

    private function staff(Tenant $tenant, Site $site, string $name, string $employeeNo): Staff
    {
        $account = Account::query()->create([
            'display_name' => $name,
            'mobile' => '139'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => 'active',
        ]);
        $staff = Staff::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => $employeeNo,
            'name' => $name,
            'status' => 'active',
            'joined_on' => now()->toDateString(),
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        return $staff;
    }

    private function url(Tenant $tenant, Site $site, string $suffix): string
    {
        return "/api/v1/admin/tenants/{$tenant->id}/sites/{$site->id}{$suffix}";
    }
}
