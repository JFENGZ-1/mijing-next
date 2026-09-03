<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\SiteOfficialAccountFollow;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberOfficialAccountFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_load_official_account_follow_content(): void
    {
        [$account, $tenant, $site] = $this->seedFixture();
        SiteOfficialAccountFollow::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'image_url' => 'https://cdn.example.com/qr.png',
            'instructions_text' => '【提醒通知】即可收到课程取消、上课提醒、排队成功等通知',
            'status' => 'published',
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/official-account-follow?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.siteId', $site->id)
            ->assertJsonPath('data.imageUrl', 'https://cdn.example.com/qr.png')
            ->assertJsonPath('data.instructionsText', '【提醒通知】即可收到课程取消、上课提醒、排队成功等通知');
    }

    public function test_cross_tenant_official_account_follow_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedFixture();
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-follow']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/official-account-follow?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    public function test_missing_follow_content_returns_not_found(): void
    {
        [$account, $tenant, $site] = $this->seedFixture();
        $this->actAsMember($account);

        $this->getJson("/api/v1/member/official-account-follow?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Site}
     */
    private function seedFixture(): array
    {
        $tenant = Tenant::create(['name' => 'Follow Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Follow Member', 'status' => 'active']);
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Follow Member']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-FOLLOW',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $tenant, $site];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
