<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_member_can_read_tenant_profile_with_field_policy(): void
    {
        [$account, $tenant, $member] = $this->seedEnrolledMember(displayName: '演示会员');

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.profile.displayName', '演示会员')
            ->assertJsonPath('data.profile.mobileMasked', '*******8000')
            ->assertJsonPath('data.registration.state', 'configuration_required')
            ->assertJsonPath('data.fieldPolicy.fields.0.key', 'displayName')
            ->assertJsonPath('data.fieldPolicy.fields.0.memberEditable', true)
            ->assertJsonMissingPath('data.profile.mobile');
    }

    public function test_member_can_update_allowed_profile_fields_and_sync_crm(): void
    {
        [$account, $tenant, $member] = $this->seedEnrolledMember(displayName: '旧名字');

        $this->actAsMember($account);

        $this->patchJson("/api/v1/member/profile?tenantId={$tenant->id}", [
            'displayName' => '新名字',
            'gender' => 'female',
            'birthDate' => '1992-03-15',
            'heightCm' => 165,
            'weightKg' => 52,
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.profile.displayName', '新名字')
            ->assertJsonPath('data.profile.gender', 'female')
            ->assertJsonPath('data.profile.birthDate', '1992-03-15')
            ->assertJsonPath('data.profile.version', 2);

        $this->assertDatabaseHas('member_profiles', [
            'account_id' => $account->id,
            'display_name' => '新名字',
            'gender' => 'female',
        ]);
        $this->assertDatabaseHas('member_crm_profiles', [
            'member_id' => $member->id,
            'name' => '新名字',
            'gender' => 'female',
            'birth_date' => '1992-03-15',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_account_id' => $account->id,
            'action' => 'member.profile.updated',
        ]);
    }

    public function test_mobile_direct_edit_is_rejected_on_tenant_profile_update(): void
    {
        [$account, $tenant] = $this->seedEnrolledMember();

        $this->actAsMember($account);

        $this->putJson("/api/v1/member/profile?tenantId={$tenant->id}", [
            'mobile' => '13800138000',
            'version' => 1,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_cross_tenant_profile_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedEnrolledMember();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-profile']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile?tenantId={$otherTenant->id}")
            ->assertNotFound();

        $this->patchJson("/api/v1/member/profile?tenantId={$otherTenant->id}", [
            'displayName' => '越权修改',
            'version' => 1,
        ])->assertNotFound();
    }

    public function test_profile_required_state_is_exposed_and_can_be_completed_via_tenant_update(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'profile-required']);
        $account = Account::create(['status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-REQ',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberCrmProfile::create(['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => '待完善']);
        $this->publishPrivacyDocument();

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.registration.state', 'profile_required')
            ->assertJsonPath('data.registration.missingFields.0', 'displayName')
            ->assertJsonPath('data.profile', null);

        $this->patchJson("/api/v1/member/profile?tenantId={$tenant->id}", [
            'displayName' => '补全昵称',
        ])->assertOk()
            ->assertJsonPath('data.profile.displayName', '补全昵称')
            ->assertJsonPath('data.registration.state', 'consent_required')
            ->assertJsonPath('data.registration.missingFields.0', 'privacyConsent');
    }

    public function test_avatar_upload_stores_object_key_and_returns_url(): void
    {
        Storage::fake('public');
        [$account, $tenant] = $this->seedEnrolledMember();

        $this->actAsMember($account);

        $response = $this->post("/api/v1/member/profile/avatar?tenantId={$tenant->id}", [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            'version' => 1,
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $objectKey = $response->json('data.avatarObjectKey');
        $this->assertNotNull($objectKey);
        Storage::disk('public')->assertExists($objectKey);
        $this->assertStringContainsString('/storage/', (string) $response->json('data.avatarUrl'));
        $this->assertDatabaseHas('member_profiles', [
            'account_id' => $account->id,
            'avatar_object_key' => $objectKey,
        ]);
        $this->assertSame(1, AuditEvent::where('action', 'member.profile.avatar_updated')->count());
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member}
     */
    private function seedEnrolledMember(?string $displayName = '演示会员'): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => $displayName, 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-PROFILE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => $displayName,
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
            'version' => 1,
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => $displayName,
        ]);

        return [$account, $tenant, $member];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }

    private function publishPrivacyDocument(): LegalDocument
    {
        return LegalDocument::create([
            'scope_key' => 'global',
            'type' => 'privacy',
            'version' => 'test-v1',
            'title' => 'Privacy',
            'content' => 'Test privacy content',
            'content_hash' => hash('sha256', 'Test privacy content'),
            'status' => 'published',
            'is_required' => true,
            'published_at' => now(),
        ]);
    }
}
