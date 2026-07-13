<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Account;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCourseCatalogWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_update_group_course(): void
    {
        [$staff, $site] = $this->actAsStaff(['course-catalog.write', 'course-catalog.read']);
        $room = $this->createRoom($site, 'A教室');

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/courses", [
            'courseType' => 'group',
            'name' => '瑜伽团课',
            'durationMinutes' => 60,
            'difficulty' => 2,
            'minCapacity' => 3,
            'maxCapacity' => 12,
            'defaultRoomId' => $room->id,
            'tags' => ['瑜伽'],
            'sortOrder' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', '瑜伽团课')
            ->assertJsonPath('data.courseType', 'group')
            ->assertJsonPath('data.maxCapacity', 12)
            ->assertJsonPath('data.defaultRoomId', $room->id)
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.version', 1);

        $courseId = $create->json('data.id');

        $this->putJson("/api/v1/staff/sites/{$site->id}/courses/{$courseId}", [
            'version' => 1,
            'name' => '瑜伽进阶团课',
            'durationMinutes' => 75,
            'maxCapacity' => 10,
            'minCapacity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '瑜伽进阶团课')
            ->assertJsonPath('data.durationMinutes', 75)
            ->assertJsonPath('data.version', 2);
    }

    public function test_staff_can_create_private_course_with_coach(): void
    {
        [$staff, $site] = $this->actAsStaff(['course-catalog.write']);
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses", [
            'courseType' => 'private',
            'name' => '私教体验',
            'durationMinutes' => 60,
            'coachStaffId' => $coach->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.courseType', 'private')
            ->assertJsonPath('data.coachStaffId', $coach->id)
            ->assertJsonPath('data.coachName', '李教练');
    }

    public function test_create_validates_course_type_specific_fields(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.write']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses", [
            'courseType' => 'group',
            'name' => '缺容量团课',
            'durationMinutes' => 60,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses", [
            'courseType' => 'private',
            'name' => '缺教练私教',
            'durationMinutes' => 60,
        ])->assertUnprocessable();
    }

    public function test_archive_excludes_from_catalog_and_restore_brings_back(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.write', 'course-catalog.read']);
        $course = $this->createCourse($site, '待归档课', CourseType::Group);

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived')
            ->assertJsonPath('data.archivedAt', fn ($value) => $value !== null);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'active')
            ->assertJsonPath('data.archivedAt', null);

        $this->getJson("/api/v1/staff/sites/{$site->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_physical_delete_is_forbidden(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.write']);
        $course = $this->createCourse($site, '不可删除课', CourseType::Group);

        $this->deleteJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'COURSE_CATALOG_DELETE_FORBIDDEN');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'catalog_status' => 'active']);
    }

    public function test_staff_without_write_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['course-catalog.read']);
        $course = $this->createCourse($site, '只读课', CourseType::Group);

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses", [
            'courseType' => 'group',
            'name' => '新课',
            'durationMinutes' => 60,
            'maxCapacity' => 10,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}", [
            'version' => 1,
            'name' => '改名',
            'durationMinutes' => 60,
            'maxCapacity' => 10,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_write_apis_are_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['course-catalog.write']);
        $course = $this->createCourse($site, '本馆课', CourseType::Group);

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
        $branchCourse = $this->createCourse($secondSite, '分店课', CourseType::Group);

        $this->putJson("/api/v1/staff/sites/{$site->id}/courses/{$otherCourse->id}", [
            'version' => 1,
            'name' => '越权',
            'durationMinutes' => 60,
            'maxCapacity' => 10,
        ])->assertNotFound();

        $this->putJson("/api/v1/staff/sites/{$site->id}/courses/{$branchCourse->id}", [
            'version' => 1,
            'name' => '越权',
            'durationMinutes' => 60,
            'maxCapacity' => 10,
        ])->assertNotFound();

        $this->postJson("/api/v1/staff/sites/{$site->id}/courses/{$course->id}/archive")->assertOk();
        $this->postJson("/api/v1/staff/sites/{$site->id}/courses/{$otherCourse->id}/archive")->assertNotFound();
    }

    private function createRoom(Site $site, string $name): Room
    {
        return Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => $name,
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
        ]);
    }

    private function createCourse(Site $site, string $name, CourseType $courseType): Course
    {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => $courseType,
            'name' => $name,
            'duration_minutes' => 60,
            'max_capacity' => $courseType === CourseType::Group ? 12 : null,
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
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
