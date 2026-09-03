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

class StaffPayrollConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_config_returns_defaults_and_can_be_updated(): void
    {
        [, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config")
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.mode', null);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'fixed_hours',
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.mode', 'fixed_hours');

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config")
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.mode', 'fixed_hours');
    }

    public function test_coach_rules_matrix_persists_and_merges_course_catalog(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);
        $coach = $this->createSiteStaff($site, 'Payroll Coach');
        $groupCourse = $this->createCourse($site, $viewer, CourseType::Group, 'Payroll Group');
        $privateCourse = $this->createCourse($site, $viewer, CourseType::Private, 'Payroll Private');

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}")
            ->assertOk()
            ->assertJsonPath('data.coach.staffId', $coach->id)
            ->assertJsonPath('data.matrixVersion', 0)
            ->assertJsonPath('data.groupCourses.0.courseId', $groupCourse->id)
            ->assertJsonPath('data.groupCourses.0.configured', false)
            ->assertJsonPath('data.privateCourses.0.courseId', $privateCourse->id)
            ->assertJsonPath('data.privateCourses.0.configured', false);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}", [
            'groupCourses' => [
                ['courseId' => $groupCourse->id, 'unitPriceCents' => 12000],
            ],
            'privateCourses' => [
                ['courseId' => $privateCourse->id, 'unitPriceCents' => 25000, 'additionalPriceCents' => 3000],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.matrixVersion', 1)
            ->assertJsonPath('data.groupCourses.0.configured', true)
            ->assertJsonPath('data.groupCourses.0.unitPriceCents', 12000)
            ->assertJsonPath('data.privateCourses.0.configured', true)
            ->assertJsonPath('data.privateCourses.0.unitPriceCents', 25000)
            ->assertJsonPath('data.privateCourses.0.additionalPriceCents', 3000);

        $coachesResponse = $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coaches")
            ->assertOk();

        $coachRow = collect($coachesResponse->json('data.items'))
            ->firstWhere('staffId', $coach->id);

        $this->assertNotNull($coachRow);
        $this->assertTrue($coachRow['rulesConfigured']);
        $this->assertSame(1, $coachRow['matrixVersion']);
    }

    public function test_sales_config_returns_defaults_and_can_be_updated(): void
    {
        [, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config")
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.mode', null)
            ->assertJsonPath('data.settings.newSaleRatePercent', null);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => true,
            'mode' => 'flat_rate',
            'settings' => [
                'newSaleRatePercent' => 5,
                'renewalRatePercent' => 3,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.mode', 'flat_rate')
            ->assertJsonPath('data.settings.newSaleRatePercent', 5)
            ->assertJsonPath('data.settings.renewalRatePercent', 3);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => true,
            'mode' => 'tiered',
            'settings' => [
                'newSaleTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => 100000, 'ratePercent' => 6],
                ],
                'renewalTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => null, 'ratePercent' => 4],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'tiered')
            ->assertJsonCount(1, 'data.settings.newSaleTiers')
            ->assertJsonPath('data.settings.newSaleTiers.0.ratePercent', 6)
            ->assertJsonPath('data.settings.renewalTiers.0.ratePercent', 4);
    }

    public function test_sales_config_rejects_overlapping_tiered_ranges(): void
    {
        [, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => true,
            'mode' => 'tiered',
            'settings' => [
                'newSaleTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => 100000, 'ratePercent' => 6],
                    ['fromAmountCents' => 50000, 'toAmountCents' => 200000, 'ratePercent' => 8],
                ],
                'renewalTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => null, 'ratePercent' => 4],
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYROLL_TIER_OVERLAP');

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => true,
            'mode' => 'tiered',
            'settings' => [
                'newSaleTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => 100000, 'ratePercent' => 6],
                ],
                'renewalTiers' => [
                    ['fromAmountCents' => 0, 'toAmountCents' => 100000, 'ratePercent' => 4],
                    ['fromAmountCents' => 100000, 'toAmountCents' => null, 'ratePercent' => 5],
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYROLL_TIER_OVERLAP');
    }

    public function test_payroll_config_endpoints_require_permissions(): void
    {
        [$viewer, $site] = $this->actAsStaff([]);
        $coach = $this->createSiteStaff($site, 'Denied Coach');

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'headcount',
        ])->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}")
            ->assertForbidden();

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}", [
            'groupCourses' => [],
            'privateCourses' => [],
        ])->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config")->assertForbidden();
        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => false,
        ])->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coaches")->assertForbidden();

        [, $site] = $this->actAsStaff(['payroll.config.read']);
        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'amount',
        ])->assertForbidden();
    }

    public function test_payroll_config_endpoints_are_scoped_to_assigned_site(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);
        $coach = $this->createSiteStaff($site, 'Scoped Coach');
        $otherSite = Site::create([
            'tenant_id' => $viewer->tenant_id,
            'name' => 'Other Payroll Site',
            'code' => 'payroll-other',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/payroll/coach-config")->assertNotFound();
        $this->putJson("/api/v1/staff/sites/{$otherSite->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'fixed_hours',
        ])->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/payroll/coach-rules?staffId={$coach->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/payroll/sales-config")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/payroll/coaches")->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config")->assertOk();
    }

    public function test_cannot_configure_rules_for_staff_outside_site(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.config.read', 'payroll.config.write']);
        $otherSite = Site::create([
            'tenant_id' => $viewer->tenant_id,
            'name' => 'Detached Payroll Site',
            'code' => 'payroll-detached',
            'status' => 'active',
        ]);
        $detachedCoach = $this->createSiteStaff($otherSite, 'Detached Coach');

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$detachedCoach->id}")
            ->assertNotFound();

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$detachedCoach->id}", [
            'groupCourses' => [],
            'privateCourses' => [],
        ])->assertNotFound();
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'code' => fake()->unique()->slug(1),
        ]);
        $account = Account::create(['display_name' => 'Payroll Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Payroll Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Payroll', 'code' => 'payroll', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'payroll']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createSiteStaff(Site $site, string $name): Staff
    {
        $staff = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => $name, 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $site->tenant_id, 'is_primary' => false]);

        return $staff;
    }

    private function createCourse(Site $site, Staff $staff, CourseType $type, string $name): Course
    {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => $type,
            'name' => $name,
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);
    }
}
