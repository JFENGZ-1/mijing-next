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

class StaffCourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_permission_can_list_active_courses(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.read']);
        $active = $this->createCourse($site, '瑜伽团课', CourseType::Group, CourseCatalogStatus::Active);
        $this->createCourse($site, '旧课目', CourseType::Group, CourseCatalogStatus::Archived, archived: true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $active->id)
            ->assertJsonPath('data.items.0.name', '瑜伽团课')
            ->assertJsonPath('data.items.0.courseType', 'group');

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses/{$active->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.tags', []);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createCourse($site, '瑜伽团课', CourseType::Group, CourseCatalogStatus::Active);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_catalog_is_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['course-catalog.read']);
        $visible = $this->createCourse($site, '本馆课', CourseType::Group, CourseCatalogStatus::Active);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherCourse = Course::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'course_type' => CourseType::Group,
            'name' => '外馆课',
            'duration_minutes' => 60,
            'max_capacity' => 10,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $secondSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $branchCourse = $this->createCourse($secondSite, '分店课', CourseType::Group, CourseCatalogStatus::Active);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $visible->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses/{$otherCourse->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/courses/{$branchCourse->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/courses")->assertNotFound();
    }

    public function test_archived_courses_are_excluded_from_catalog_list(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.read']);
        $this->createCourse($site, '在售课', CourseType::Group, CourseCatalogStatus::Active);
        $archived = $this->createCourse($site, '已归档课', CourseType::Group, CourseCatalogStatus::Archived, archived: true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.name', '在售课');

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses/{$archived->id}")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived');
    }

    private function createCourse(
        Site $site,
        string $name,
        CourseType $courseType,
        CourseCatalogStatus $catalogStatus,
        int $sortOrder = 0,
        bool $archived = false,
    ): Course {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => $courseType,
            'name' => $name,
            'duration_minutes' => 60,
            'max_capacity' => $courseType === CourseType::Group ? 12 : null,
            'catalog_status' => $catalogStatus,
            'sort_order' => $sortOrder,
            'archived_at' => $archived ? now() : null,
        ]);
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Course Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Course Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Courses', 'code' => 'courses', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'catalog']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
