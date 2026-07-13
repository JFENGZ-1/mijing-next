<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberPurchaseGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profile_blocks_purchase_with_missing_fields(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'purchase-gate-blocked']);
        $account = Account::create(['status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-GATE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberCrmProfile::create(['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => '待完善']);
        $this->publishPrivacyDocument();

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile/purchase-gate?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.missingFields.0.key', 'displayName')
            ->assertJsonPath('data.missingFields.1.key', 'privacyConsent')
            ->assertJsonPath('data.redirectHints.profile', "/member/profile?tenantId={$tenant->id}")
            ->assertJsonPath('data.redirectHints.cardCatalog', "/member/card-products?tenantId={$tenant->id}");
    }

    public function test_complete_profile_allows_purchase_gate(): void
    {
        [$account, $tenant] = $this->seedCompleteMember();

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile/purchase-gate?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.missingFields', [])
            ->assertJsonPath('data.redirectHints.cardCatalog', "/member/card-products?tenantId={$tenant->id}");
    }

    public function test_cross_tenant_purchase_gate_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedCompleteMember();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-gate']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/profile/purchase-gate?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant}
     */
    private function seedCompleteMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'purchase-gate-allowed']);
        $account = Account::create(['display_name' => '完整会员', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-GATE-OK',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => '完整会员',
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
            'version' => 1,
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '完整会员',
        ]);
        $document = $this->publishPrivacyDocument();
        LegalConsent::create([
            'account_id' => $account->id,
            'legal_document_id' => $document->id,
            'action' => 'accepted',
            'source' => 'member-miniapp',
            'occurred_at' => now(),
        ]);

        return [$account, $tenant];
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
