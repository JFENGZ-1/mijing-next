<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Account;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 私教档案（对标原版 drainer：预约时间制）。
 */
class CoachPrivateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uniform_profile_creates_hidden_course(): void
    {
        [$staff, $site] = $this->actAsStaff();
        $coach = $this->makeCoach($staff->tenant_id);

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'tagText' => '金牌',
            'experience' => '入行3年，上千节授课经验',
            'specialty' => '局部塑形，体态调整等',
            'bookingWindows' => [
                ['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00'],
            ],
            'subjectMode' => 'uniform',
            'uniformDurationMinutes' => 60,
        ])
            ->assertCreated()
            ->assertJsonPath('data.coachName', $coach->name)
            ->assertJsonPath('data.subjectMode', 'uniform')
            ->assertJsonPath('data.bookingWindows.0.start', '08:00');

        $uniformCourseId = $response->json('data.uniformCourseId');
        $this->assertNotNull($uniformCourseId);
        $this->assertDatabaseHas('courses', [
            'id' => $uniformCourseId,
            'hidden_in_catalog' => true,
            'course_type' => 'private',
            'coach_staff_id' => $coach->id,
        ]);

        // 隐藏课不出现在课程库列表
        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonMissing(['id' => $uniformCourseId]);

        // 同教练重复创建 → 拦截
        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'specialty' => 'x',
            'bookingWindows' => [['days' => [1], 'start' => '08:00', 'end' => '10:00']],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'COACH_PRIVATE_PROFILE_EXISTS');
    }

    public function test_update_and_delete_profile(): void
    {
        [$staff, $site] = $this->actAsStaff();
        $coach = $this->makeCoach($staff->tenant_id);

        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 3, 5], 'start' => '09:00', 'end' => '18:00']],
        ])->assertCreated()->json('data');

        // 教练名下建一门课目（切 per_course 的前置条件：课目非空）
        Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'coach_staff_id' => $coach->id,
            'name' => '体型调整',
            'duration_minutes' => 60,
            'hidden_in_catalog' => false,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        // 更新：改时长 + 切 per_course 模式
        $this->patchJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$created['id']}", [
            'version' => $created['version'],
            'uniformDurationMinutes' => 90,
            'subjectMode' => 'per_course',
            'tagText' => '明星教练',
        ])
            ->assertOk()
            ->assertJsonPath('data.uniformDurationMinutes', 90)
            ->assertJsonPath('data.subjectMode', 'per_course')
            ->assertJsonPath('data.tagText', '明星教练');

        // 隐藏课时长同步
        $this->assertDatabaseHas('courses', [
            'id' => $created['uniformCourseId'],
            'duration_minutes' => 90,
        ]);

        // 删除：档案删除 + 隐藏课归档
        $this->deleteJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$created['id']}")
            ->assertOk();
        $this->assertDatabaseMissing('coach_private_profiles', ['id' => $created['id']]);
        $this->assertDatabaseHas('courses', [
            'id' => $created['uniformCourseId'],
            'catalog_status' => 'archived',
        ]);
    }

    public function test_save_full_submits_courses_and_fees_in_one_shot(): void
    {
        // 对标原版 savePrivateCourse：档案 + 课目 + 卡扣费一次性提交
        [$staff, $site] = $this->actAsStaff();
        $coach = $this->makeCoach($staff->tenant_id);
        $card = \App\Models\CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => \App\Enums\CardType::Count,
            'name' => '次卡A',
            'price' => 100,
            'initial_count' => 10,
            'activation_mode' => 'immediate',
            'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
            'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
        ]);

        // 创建：per_course + 一个课目挂卡扣费
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3], 'start' => '09:00', 'end' => '18:00']],
            'subjectMode' => 'per_course',
            'courses' => [
                ['name' => '体型调整', 'durationMinutes' => 60, 'feeList' => [
                    ['cardProductId' => $card->id, 'deductAmount' => 2],
                ]],
            ],
        ])->assertOk()->json('data');

        $this->assertCount(1, $created['courses']);
        $courseId = $created['courses'][0]['id'];
        $this->assertSame('体型调整', $created['courses'][0]['name']);
        $this->assertSame($card->id, $created['courses'][0]['feeList'][0]['cardProductId']);
        $this->assertDatabaseHas('card_product_course_scopes', [
            'card_product_id' => $card->id,
            'scope_key' => (string) $courseId,
        ]);

        // 再次保存：移除课目的卡扣费 + 删除课目改为新课目
        $updated = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'profileId' => $created['id'],
            'version' => $created['version'],
            'bookingWindows' => [['days' => [1], 'start' => '09:00', 'end' => '12:00']],
            'subjectMode' => 'per_course',
            'courses' => [
                ['name' => '产后恢复', 'durationMinutes' => 45, 'feeList' => []],
            ],
        ])->assertOk()->json('data');

        $this->assertCount(1, $updated['courses']);
        $this->assertSame('产后恢复', $updated['courses'][0]['name']);
        // 旧课目归档 + 旧 scope 已随 feeList reconcile 逻辑保留在旧课上（未提交则不动）
        $this->assertDatabaseHas('courses', ['id' => $courseId, 'catalog_status' => 'archived']);
    }

    public function test_save_full_per_course_requires_subjects(): void
    {
        // 对齐原版「请设置私教科目」：课目模式不允许零课目保存（服务端兜底）
        [$staff, $site] = $this->actAsStaff();
        $coach = $this->makeCoach($staff->tenant_id);
        $windows = [['days' => [1], 'start' => '09:00', 'end' => '18:00']];

        // 新建 per_course 未带 courses → 422
        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => $windows,
            'subjectMode' => 'per_course',
        ])->assertStatus(422);

        // 新建 per_course 带空 courses → 422
        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => $windows,
            'subjectMode' => 'per_course',
            'courses' => [],
        ])->assertStatus(422);
    }

    public function test_update_cannot_switch_to_per_course_without_subjects(): void
    {
        [$staff, $site] = $this->actAsStaff();
        $coach = $this->makeCoach($staff->tenant_id);

        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1], 'start' => '09:00', 'end' => '18:00']],
        ])->assertCreated()->json('data');

        // 无课目切 per_course → 422
        $this->patchJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$created['id']}", [
            'version' => $created['version'],
            'subjectMode' => 'per_course',
        ])->assertStatus(422);

        // 补一门课目后可切换
        Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'coach_staff_id' => $coach->id,
            'name' => '产后恢复',
            'duration_minutes' => 45,
            'hidden_in_catalog' => false,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $this->patchJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$created['id']}", [
            'version' => $created['version'],
            'subjectMode' => 'per_course',
        ])
            ->assertOk()
            ->assertJsonPath('data.subjectMode', 'per_course');
    }

    public function test_profiles_are_tenant_isolated(): void
    {
        // 租户 A 创建档案
        [$staffA, $siteA] = $this->actAsStaff('tenant-a');
        $coachA = $this->makeCoach($staffA->tenant_id);
        $profileId = $this->postJson("/api/v1/staff/sites/{$siteA->id}/private-coaches", [
            'coachStaffId' => $coachA->id,
            'bookingWindows' => [['days' => [1], 'start' => '08:00', 'end' => '10:00']],
        ])->assertCreated()->json('data.id');

        // 租户 B 员工访问 A 的 site/档案 → 404
        [$staffB, $siteB] = $this->actAsStaff('tenant-b');
        $this->getJson("/api/v1/staff/sites/{$siteA->id}/private-coaches")->assertNotFound();
        $this->patchJson("/api/v1/staff/sites/{$siteB->id}/private-coaches/{$profileId}", [
            'version' => 1,
        ])->assertNotFound();
    }

    // ================= fixture =================

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function actAsStaff(string $code = 'tenant-x'): array
    {
        $tenant = Tenant::create(['name' => $code, 'code' => $code.fake()->unique()->numerify('##')]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Admin', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '管理员',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => '角色', 'code' => 'r'.fake()->unique()->numerify('###'), 'status' => 'active']);
        foreach (['course-catalog.read', 'course-catalog.write'] as $permission) {
            $model = Permission::firstOrCreate(['code' => $permission], ['name' => $permission, 'module' => 'catalog']);
            $role->permissions()->attach($model->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeCoach(int $tenantId): Staff
    {
        return Staff::create([
            'tenant_id' => $tenantId,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '管家',
            'status' => 'active',
        ]);
    }
}
