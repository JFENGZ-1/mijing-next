<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberTag;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_unlinked_lead_and_only_receives_masked_mobile(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '潜客张三',
            'mobile' => '13800138000',
            'assignToMe' => true,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'lead')
            ->assertJsonPath('data.mobileMasked', '*******8000')
            ->assertJsonPath('data.owner.id', $staff->id)
            ->assertJsonMissing(['mobile' => '13800138000']);

        $memberId = $response->json('data.id');
        $this->assertDatabaseHas('members', ['id' => $memberId, 'account_id' => null, 'tenant_id' => $staff->tenant_id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.created', 'subject_id' => $memberId]);
        $this->assertDatabaseHas('member_status_events', ['member_id' => $memberId, 'to_status' => 'lead']);
    }

    public function test_same_tenant_mobile_conflict_never_merges_members(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $payload = ['name' => 'First', 'mobile' => '13800138000'];
        $this->postJson("/api/v1/staff/sites/{$site->id}/members", $payload)->assertCreated();

        $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Second', 'mobile' => '13800138000'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CRM_MOBILE_CONFLICT');

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_staff_cannot_access_member_outside_assigned_site_or_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'OTHER001',
            'status' => 'lead',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
        ]);
        MemberCrmProfile::create(['tenant_id' => $otherTenant->id, 'member_id' => $otherMember->id, 'name' => 'Other Member']);
        DB::table('member_sites')->insert([
            'tenant_id' => $otherTenant->id, 'member_id' => $otherMember->id, 'site_id' => $otherSite->id,
            'relationship_type' => 'home', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$otherMember->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/members")->assertNotFound();
        $this->assertSame($staff->tenant_id, $site->tenant_id);
    }

    public function test_member_token_cannot_use_staff_crm_even_for_same_account(): void
    {
        [$staff, $site] = $this->makeStaff($this->allCrmPermissions());
        Sanctum::actingAs($staff->account, ['api', 'client:member']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members")
            ->assertForbidden()
            ->assertJsonPath('code', 'TOKEN_AUDIENCE_INVALID');
    }

    public function test_status_transition_is_explicit_versioned_and_audited(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Lead'])->assertCreated();
        $memberId = $created->json('data.id');
        $version = $created->json('data.version');

        $activated = $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/status-transitions", [
            'version' => $version,
            'targetStatus' => 'active',
            'reason' => '完成首次到店登记',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/status-transitions", [
            'version' => $version,
            'targetStatus' => 'frozen',
            'reason' => 'stale request',
        ])->assertStatus(409);

        $this->assertDatabaseHas('member_status_events', ['member_id' => $memberId, 'from_status' => 'lead', 'to_status' => 'active']);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.status_changed', 'subject_id' => $memberId]);
        $this->assertGreaterThan($version, $activated->json('data.version'));
    }

    public function test_notes_are_append_only_and_tags_are_tenant_scoped(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Member'])->assertCreated();
        $memberId = $created->json('data.id');
        $version = $created->json('data.version');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes", ['body' => '首次沟通，会员不可见'])
            ->assertCreated();
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes")
            ->assertOk()->assertJsonPath('data.0.author', $staff->name);

        $tag = MemberTag::create([
            'tenant_id' => $staff->tenant_id, 'name' => '重点跟进', 'normalized_name' => '重点跟进', 'color' => '#D92D20',
        ]);
        $this->putJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/tags", [
            'version' => $version,
            'tagIds' => [$tag->id],
        ])->assertOk()->assertJsonPath('data.tags.0.id', $tag->id);

        $this->assertDatabaseCount('member_notes', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.note_added', 'subject_id' => $memberId]);
        $this->getJson("/api/v1/staff/member-tags?siteId={$site->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $tag->id);
        $this->getJson('/api/v1/staff/member-tags')->assertUnprocessable();
    }

    public function test_database_rejects_cross_tenant_staff_site_pivot(): void
    {
        [$staff] = $this->makeStaff($this->allCrmPermissions());
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other']);

        $this->expectException(QueryException::class);
        DB::table('site_staff')->insert([
            'site_id' => $otherSite->id,
            'tenant_id' => $staff->tenant_id,
            'staff_id' => $staff->id,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allCrmPermissions(): array
    {
        return [
            'crm.member.read', 'crm.member.mobile.search', 'crm.member.create', 'crm.member.update',
            'crm.member.status.manage', 'crm.member.owner.claim', 'crm.member.note.read',
            'crm.member.note.add', 'crm.member.tag.assign', 'crm.tag.manage', 'crm.member.app_access.manage',
        ];
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
}
