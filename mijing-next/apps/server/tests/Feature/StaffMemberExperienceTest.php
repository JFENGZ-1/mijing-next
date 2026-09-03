<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteCarouselItem;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_warm_hint_crud_for_private_and_group_course_types(): void
    {
        [, $site] = $this->actAsStaff(['tenant.member-experience.read', 'tenant.member-experience.write']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-warm-hint")
            ->assertOk()
            ->assertJsonCount(2, 'data.hints')
            ->assertJsonPath('data.hints.0.courseType', 6)
            ->assertJsonPath('data.hints.1.courseType', 7)
            ->assertJsonPath('data.hints.0.hasContent', false);

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-warm-hint", [
            'courseType' => 7,
            'title' => '团课温馨提示',
            'text' => '<p>请提前10分钟到场</p>',
        ])
            ->assertOk()
            ->assertJsonPath('data.hints.1.hasContent', true)
            ->assertJsonPath('data.hints.1.title', '团课温馨提示');

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-warm-hint", [
            'courseType' => 7,
            'title' => '',
            'text' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.hints.1.hasContent', false);

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'member_warm_hints' => null,
        ]);
    }

    public function test_carousel_crud_reflects_on_member_home(): void
    {
        [$staff, $site, $tenant] = $this->actAsStaffWithTenant(['tenant.member-experience.read', 'tenant.member-experience.write']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-carousel", [
            'items' => [
                ['imageUrl' => 'https://cdn.example.com/custom-1.jpg', 'sortOrder' => 0],
                ['imageUrl' => 'https://cdn.example.com/custom-2.jpg', 'sortOrder' => 1],
            ],
            'defaultImageUrl' => 'https://cdn.example.com/fallback.jpg',
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.defaultImageUrl', 'https://cdn.example.com/fallback.jpg')
            ->assertJsonPath('data.usesDefaultImage', false);

        $this->assertDatabaseCount('site_carousel_items', 2);

        [$account, $member] = $this->makeMember($tenant, $site);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/home?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertOk()
            ->assertJsonPath('data.carousel.items.0.imageUrl', 'https://cdn.example.com/custom-1.jpg')
            ->assertJsonPath('data.carousel.items.1.imageUrl', 'https://cdn.example.com/custom-2.jpg')
            ->assertJsonPath('data.carousel.defaultImageUrl', null);
    }

    public function test_carousel_empty_falls_back_to_default_image(): void
    {
        [$staff, $site, $tenant] = $this->actAsStaffWithTenant(['tenant.member-experience.read', 'tenant.member-experience.write']);
        [$account] = $this->makeMember($tenant, $site);

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-carousel", [
            'items' => [],
            'defaultImageUrl' => 'https://cdn.example.com/fallback.jpg',
        ])->assertOk()
            ->assertJsonPath('data.usesDefaultImage', true);

        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/home?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertOk()
            ->assertJsonPath('data.carousel.items', [])
            ->assertJsonPath('data.carousel.defaultImageUrl', 'https://cdn.example.com/fallback.jpg');
    }

    public function test_miniapp_layout_persists_and_syncs_show_month_rank(): void
    {
        [$staff, $site, $tenant] = $this->actAsStaffWithTenant(['tenant.member-experience.read', 'tenant.member-experience.write']);

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/member-miniapp-layout")
            ->assertOk()
            ->assertJsonStructure(['data' => ['items' => [['key', 'label', 'group', 'enabled']]]]);

        $items = collect($response->json('data.items'))
            ->map(fn (array $item) => $item['key'] === 'showMonthRank'
                ? ['key' => 'showMonthRank', 'enabled' => true]
                : $item)
            ->all();

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-miniapp-layout", ['items' => $items])
            ->assertOk()
            ->assertJsonPath('data.items', fn ($value) => collect($value)->firstWhere('key', 'showMonthRank')['enabled'] === true);

        $tenant->refresh();
        $this->assertTrue($tenant->show_month_rank);

        [$account, $member] = $this->makeMember($tenant, $site);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/mine?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.showMonthRank', true);
    }

    public function test_onboarding_help_crud(): void
    {
        [, $site] = $this->actAsStaff(['tenant.member-experience.read', 'tenant.member-experience.write']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-onboarding-help")
            ->assertOk()
            ->assertJsonPath('data.posterUrl', null);

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-onboarding-help", [
            'posterUrl' => 'https://cdn.example.com/poster.png',
            'stepUrl' => 'https://cdn.example.com/steps.png',
        ])
            ->assertOk()
            ->assertJsonPath('data.posterUrl', 'https://cdn.example.com/poster.png')
            ->assertJsonPath('data.stepUrl', 'https://cdn.example.com/steps.png');
    }

    public function test_member_experience_endpoints_require_permissions(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/member-warm-hint")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/member-carousel", [
            'items' => [['imageUrl' => 'https://cdn.example.com/denied.jpg']],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_settings_hub_lists_member_experience_items_with_routes(): void
    {
        [, $site] = $this->actAsStaff([
            'tenant.settings.read',
            'tenant.settings.member-experience.read',
            'tenant.member-experience.read',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'member-warm-hint',
                'route' => '/subpackages/settings/member/warm-hint/index',
                'implemented' => true,
                'enabled' => true,
            ])
            ->assertJsonFragment([
                'key' => 'member-home-carousel',
                'route' => '/subpackages/settings/member/carousel/index',
                'implemented' => true,
            ]);
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Tenant}
     */
    private function actAsStaffWithTenant(array $permissions): array
    {
        [$staff, $site] = $this->actAsStaff($permissions);

        return [$staff, $site, $staff->tenant];
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Experience Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Experience Admin',
            'status' => 'active',
        ]);
        $staff->setRelation('tenant', $tenant);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Experience', 'code' => 'experience', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'tenant-config']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    /**
     * @return array{0: Account, 1: Member}
     */
    private function makeMember(Tenant $tenant, Site $site): array
    {
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-001',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $member];
    }
}
