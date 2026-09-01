<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\CommissionSettlementLine;
use App\Models\CompensationRole;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\MemberWallet;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\SuperAdminAuditLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCardConsumptionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'username' => 'finance.admin',
            'name' => '财务超管',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
    }

    public function test_all_governance_routes_require_admin_auth_and_explicit_matching_scope(): void
    {
        [$tenantA, $siteA] = $this->scope('觅境一区', 'mijing-a', '滨江店', 'binjiang');
        [$tenantB, $siteB] = $this->scope('觅境二区', 'mijing-b', '朝阳店', 'chaoyang');

        $this->getJson($this->url($tenantA, $siteA, '/compensation-roles'))->assertUnauthorized();

        $this->actingAsAdmin();
        $this->getJson($this->url($tenantA, $siteB, '/card-consumption/options'))->assertNotFound();
        $this->getJson($this->url($tenantB, $siteA, '/member-wallets'))->assertNotFound();
    }

    public function test_admin_manages_roles_assignments_payment_methods_and_versioned_rules_through_domain_services(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $staff = $this->staff($tenant, $site, '陈教练', 'COACH-001');
        $assistant = $this->staff($tenant, $site, '林助教', 'COACH-002');
        $course = $this->course($tenant, $site, $staff);
        $product = $this->cardProduct($tenant, $site, $staff);

        $roleCommandKey = (string) Str::uuid();
        $rolePayload = [
            'name' => '主教练',
            'roleType' => 'delivery',
            'commandKey' => $roleCommandKey,
        ];
        $roleResponse = $this->postJson($this->url($tenant, $site, '/compensation-roles'), $rolePayload)->assertCreated()
            ->assertJsonPath('data.roleType', 'delivery')
            ->assertJsonPath('data.version', 1);
        $roleId = (int) $roleResponse->json('data.id');
        $this->postJson($this->url($tenant, $site, '/compensation-roles'), $rolePayload)
            ->assertCreated()->assertJsonPath('data.id', $roleId);
        $this->assertSame(1, CompensationRole::query()->whereKey($roleId)->count());
        $this->assertDatabaseHas('compensation_roles', [
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'created_by_staff_id' => null,
        ]);
        $updateRoleKey = (string) Str::uuid();
        $updateRolePayload = [
            'name' => '主教练（A）',
            'roleType' => 'delivery',
            'version' => 1,
            'commandKey' => $updateRoleKey,
        ];
        $this->putJson($this->url($tenant, $site, "/compensation-roles/{$roleId}"), $updateRolePayload)->assertOk()
            ->assertJsonPath('data.name', '主教练（A）')
            ->assertJsonPath('data.version', 2);
        $this->putJson($this->url($tenant, $site, "/compensation-roles/{$roleId}"), $updateRolePayload)
            ->assertOk()->assertJsonPath('data.version', 2);

        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments'), [
            'roleId' => $roleId,
            'staffId' => $staff->id,
            'effectiveFrom' => now()->toDateString(),
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.staffId', $staff->id)
            ->assertJsonPath('data.roleId', $roleId);

        $assignmentCommandKey = (string) Str::uuid();
        $assistantAssignmentPayload = [
            'roleId' => $roleId,
            'staffId' => $assistant->id,
            'effectiveFrom' => now()->toDateString(),
            'commandKey' => $assignmentCommandKey,
        ];
        $assistantAssignment = $this->postJson($this->url($tenant, $site, '/compensation-role-assignments'), $assistantAssignmentPayload)->assertCreated()
            ->assertJsonPath('data.staffId', $assistant->id)
            ->assertJsonPath('data.roleId', $roleId);
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments'), $assistantAssignmentPayload)
            ->assertCreated()->assertJsonPath('data.id', $assistantAssignment->json('data.id'));

        $paymentMethodKey = (string) Str::uuid();
        $paymentMethodPayload = [
            'allowedPaymentMethods' => ['online', 'balance'],
            'version' => $product->refresh()->version,
            'reason' => '同时开放在线支付与会员钱包余额',
            'commandKey' => $paymentMethodKey,
        ];
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/payment-methods"), $paymentMethodPayload)->assertOk()
            ->assertJsonPath('data.allowedPaymentMethods.0', 'balance')
            ->assertJsonPath('data.allowedPaymentMethods.1', 'online')
            ->assertJsonPath('data.version', 2);
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/payment-methods"), $paymentMethodPayload)
            ->assertOk()->assertJsonPath('data.version', 2);

        $cardRuleCommandKey = (string) Str::uuid();
        $cardRulePayload = [
            'rules' => [[
                'courseId' => $course->id,
                'deductionKind' => 'count',
                'deductionCount' => 1,
            ]],
            'expectedVersion' => 0,
            'reason' => '为十次卡发布单节扣一次规则',
            'commandKey' => $cardRuleCommandKey,
        ];
        $rule = $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), $cardRulePayload)->assertOk()
            ->assertJsonPath('data.rules.0.deductionKind', 'count')
            ->assertJsonPath('data.rules.0.deductionCount', 1);
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), $cardRulePayload)
            ->assertOk()->assertJsonPath('data.rules.0.id', $rule->json('data.rules.0.id'));
        $this->assertDatabaseCount('card_product_course_rules', 1);

        $compensationRuleCommandKey = (string) Str::uuid();
        $compensationRulePayload = [
            'courseId' => $course->id,
            'sessionFeeCents' => 1000,
            'roleRates' => [['compensationRoleId' => $roleId, 'rateBps' => 1000]],
            'version' => 0,
            'reason' => '发布主教练课时费与十点提成',
            'commandKey' => $compensationRuleCommandKey,
        ];
        $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), $compensationRulePayload)->assertOk()
            ->assertJsonPath('data.sessionFeeCents', 1000)
            ->assertJsonPath('data.roleRates.0.rateBps', 1000);
        $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), $compensationRulePayload)
            ->assertOk()->assertJsonPath('data.version', 1);
        $this->assertDatabaseCount('course_compensation_rules', 1);
        $this->assertDatabaseHas('catalog_change_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'resource_type' => 'course_compensation_rule',
            'command_key' => $compensationRuleCommandKey,
            'reason' => '发布主教练课时费与十点提成',
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
        ]);

        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->startOfHour()->addHour(),
            'capacity' => 12,
            'booked_count' => 0,
            'status' => 'scheduled',
            'session_kind' => 'group',
            'created_by_staff_id' => $staff->id,
        ]);
        $deliveryCommandKey = (string) Str::uuid();
        $this->putJson($this->url($tenant, $site, "/sessions/{$session->id}/delivery-assignments"), [
            'assignments' => [
                ['staffId' => $staff->id, 'compensationRoleId' => $roleId, 'allocationBps' => 5000, 'isPrimary' => true],
                ['staffId' => $assistant->id, 'compensationRoleId' => $roleId, 'allocationBps' => 5000, 'isPrimary' => false],
            ],
            'expectedVersion' => $session->refresh()->version,
            'reason' => '双教练协同履约',
            'commandKey' => $deliveryCommandKey,
        ])->assertOk()
            ->assertJsonPath('data.items.0.staffId', $staff->id)
            ->assertJsonPath('data.items.1.staffId', $assistant->id)
            ->assertJsonPath('data.sessionVersion', 2);
        $this->assertDatabaseHas('schedule_session_assignment_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'schedule_session_id' => $session->id,
            'command_key' => $deliveryCommandKey,
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'reason' => '双教练协同履约',
        ]);
        $this->getJson($this->url($tenant, $site, "/delivery-assignments?sessionId={$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.allocationBps', 5000);
        $this->getJson($this->url($tenant, $site, '/card-consumption/options'))
            ->assertOk()
            ->assertJsonPath('data.sessions.0.id', $session->id)
            ->assertJsonPath('data.sessions.0.version', 2);

        $this->getJson($this->url($tenant, $site, '/card-course-rules'))
            ->assertOk()
            ->assertJsonPath('data.items.0.rules.0.id', $rule->json('data.rules.0.id'));
        $this->getJson($this->url($tenant, $site, '/course-compensation-rules'))
            ->assertOk()
            ->assertJsonPath('data.items.0.formulaVersion', 'course-rule-v1');
        $endDate = now()->addDays(7)->toDateString();
        $endCommandKey = (string) Str::uuid();
        $endPayload = [
            'version' => $assistantAssignment->json('data.version'),
            'effectiveUntil' => $endDate,
            'reason' => '协同场次结束后终止归属',
            'commandKey' => $endCommandKey,
        ];
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments/'.$assistantAssignment->json('data.id').'/end'), $endPayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.effectiveUntil', $endDate);
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments/'.$assistantAssignment->json('data.id').'/end'), $endPayload)
            ->assertOk()->assertJsonPath('data.effectiveUntil', $endDate);
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments/'.$assistantAssignment->json('data.id').'/end'), [
            ...$endPayload,
            'effectiveUntil' => now()->addDays(8)->toDateString(),
        ])->assertConflict();
        $this->assertDatabaseHas('staff_compensation_role_assignment_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'command_key' => $endCommandKey,
            'action' => 'end',
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'reason' => '协同场次结束后终止归属',
        ]);

        $archiveKey = (string) Str::uuid();
        $archivePayload = [
            'version' => 2,
            'reason' => '新业务架构停用旧角色',
            'commandKey' => $archiveKey,
        ];
        $this->postJson($this->url($tenant, $site, "/compensation-roles/{$roleId}/archive"), $archivePayload)
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.version', 3);
        $this->postJson($this->url($tenant, $site, "/compensation-roles/{$roleId}/archive"), $archivePayload)
            ->assertOk()->assertJsonPath('data.version', 3);
        $this->assertDatabaseHas('compensation_role_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'compensation_role_id' => $roleId,
            'command_key' => $archiveKey,
            'action' => 'archive',
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'reason' => '新业务架构停用旧角色',
            'result_version' => 3,
        ]);
        $this->deleteJson($this->url($tenant, $site, "/compensation-roles/{$roleId}"))->assertStatus(405);

        $this->assertTrue(SuperAdminAuditLog::query()
            ->where('super_admin_id', $this->admin->id)
            ->where('path', 'like', '%/course-compensation-rules')
            ->where('method', 'PUT')
            ->where('status_code', 200)
            ->exists());
    }

    public function test_course_compensation_rule_is_managed_as_one_versioned_course_document(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境课酬', 'mijing-compensation', '课酬店', 'compensation');
        $staff = $this->staff($tenant, $site, '张教练', 'COACH-RULE');
        $course = $this->course($tenant, $site, $staff);
        $deliveryRole = CompensationRole::query()->create([
            'tenant_id' => $tenant->id, 'site_id' => $site->id, 'code' => 'delivery-rule',
            'name' => '授课教练', 'role_type' => 'delivery', 'status' => 'active', 'version' => 1,
        ]);
        $shareRole = CompensationRole::query()->create([
            'tenant_id' => $tenant->id, 'site_id' => $site->id, 'code' => 'share-rule',
            'name' => '会籍顾问', 'role_type' => 'share', 'status' => 'active', 'version' => 1,
        ]);

        $emptyRoleRule = $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), [
            'courseId' => $course->id,
            'sessionFeeCents' => 1000,
            'roleRates' => [],
            'version' => 0,
            'reason' => '先只配置课时费',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonCount(0, 'data.roleRates');
        $this->getJson($this->url($tenant, $site, '/course-compensation-rules'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonCount(0, 'data.items.0.roleRates');

        $twoRoleRule = $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), [
            'id' => $emptyRoleRule->json('data.id'),
            'courseId' => $course->id,
            'sessionFeeCents' => 1200,
            'roleRates' => [
                ['compensationRoleId' => $deliveryRole->id, 'rateBps' => 1000],
                ['compensationRoleId' => $shareRole->id, 'rateBps' => 800],
            ],
            'version' => 1,
            'reason' => '增加 A/B 两类角色分成',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(2, 'data.roleRates');

        $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), [
            'id' => $twoRoleRule->json('data.id'),
            'courseId' => $course->id,
            'sessionFeeCents' => 1300,
            'roleRates' => [],
            'version' => 1,
            'reason' => '使用过期版本测试并发保护',
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->putJson($this->url($tenant, $site, '/course-compensation-rules'), [
            'id' => $twoRoleRule->json('data.id'),
            'courseId' => $course->id,
            'sessionFeeCents' => 1200,
            'roleRates' => [
                ['compensationRoleId' => $deliveryRole->id, 'rateBps' => 1000],
            ],
            'version' => 2,
            'reason' => '删除 B 角色费率但保留历史版本',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonCount(1, 'data.roleRates')
            ->assertJsonPath('data.roleRates.0.compensationRoleId', $deliveryRole->id);
        $this->assertDatabaseCount('course_compensation_rules', 3);
    }

    public function test_card_course_rules_support_audited_full_replace_and_removal(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境卡课', 'mijing-card-course', '卡课店', 'card-course');
        $staff = $this->staff($tenant, $site, '李教练', 'COACH-MATRIX');
        $product = $this->cardProduct($tenant, $site, $staff);
        $courseOne = $this->course($tenant, $site, $staff);
        $courseTwo = $this->course($tenant, $site, $staff);
        $courseTwo->update(['name' => '核心普拉提']);

        $first = $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), [
            'rules' => [
                ['courseId' => $courseOne->id, 'deductionKind' => 'count', 'deductionCount' => 1],
                ['courseId' => $courseTwo->id, 'deductionKind' => 'count', 'deductionCount' => 2],
            ],
            'expectedVersion' => 0,
            'reason' => '首次发布完整卡课矩阵',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.rulesVersion', 1)
            ->assertJsonCount(2, 'data.rules');

        $replaceKey = (string) Str::uuid();
        $replacePayload = [
            'rules' => [
                ['courseId' => $courseOne->id, 'deductionKind' => 'count', 'deductionCount' => 1],
            ],
            'expectedVersion' => 1,
            'reason' => '解除核心普拉提关联',
            'commandKey' => $replaceKey,
        ];
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), $replacePayload)
            ->assertOk()
            ->assertJsonPath('data.rulesVersion', 2)
            ->assertJsonCount(1, 'data.rules');
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), $replacePayload)
            ->assertOk()->assertJsonPath('data.rulesVersion', 2);
        $this->assertDatabaseHas('card_product_course_rules', [
            'card_product_id' => $product->id,
            'course_id' => $courseTwo->id,
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('catalog_change_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'resource_type' => 'card_product_course_rules',
            'command_key' => $replaceKey,
            'action' => 'replace',
            'reason' => '解除核心普拉提关联',
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'result_version' => 2,
        ]);
        $this->assertTrue(SuperAdminAuditLog::query()
            ->where('super_admin_id', $this->admin->id)
            ->where('path', 'like', '%/card-products/%/course-rules')
            ->where('method', 'PUT')
            ->where('status_code', 200)
            ->exists());

        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), [
            'rules' => [],
            'expectedVersion' => 2,
            'reason' => '解除该卡项的全部课程关联',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.rulesVersion', 3)
            ->assertJsonCount(0, 'data.rules');
        $this->getJson($this->url($tenant, $site, '/card-course-rules'))
            ->assertOk()
            ->assertJsonPath('data.items.0.rulesVersion', 3)
            ->assertJsonCount(0, 'data.items.0.rules');
        $this->putJson($this->url($tenant, $site, "/card-products/{$product->id}/course-rules"), [
            'rules' => [],
            'expectedVersion' => 2,
            'reason' => '不同命令使用过期矩阵版本',
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->assertNotNull($first->json('data.rules.0.id'));
    }

    public function test_member_card_share_assignment_and_wallet_adjustment_are_scoped_append_only_commands(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $staff = $this->staff($tenant, $site, '李顾问', 'SALES-001');
        [$member, $card] = $this->memberAndCard($tenant, $site, $staff);
        $role = CompensationRole::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'code' => 'consultant',
            'name' => '会籍顾问',
            'role_type' => 'share',
            'status' => 'active',
            'version' => 1,
        ]);
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments'), [
            'roleId' => $role->id,
            'staffId' => $staff->id,
            'effectiveFrom' => now()->toDateString(),
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();

        $shareAssignment = $this->postJson($this->url($tenant, $site, '/member-card-share-assignments'), [
            'memberCardId' => $card->id,
            'compensationRoleId' => $role->id,
            'staffId' => $staff->id,
            'allocationBps' => 10000,
            'effectiveFrom' => now()->toDateString(),
            'expectedVersion' => 0,
            'reason' => '建立会员卡默认会籍归属',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.items.0.roleId', $role->id)
            ->assertJsonPath('data.items.0.allocationBps', 10000);
        $this->postJson($this->url($tenant, $site, '/member-card-share-assignments/'.$shareAssignment->json('data.items.0.id').'/end'), [
            'expectedVersion' => $shareAssignment->json('data.items.0.scopeVersion'),
            'reason' => '结束当前会籍归属',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.ended', true);
        $this->getJson($this->url($tenant, $site, '/member-card-share-assignments?status=archived'))
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'archived');

        $secondStaff = $this->staff($tenant, $site, '周顾问', 'SALES-002');
        $this->postJson($this->url($tenant, $site, '/compensation-role-assignments'), [
            'roleId' => $role->id,
            'staffId' => $secondStaff->id,
            'effectiveFrom' => now()->toDateString(),
            'reason' => '加入联合会籍归属团队',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
        $batchKey = (string) Str::uuid();
        $batchPayload = [
            'assignments' => [
                [
                    'staffId' => $staff->id,
                    'compensationRoleId' => $role->id,
                    'allocationBps' => 5000,
                    'effectiveFrom' => now()->toDateString(),
                    'effectiveUntil' => null,
                ],
                [
                    'staffId' => $secondStaff->id,
                    'compensationRoleId' => $role->id,
                    'allocationBps' => 5000,
                    'effectiveFrom' => now()->toDateString(),
                    'effectiveUntil' => null,
                ],
            ],
            'expectedVersion' => 2,
            'reason' => '两位会籍顾问按贡献各分配百分之五十',
            'commandKey' => $batchKey,
        ];
        $this->putJson($this->url($tenant, $site, "/member-cards/{$card->id}/share-assignments"), $batchPayload)
            ->assertOk()
            ->assertJsonPath('data.scopeVersion', 3)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.allocationBps', 5000)
            ->assertJsonPath('data.items.1.allocationBps', 5000);
        $this->putJson($this->url($tenant, $site, "/member-cards/{$card->id}/share-assignments"), $batchPayload)
            ->assertOk()->assertJsonCount(2, 'data.items');
        $this->getJson($this->url($tenant, $site, "/member-card-share-assignments?memberCardId={$card->id}&status=active"))
            ->assertOk()->assertJsonPath('data.pagination.total', 2);
        $this->assertDatabaseHas('member_card_share_assignment_commands', [
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'command_key' => $batchKey,
            'actor_type' => 'super_admin',
            'actor_id' => $this->admin->id,
            'reason' => '两位会籍顾问按贡献各分配百分之五十',
        ]);

        $wallet = MemberWallet::query()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'balance_cents' => 0,
            'version' => 1,
        ]);
        $commandKey = (string) Str::uuid();
        $this->postJson($this->url($tenant, $site, "/member-wallets/{$member->id}/adjustments"), [
            'amountCents' => 25000,
            'version' => $wallet->version,
            'reason' => '线下转账审批单 FIN-001',
            'commandKey' => $commandKey,
        ])->assertCreated()
            ->assertJsonPath('data.wallet.balanceCents', 25000)
            ->assertJsonPath('data.created', true);
        $this->postJson($this->url($tenant, $site, "/member-wallets/{$member->id}/adjustments"), [
            'amountCents' => 25000,
            'version' => $wallet->version,
            'reason' => '线下转账审批单 FIN-001',
            'commandKey' => $commandKey,
        ])->assertOk()->assertJsonPath('data.created', false);

        $this->getJson($this->url($tenant, $site, "/member-wallets/{$member->id}/ledger"))
            ->assertOk()
            ->assertJsonPath('data.items.0.deltaCents', 25000)
            ->assertJsonPath('data.items.0.actorName', '财务超管');
        $this->deleteJson($this->url($tenant, $site, "/member-wallets/{$member->id}/ledger/1"))
            ->assertNotFound();

        [$otherTenant, $otherSite] = $this->scope('其他租户', 'other', '其他店', 'other-site');
        $this->postJson($this->url($otherTenant, $otherSite, "/member-wallets/{$member->id}/adjustments"), [
            'amountCents' => 100,
            'version' => 2,
            'reason' => '跨租户不应成功',
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_period_and_report_endpoints_are_read_only_or_audited_close_commands(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $staff = $this->staff($tenant, $site, '陈教练', 'COACH-001');
        $course = $this->course($tenant, $site, $staff);
        [$member, $card] = $this->memberAndCard($tenant, $site, $staff);
        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->startOfHour(),
            'ends_at' => now()->startOfHour()->addHour(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => 'scheduled',
            'session_kind' => 'group',
            'created_by_staff_id' => $staff->id,
        ]);
        $appointment = Appointment::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => 'completed',
            'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subDay(),
        ]);
        $event = ConsumptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'appointment_id' => $appointment->id,
            'session_id' => $session->id,
            'course_id' => $course->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'coach_staff_id' => $staff->id,
            'business_date' => now()->toDateString(),
            'card_type' => 'count',
            'deducted_count' => 1,
            'consumed_value_cents' => 20000,
            'value_provenance' => 'paid',
            'status' => 'final',
            'source' => 'manual',
            'command_key' => 'admin-test-consumption-'.$appointment->id,
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=delivery'))
            ->assertOk()
            ->assertJsonPath('data.items.0.subjectId', $staff->id)
            ->assertJsonPath('data.items.0.consumptionCount', 1)
            ->assertJsonPath('data.items.0.consumedAmountCents', 20000);
        $this->getJson($this->url($tenant, $site, '/consumption-events'))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $event->id)
            ->assertJsonPath('data.items.0.formulaInputs.consumedValueCents', 20000);
        $this->getJson($this->url($tenant, $site, '/period-settlement-days'))
            ->assertOk()->assertJsonCount(0, 'data.items');

        $reversalKey = (string) Str::uuid();
        $this->postJson($this->url($tenant, $site, "/consumption-events/{$event->id}/reverse"), [
            'reason' => '学员请假审批 REVERSE-001',
            'commandKey' => $reversalKey,
        ])->assertOk()
            ->assertJsonPath('data.status', 'reversed');
        $this->postJson($this->url($tenant, $site, "/consumption-events/{$event->id}/reverse"), [
            'reason' => '学员请假审批 REVERSE-001',
            'commandKey' => $reversalKey,
        ])->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->assertDatabaseHas('consumption_events', [
            'id' => $event->id,
            'status' => 'reversed',
            'reversal_reason' => '学员请假审批 REVERSE-001',
            'reversed_by_type' => 'super_admin',
            'reversed_by_id' => $this->admin->id,
        ]);

        $period = $this->postJson($this->url($tenant, $site, '/payroll-periods'), [
            'year' => 2026,
            'month' => 8,
            'reason' => '创建八月结算期',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open');
        $periodId = (int) $period->json('data.id');

        $this->getJson($this->url($tenant, $site, '/payroll-periods?year=2026'))
            ->assertOk()
            ->assertJsonPath('data.items.0.month', 8)
            ->assertJsonPath('data.items.0.settlementLineCount', 0)
            ->assertJsonPath('data.items.0.canClose', true)
            ->assertJsonPath('data.items.0.pendingCount', 0)
            ->assertJsonPath('data.items.0.blockedReason', null);
        $this->postJson($this->url($tenant, $site, "/payroll-periods/{$periodId}/close"), [
            'expectedVersion' => 1,
            'reason' => '财务复核完成 FIN-CLOSE-001',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $periodId,
            'create_reason' => '创建八月结算期',
            'close_reason' => '财务复核完成 FIN-CLOSE-001',
            'created_by_type' => 'super_admin',
            'created_by_id' => $this->admin->id,
            'closed_by_type' => 'super_admin',
            'closed_by_id' => $this->admin->id,
        ]);

        $futurePeriod = $this->postJson($this->url($tenant, $site, '/payroll-periods'), [
            'year' => 2099,
            'month' => 12,
            'reason' => '验证未结束期间不得关账',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.canClose', false)
            ->assertJsonPath('data.pendingCount', 0)
            ->assertJsonPath('data.blockedReason', 'PAYROLL_PERIOD_NOT_ENDED');
        $this->postJson($this->url($tenant, $site, '/payroll-periods/'.$futurePeriod->json('data.id').'/close'), [
            'expectedVersion' => 1,
            'reason' => '不应允许提前关账',
            'commandKey' => (string) Str::uuid(),
        ])->assertConflict();

        $this->deleteJson($this->url($tenant, $site, "/payroll-periods/{$periodId}"))->assertNotFound();
        $this->deleteJson($this->url($tenant, $site, '/consumption-events/1'))->assertNotFound();
        $this->assertDatabaseHas('super_admin_audit_logs', [
            'super_admin_id' => $this->admin->id,
            'method' => 'POST',
            'status_code' => 200,
        ]);
    }

    public function test_five_dimension_reports_use_all_recipients_without_session_fee_leakage(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $coachA = $this->staff($tenant, $site, 'A 教练', 'COACH-A');
        $coachB = $this->staff($tenant, $site, 'B 教练', 'COACH-B');
        $sales = $this->staff($tenant, $site, '会籍顾问', 'SALES-A');
        $course = $this->course($tenant, $site, $coachA);
        [$member, $card] = $this->memberAndCard($tenant, $site, $coachA);
        $deliveryRole = CompensationRole::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'code' => 'delivery-coach',
            'name' => '授课教练',
            'role_type' => 'delivery',
            'status' => 'active',
            'version' => 1,
        ]);
        $shareRole = CompensationRole::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'code' => 'share-sales',
            'name' => '销售分成',
            'role_type' => 'share',
            'status' => 'active',
            'version' => 1,
        ]);
        $shareRoleTwo = CompensationRole::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'code' => 'share-owner',
            'name' => '卡归属分成',
            'role_type' => 'share',
            'status' => 'active',
            'version' => 1,
        ]);
        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coachA->id,
            'starts_at' => now()->startOfHour(),
            'ends_at' => now()->startOfHour()->addHour(),
            'capacity' => 12,
            'booked_count' => 1,
            'status' => 'completed',
            'session_kind' => 'group',
            'version' => 2,
            'created_by_staff_id' => $coachA->id,
        ]);
        foreach ([[$coachA, true], [$coachB, false]] as [$coach, $primary]) {
            ScheduleSessionStaffAssignment::query()->create([
                'tenant_id' => $tenant->id,
                'site_id' => $site->id,
                'schedule_session_id' => $session->id,
                'staff_id' => $coach->id,
                'compensation_role_id' => $deliveryRole->id,
                'allocation_bps' => 5000,
                'is_primary' => $primary,
                'assignment_version' => 2,
            ]);
        }
        $appointment = Appointment::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => 'completed',
            'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subHour(),
        ]);
        $event = ConsumptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'appointment_id' => $appointment->id,
            'session_id' => $session->id,
            'course_id' => $course->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'coach_staff_id' => $coachA->id,
            'business_date' => now()->toDateString(),
            'card_type' => 'count',
            'deducted_count' => 1,
            'consumed_value_cents' => 10001,
            'value_provenance' => 'actual',
            'status' => 'final',
            'source' => 'check_in',
            'command_key' => 'five-dimension-event-'.$appointment->id,
            'metadata' => [
                'deliveryRecipients' => [
                    ['staffId' => $coachA->id, 'compensationRoleId' => $deliveryRole->id, 'allocationBps' => 5000],
                    ['staffId' => $coachB->id, 'compensationRoleId' => $deliveryRole->id, 'allocationBps' => 5000],
                ],
                'shareRecipients' => [
                    ['staffId' => $sales->id, 'compensationRoleId' => $shareRole->id, 'allocationBps' => 5000],
                    ['staffId' => $coachB->id, 'compensationRoleId' => $shareRole->id, 'allocationBps' => 5000],
                    ['staffId' => $sales->id, 'compensationRoleId' => $shareRoleTwo->id, 'allocationBps' => 10000],
                ],
                'valueLotAllocations' => [
                    ['valueLotId' => null, 'count' => 1, 'valueCents' => 10001],
                ],
            ],
            'occurred_at' => now(),
        ]);
        $lineDefinitions = [
            [$coachA, $deliveryRole, 'session_fee', 'accrual', 2000, null, 5000, 1000],
            [$coachA, $deliveryRole, 'consumption_commission', 'accrual', 10000, 1000, 5000, 500],
            [$coachB, $deliveryRole, 'session_fee', 'accrual', 2000, null, 5000, 1000],
            [$coachB, $deliveryRole, 'consumption_commission', 'accrual', 10000, 1000, 5000, 500],
            [$sales, $shareRole, 'consumption_commission', 'accrual', 10000, 1000, 5000, 500],
            [$sales, $shareRole, 'consumption_commission', 'adjustment', 10000, 1200, 5000, 100],
            [$coachB, $shareRole, 'consumption_commission', 'accrual', 10000, 1000, 5000, 500],
            [$sales, $shareRoleTwo, 'consumption_commission', 'accrual', 10000, 300, 10000, 300],
        ];
        foreach ($lineDefinitions as $index => [$recipient, $role, $component, $lineType, $base, $rate, $allocation, $amount]) {
            CommissionSettlementLine::query()->create([
                'tenant_id' => $tenant->id,
                'site_id' => $site->id,
                'consumption_event_id' => $event->id,
                'staff_id' => $recipient->id,
                'compensation_role_id' => $role->id,
                'component' => $component,
                'line_type' => $lineType,
                'base_value_cents' => $base,
                'rate_bps' => $rate,
                'allocation_bps' => $allocation,
                'amount_cents' => $amount,
                'command_key' => 'five-dimension-line-'.$index,
                'metadata' => ['postCloseAdjustment' => $lineType === 'adjustment'],
                'occurred_at' => now()->addSeconds($index),
            ]);
        }

        $delivery = $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=delivery'))
            ->assertOk()->json('data.items');
        $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=coach'))
            ->assertUnprocessable();
        $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=delivery&status=adjusted'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);
        $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=delivery&status=reversed'))
            ->assertUnprocessable();
        $deliveryByStaff = collect($delivery)->keyBy('subjectId');
        $deliveryExtraToA = strcmp("{$coachA->id}:{$deliveryRole->id}", "{$coachB->id}:{$deliveryRole->id}") < 0;
        $this->assertSame($deliveryExtraToA ? 5001 : 5000, $deliveryByStaff[$coachA->id]['consumedAmountCents']);
        $this->assertSame($deliveryExtraToA ? 5000 : 5001, $deliveryByStaff[$coachB->id]['consumedAmountCents']);
        $this->assertSame(10001, $deliveryByStaff[$coachA->id]['consumedAmountCents'] + $deliveryByStaff[$coachB->id]['consumedAmountCents']);
        $this->assertSame(1000, $deliveryByStaff[$coachA->id]['sessionFeeCents']);

        $shareRows = collect($this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=share'))
            ->assertOk()->json('data.items'))->keyBy('subjectId');
        $this->assertSame(1, $shareRows[$sales->id]['consumptionCount']);
        // Each B role is a separate attribution pool. The same employee can
        // receive value from multiple roles and must not be capped at 100%.
        $shareExtraToSales = strcmp("{$sales->id}:{$shareRole->id}", "{$coachB->id}:{$shareRole->id}") < 0;
        $this->assertSame($shareExtraToSales ? 15002 : 15001, $shareRows[$sales->id]['consumedAmountCents']);
        $this->assertSame(900, $shareRows[$sales->id]['commissionCents']);
        $this->assertSame($shareExtraToSales ? 5000 : 5001, $shareRows[$coachB->id]['consumedAmountCents']);
        $this->assertSame(20002, $shareRows[$sales->id]['consumedAmountCents'] + $shareRows[$coachB->id]['consumedAmountCents']);
        $this->assertSame(500, $shareRows[$coachB->id]['commissionCents']);

        foreach (['member' => $member->id, 'card' => $card->id] as $dimension => $subjectId) {
            $row = $this->getJson($this->url($tenant, $site, "/consumption-reports?dimension={$dimension}"))
                ->assertOk()->json('data.items.0');
            $this->assertSame($subjectId, $row['subjectId']);
            $this->assertSame(0, $row['sessionFeeCents']);
            $this->assertSame(2400, $row['commissionCents']);
            $this->assertSame(2400, $row['totalCompensationCents']);
        }
        $courseRow = $this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=course'))
            ->assertOk()->json('data.items.0');
        $this->assertSame($course->id, $courseRow['subjectId']);
        $this->assertSame(2000, $courseRow['sessionFeeCents']);
        $this->assertSame(2400, $courseRow['commissionCents']);
        $this->assertSame(4400, $courseRow['totalCompensationCents']);

        $eventPayload = $this->getJson($this->url($tenant, $site, '/consumption-events'))
            ->assertOk()
            ->assertJsonCount(2, 'data.items.0.deliveryRecipients')
            ->assertJsonCount(3, 'data.items.0.shareRecipients')
            ->assertJsonCount(1, 'data.items.0.valueLotAllocations')
            ->assertJsonCount(8, 'data.items.0.commissionLines')
            ->json('data.items.0');
        $this->assertSame('A 教练', $eventPayload['deliveryRecipients'][0]['staffName']);
        $this->assertSame(600, $eventPayload['commissionLines'][5]['netCents']);
        $this->assertTrue($eventPayload['commissionLines'][5]['postCloseAdjustment']);
        $this->assertSame('adjusted', $eventPayload['status']);
        $this->getJson($this->url($tenant, $site, '/consumption-events?status=adjusted'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.status', 'adjusted');
        $this->getJson($this->url($tenant, $site, '/consumption-events?status=unknown'))
            ->assertUnprocessable();
    }

    public function test_post_close_reversal_does_not_rewrite_closed_payroll_totals(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $staff = $this->staff($tenant, $site, '陈教练', 'COACH-001');
        $course = $this->course($tenant, $site, $staff);
        [$member, $card] = $this->memberAndCard($tenant, $site, $staff);
        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => '2026-08-15 10:00:00',
            'ends_at' => '2026-08-15 11:00:00',
            'capacity' => 10,
            'booked_count' => 1,
            'status' => 'completed',
            'session_kind' => 'group',
            'created_by_staff_id' => $staff->id,
        ]);
        $appointment = Appointment::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => 'completed',
            'command_key' => (string) Str::uuid(),
            'booked_at' => '2026-08-15 09:00:00',
        ]);
        $event = ConsumptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'appointment_id' => $appointment->id,
            'session_id' => $session->id,
            'course_id' => $course->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'coach_staff_id' => $staff->id,
            'business_date' => '2026-08-15',
            'card_type' => 'count',
            'deducted_count' => 1,
            'consumed_value_cents' => 10000,
            'value_provenance' => 'actual',
            'status' => 'final',
            'source' => 'check_in',
            'command_key' => 'closed-period-event-'.$appointment->id,
            'metadata' => ['deliveryRecipients' => [['staffId' => $staff->id, 'allocationBps' => 10000]]],
            'occurred_at' => '2026-08-15 11:00:00',
        ]);
        $period = $this->postJson($this->url($tenant, $site, '/payroll-periods'), [
            'year' => 2026,
            'month' => 8,
            'reason' => '创建八月工资期',
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
        $periodId = (int) $period->json('data.id');
        CommissionSettlementLine::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'consumption_event_id' => $event->id,
            'staff_id' => $staff->id,
            'payroll_period_id' => $periodId,
            'component' => 'consumption_commission',
            'line_type' => 'accrual',
            'base_value_cents' => 10000,
            'rate_bps' => 1000,
            'allocation_bps' => 10000,
            'amount_cents' => 1000,
            'command_key' => 'closed-period-accrual',
            'metadata' => ['postCloseAdjustment' => false],
            'occurred_at' => '2026-08-15 11:00:00',
        ]);
        $this->postJson($this->url($tenant, $site, "/payroll-periods/{$periodId}/close"), [
            'expectedVersion' => 1,
            'reason' => '八月工资审批完成',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();
        $before = $this->getJson($this->url($tenant, $site, '/payroll-periods?year=2026'))
            ->assertOk()->json('data.items.0');
        $this->assertSame(1000, $before['compensationCents']);
        $this->assertSame(1, $before['settlementLineCount']);

        $this->postJson($this->url($tenant, $site, "/consumption-events/{$event->id}/reverse"), [
            'reason' => '关账后审批冲正 REVERSE-CLOSED',
            'commandKey' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.status', 'reversed');

        $after = $this->getJson($this->url($tenant, $site, '/payroll-periods?year=2026'))
            ->assertOk()->json('data.items.0');
        $this->assertSame(1000, $after['compensationCents']);
        $this->assertSame(1, $after['settlementLineCount']);
        $adjustment = CommissionSettlementLine::query()
            ->where('consumption_event_id', $event->id)
            ->where('line_type', 'reversal')
            ->firstOrFail();
        $this->assertSame(-1000, $adjustment->amount_cents);
        $this->assertNull($adjustment->payroll_period_id);
        $this->assertTrue((bool) $adjustment->metadata['postCloseAdjustment']);
    }

    public function test_delivery_report_uses_event_snapshot_without_assignment_or_commission_lines(): void
    {
        $this->actingAsAdmin();
        [$tenant, $site] = $this->scope('觅境运动', 'mijing', '旗舰店', 'flagship');
        $coachA = $this->staff($tenant, $site, 'A 教练', 'COACH-A');
        $coachB = $this->staff($tenant, $site, 'B 教练', 'COACH-B');
        $course = $this->course($tenant, $site, $coachA);
        [$member, $card] = $this->memberAndCard($tenant, $site, $coachA);
        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coachA->id,
            'starts_at' => now()->startOfHour(),
            'ends_at' => now()->startOfHour()->addHour(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => 'completed',
            'session_kind' => 'group',
            'created_by_staff_id' => $coachA->id,
        ]);
        $appointment = Appointment::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => 'completed',
            'command_key' => (string) Str::uuid(),
            'booked_at' => now()->subHour(),
        ]);
        ConsumptionEvent::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'appointment_id' => $appointment->id,
            'session_id' => $session->id,
            'course_id' => $course->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'coach_staff_id' => $coachA->id,
            'business_date' => now()->toDateString(),
            'card_type' => 'count',
            'deducted_count' => 1,
            'consumed_value_cents' => 20000,
            'value_provenance' => 'actual',
            'status' => 'final',
            'source' => 'backfill',
            'command_key' => 'metadata-only-event-'.$appointment->id,
            'metadata' => ['deliveryRecipients' => [
                ['staffId' => $coachA->id, 'allocationBps' => 2500],
                ['staffId' => $coachB->id, 'allocationBps' => 7500],
            ]],
            'occurred_at' => now(),
        ]);

        $rows = collect($this->getJson($this->url($tenant, $site, '/consumption-reports?dimension=delivery'))
            ->assertOk()->json('data.items'))->keyBy('subjectId');
        $this->assertSame(5000, $rows[$coachA->id]['consumedAmountCents']);
        $this->assertSame(15000, $rows[$coachB->id]['consumedAmountCents']);
        $this->assertSame(0, $rows[$coachA->id]['totalCompensationCents']);
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
            'mobile' => '138'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
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

    private function course(Tenant $tenant, Site $site, Staff $staff): Course
    {
        return Course::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => 'group',
            'name' => '燃脂搏击',
            'duration_minutes' => 60,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'coach_staff_id' => $staff->id,
            'catalog_status' => 'active',
        ]);
    }

    private function cardProduct(Tenant $tenant, Site $site, Staff $staff): CardProduct
    {
        return CardProduct::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => 'count',
            'name' => '团课十次卡',
            'price' => '2000.00',
            'initial_count' => 10,
            'validity_days' => 365,
            'validity_mode' => 'days',
            'activation_mode' => 'immediate',
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
            'created_by_staff_id' => $staff->id,
        ]);
    }

    private function memberAndCard(Tenant $tenant, Site $site, Staff $staff): array
    {
        $account = Account::query()->create([
            'display_name' => '周雨晴',
            'mobile' => '13800002064',
            'status' => 'active',
        ]);
        $member = Member::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'M-10001',
            'status' => 'active',
            'source' => 'admin-test',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::query()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '周雨晴',
        ]);
        $product = $this->cardProduct($tenant, $site, $staff);
        $card = MemberCard::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => 'count',
            'card_no' => 'MC-ADMIN-001',
            'status' => 'active',
            'product_snapshot' => ['name' => $product->name],
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'cached_remaining_count' => 10,
            'issued_at' => now(),
            'issued_by_staff_id' => $staff->id,
        ]);

        return [$member, $card];
    }

    private function url(Tenant $tenant, Site $site, string $suffix): string
    {
        return "/api/v1/admin/tenants/{$tenant->id}/sites/{$site->id}{$suffix}";
    }
}
