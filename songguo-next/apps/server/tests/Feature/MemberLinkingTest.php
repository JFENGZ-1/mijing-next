<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
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

class MemberLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_mobile_match_requires_member_and_staff_confirmation_before_linking(): void
    {
        [$memberAccount, $staff, $site, $lead] = $this->scenario();
        $this->actAsMember($memberAccount);

        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertStatus(202)
            ->assertJsonPath('data.state', 'link_review')
            ->assertJsonPath('data.status', 'pending_member_confirmation');
        $requestId = $started->json('data.requestId');
        $version = $started->json('data.version');

        $this->assertNull($lead->fresh()->account_id);
        $this->assertDatabaseCount('members', 1);

        $memberDecision = $this->postJson("/api/v1/member/member-link-requests/{$requestId}/decision", [
            'version' => $version,
            'decision' => 'link',
        ])->assertOk()->assertJsonPath('data.status', 'pending_staff_review');

        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending_staff_review');

        $this->actAsStaff($staff);
        $reviewed = $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$requestId}/decision", [
            'version' => $memberDecision->json('data.version'),
            'decision' => 'approve_link',
            'reason' => '会员本人已确认且手机号证据一致',
        ])->assertOk()->assertJsonPath('data.status', 'linked');

        $this->assertSame($lead->id, $reviewed->json('data.resolvedMemberId'));
        $this->assertDatabaseHas('members', ['id' => $lead->id, 'account_id' => $memberAccount->id]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.link.linked',
            'subject_type' => 'member_link_request',
            'actor_account_id' => $staff->account_id,
            'actor_staff_id' => $staff->id,
        ]);

        $this->actAsMember($memberAccount);
        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $lead->id);
    }

    public function test_not_me_decision_only_creates_separate_member_after_staff_approval(): void
    {
        [$memberAccount, $staff, $site, $lead] = $this->scenario();
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'not_me',
        ])->assertOk();

        $this->assertDatabaseCount('members', 1);
        $this->actAsStaff($staff);
        $reviewed = $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'approve_separate',
            'reason' => '场馆确认不是同一自然人',
        ])->assertOk()->assertJsonPath('data.status', 'separate_approved');

        $resolvedId = $reviewed->json('data.resolvedMemberId');
        $this->assertNotSame($lead->id, $resolvedId);
        $this->assertDatabaseHas('members', ['id' => $lead->id, 'account_id' => null]);
        $this->assertDatabaseHas('members', ['id' => $resolvedId, 'account_id' => $memberAccount->id]);
        $this->assertDatabaseHas('member_crm_profiles', ['member_id' => $resolvedId, 'mobile_hash' => null]);
    }

    public function test_staff_cannot_approve_separate_when_member_confirmed_the_candidate(): void
    {
        [$memberAccount, $staff, $site, $lead] = $this->scenario();
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();

        $this->actAsStaff($staff);
        $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'approve_separate',
            'reason' => '不得违背会员本人的关联决定',
        ])->assertStatus(409)->assertJsonPath('code', 'MEMBER_LINK_DECISION_MISMATCH');

        $this->assertNull($lead->fresh()->account_id);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_staff_identity_cannot_review_its_own_member_link_request(): void
    {
        [$memberAccount, $staff, $site, $lead] = $this->scenario();
        $staff->account()->associate($memberAccount);
        $staff->save();
        $staff->unsetRelation('account');

        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();

        $this->actAsStaff($staff);
        $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'approve_link',
            'reason' => '不得自审本人档案',
        ])->assertForbidden()->assertJsonPath('code', 'MEMBER_LINK_SELF_REVIEW_FORBIDDEN');

        $this->assertNull($lead->fresh()->account_id);
    }

    public function test_staff_rejection_keeps_candidate_locked_and_prevents_duplicate_join(): void
    {
        [$memberAccount, $staff, $site] = $this->scenario();
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();

        $this->actAsStaff($staff);
        $rejected = $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'reject',
            'reason' => '身份资料不足，暂不允许进入',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->actAsMember($memberAccount);
        $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertStatus(202)
            ->assertJsonPath('data.requestId', $rejected->json('data.requestId'))
            ->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseCount('members', 1);
    }

    public function test_changed_verified_mobile_invalidates_pending_link_evidence(): void
    {
        [$memberAccount, $staff, $site, $lead, $protection] = $this->scenario();
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();
        $otherMobile = $protection->normalize('13900139000');
        MemberProfile::where('account_id', $memberAccount->id)->update([
            'mobile_ciphertext' => $protection->encrypt($otherMobile),
            'mobile_hash' => $protection->hash($otherMobile),
            'mobile_last4' => '9000',
            'version' => DB::raw('version + 1'),
        ]);

        $this->actAsStaff($staff);
        $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'approve_link',
            'reason' => '尝试使用过期证据',
        ])->assertStatus(409)->assertJsonPath('code', 'MEMBER_LINK_EVIDENCE_EXPIRED');
        $this->assertNull($lead->fresh()->account_id);
    }

    public function test_staff_without_review_permission_cannot_list_or_decide_link_requests(): void
    {
        [$memberAccount, $staff, $site] = $this->scenario();
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $decision = $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();

        $staff->roles()->detach();
        $this->actAsStaff($staff);
        $this->getJson("/api/v1/staff/sites/{$site->id}/member-link-requests")
            ->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED');
        $this->postJson("/api/v1/staff/sites/{$site->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $decision->json('data.version'),
            'decision' => 'approve_link',
            'reason' => '没有审核权限',
        ])->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_cannot_review_link_request_through_an_unassigned_site(): void
    {
        [$memberAccount, $staff, $site] = $this->scenario();
        $otherSite = Site::create([
            'tenant_id' => $site->tenant_id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);
        $this->actAsMember($memberAccount);
        $started = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $this->postJson("/api/v1/member/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => $started->json('data.version'),
            'decision' => 'link',
        ])->assertOk();

        $this->actAsStaff($staff);
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/member-link-requests")->assertNotFound();
        $this->postJson("/api/v1/staff/sites/{$otherSite->id}/member-link-requests/{$started->json('data.requestId')}/decision", [
            'version' => 2,
            'decision' => 'approve_link',
            'reason' => '错误场馆上下文',
        ])->assertNotFound();
    }

    public function test_expired_pending_request_is_replaced_with_fresh_evidence(): void
    {
        [$memberAccount, , $site] = $this->scenario();
        $this->actAsMember($memberAccount);
        $first = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])->assertStatus(202);
        $this->travel(8)->days();

        $second = $this->postJson('/api/v1/member/memberships', ['siteId' => $site->id])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending_member_confirmation');

        $this->assertNotSame($first->json('data.requestId'), $second->json('data.requestId'));
        $this->assertDatabaseHas('member_link_requests', [
            'public_id' => $first->json('data.requestId'),
            'status' => 'expired',
            'active_key' => null,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'member.link.expired']);
    }

    private function scenario(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $memberAccount = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $protection = app(MobileProtectionService::class);
        $mobile = $protection->normalize('13800138000');
        MemberProfile::create([
            'account_id' => $memberAccount->id,
            'display_name' => 'Member',
            'mobile_ciphertext' => $protection->encrypt($mobile),
            'mobile_hash' => $protection->hash($mobile),
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
            'mobile_verification_method' => 'wechat',
        ]);

        $lead = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => null,
            'member_no' => 'LEAD001',
            'status' => 'lead',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $lead->id,
            'name' => '潜客张三',
            'mobile_ciphertext' => $protection->encrypt($mobile),
            'mobile_hash' => $protection->hashForTenant($mobile, $tenant->id),
            'mobile_last4' => '8000',
            'mobile_source' => 'staff_entered',
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenant->id,
            'member_id' => $lead->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffAccount = Account::create(['display_name' => 'Reviewer', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $staffAccount->id,
            'employee_no' => 'EMP001',
            'name' => 'Reviewer',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $permission = Permission::create([
            'name' => 'Review member links', 'code' => 'crm.member.link.review', 'module' => 'crm',
        ]);
        $role = Role::create([
            'tenant_id' => $tenant->id, 'name' => 'Reviewer', 'code' => 'reviewer', 'status' => 'active',
        ]);
        $role->permissions()->attach($permission);
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => $site->id]);

        return [$memberAccount, $staff, $site, $lead, $protection];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }

    private function actAsStaff(Staff $staff): void
    {
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
    }
}
