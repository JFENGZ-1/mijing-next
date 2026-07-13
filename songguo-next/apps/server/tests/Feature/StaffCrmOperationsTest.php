<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Members\MobileProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCrmOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_import_creates_leads_from_text_and_reports_line_errors(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.batch-import', 'crm.member.read', 'crm.member.create']);
        $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => 'Existing',
            'mobile' => '13800138000',
        ])->assertCreated();

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/members/batch-import", [
            'text' => "13800138001张三\n13800138000李四\ninvalid-line\n13800138002王五",
        ])->assertOk()
            ->assertJsonPath('data.successCount', 2)
            ->assertJsonPath('data.failCount', 2);

        $errors = collect($response->json('data.errors'));
        $this->assertTrue($errors->contains(fn (array $error) => $error['code'] === 'DUPLICATE_MOBILE'));
        $this->assertTrue($errors->contains(fn (array $error) => $error['code'] === 'INVALID_LINE'));
        $this->assertDatabaseHas('member_crm_profiles', ['name' => '张三']);
        $this->assertDatabaseHas('member_crm_profiles', ['name' => '王五']);
        $this->assertDatabaseMissing('member_crm_profiles', ['name' => '李四']);
    }

    public function test_batch_import_accepts_structured_lines_and_enforces_permission(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/batch-import", [
            'lines' => [
                ['name' => 'A', 'mobile' => '13800138011'],
            ],
        ])->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED');

        [, $site] = $this->actAsStaff(['crm.member.batch-import']);
        $this->postJson("/api/v1/staff/sites/{$site->id}/members/batch-import", [
            'lines' => [
                ['name' => 'Structured', 'mobile' => '13800138012'],
            ],
        ])->assertOk()->assertJsonPath('data.successCount', 1);
    }

    public function test_batch_import_structured_lines_respect_field_policy(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.batch-import']);
        $staff->tenant->update([
            'crm_field_policy' => [
                'fields' => [
                    'mobile' => ['isRequired' => true, 'isVisible' => true, 'staffEditable' => true],
                    'name' => ['isRequired' => true, 'isVisible' => true, 'staffEditable' => true],
                ],
            ],
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/batch-import", [
            'text' => "13800138021Policy OK\n13800138022",
        ])->assertOk()
            ->assertJsonPath('data.successCount', 1)
            ->assertJsonPath('data.failCount', 1);
    }

    public function test_sales_staff_list_returns_active_site_staff(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $inactive = $this->createSiteStaff($staff->tenant, $site, 'Inactive', 'inactive');
        $otherSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $this->createSiteStaff($staff->tenant, $otherSite, 'Other Site', 'active');

        $response = $this->getJson("/api/v1/staff/sites/{$site->id}/crm/sales-staff")->assertOk();
        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertTrue($ids->contains($staff->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_sales_staff_list_requires_permission_and_is_site_scoped(): void
    {
        [$staff, $site] = $this->actAsStaff([]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/crm/sales-staff")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-tenant']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/crm/sales-staff")->assertNotFound();
        $this->assertSame($staff->tenant_id, $site->tenant_id);
    }

    public function test_sticky_remark_overwrites_without_touching_notes(): void
    {
        [, $site] = $this->actAsStaff([
            'crm.member.read', 'crm.member.create', 'crm.member.update',
            'crm.member.note.read', 'crm.member.note.add',
        ]);
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => 'Remark Member',
            'mobile' => '13800138031',
        ])->assertCreated();
        $memberId = $created->json('data.id');
        $version = $created->json('data.version');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes", [
            'body' => '内部备注不应被覆盖',
        ])->assertCreated();

        $this->patchJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/sticky-remark", [
            'version' => $version,
            'stickyRemark' => '前台可见备注',
        ])->assertOk()
            ->assertJsonPath('data.stickyRemark', '前台可见备注')
            ->assertJsonPath('data.hasStickyRemark', true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes")
            ->assertOk()
            ->assertJsonPath('data.0.body', '内部备注不应被覆盖');
        $this->assertDatabaseHas('audit_events', [
            'action' => 'crm.member.sticky_remark_updated',
            'subject_id' => $memberId,
        ]);
    }

    public function test_deleted_list_and_restore_are_transactional_with_conflict_guard(): void
    {
        [$staff, $site] = $this->actAsStaff([
            'crm.member.deleted.read', 'crm.member.restore', 'crm.member.read', 'crm.member.create',
        ]);
        $mobile = app(MobileProtectionService::class);
        $normalized = $mobile->normalize('13800138041');
        $hash = $mobile->hashForTenant($normalized, $staff->tenant_id);

        $archived = $this->createMemberAtSite($staff->tenant, $site, 'Archived', $hash, $normalized, archived: true);
        MemberCrmProfile::where('member_id', $archived->id)->update(['mobile_hash' => null]);
        $active = $this->createMemberAtSite($staff->tenant, $site, 'Active', $hash, $normalized);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/deleted")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $archived->id);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$archived->id}/restore")
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_RESTORE_BLOCKED');

        Member::whereKey($active->id)->update(['archived_at' => now()]);
        MemberCrmProfile::where('member_id', $active->id)->update(['mobile_hash' => null]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$archived->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Archived');

        $this->assertDatabaseHas('members', ['id' => $archived->id, 'archived_at' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.restored', 'subject_id' => $archived->id]);
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$archived->id}")->assertOk();
    }

    public function test_deleted_and_restore_permissions_and_tenant_isolation(): void
    {
        [$staff, $site] = $this->actAsStaff(['crm.member.read']);
        $archived = $this->createMemberAtSite($staff->tenant, $site, 'Gone', null, null, archived: true);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/deleted")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$archived->id}/restore")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other2']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other2', 'status' => 'active']);
        $otherArchived = $this->createMemberAtSite($otherTenant, $otherSite, 'Foreign', null, null, archived: true);

        [, $siteWithPerm] = $this->actAsStaff(['crm.member.deleted.read', 'crm.member.restore']);
        $this->getJson("/api/v1/staff/sites/{$siteWithPerm->id}/members/deleted")->assertOk();
        $this->postJson("/api/v1/staff/sites/{$siteWithPerm->id}/members/{$otherArchived->id}/restore")->assertNotFound();
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
        $account = Account::create(['display_name' => 'CRM Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'CRM Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'CRM', 'code' => 'crm', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'crm']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createSiteStaff(Tenant $tenant, Site $site, string $name, string $status): Staff
    {
        $account = Account::create(['display_name' => $name, 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => $status,
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => false]);

        return $staff;
    }

    private function createMemberAtSite(
        Tenant $tenant,
        Site $site,
        string $name,
        ?string $mobileHash,
        ?string $normalizedMobile,
        bool $archived = false,
    ): Member {
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('????????')),
            'status' => 'lead',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'archived_at' => $archived ? now() : null,
        ]);
        $profile = ['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => $name];
        if ($mobileHash && $normalizedMobile) {
            $mobile = app(MobileProtectionService::class);
            $profile = [...$profile,
                'mobile_hash' => $mobileHash,
                'mobile_ciphertext' => $mobile->encrypt($normalizedMobile),
                'mobile_last4' => substr($normalizedMobile, -4),
                'mobile_source' => 'staff_entered',
            ];
        }
        MemberCrmProfile::create($profile);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }
}
