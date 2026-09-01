<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberSitePublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_read_site_public_detail(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => 'site-public']);
        $account = Account::create(['status' => 'active']);
        $site = Site::create([
            'tenant_id' => $tenant->id,
            'name' => '觅境瑜伽',
            'code' => 'main',
            'status' => 'active',
            'phone' => '13800000000',
            'address' => '测试路 1 号',
            'description' => '欢迎光临',
        ]);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-SITE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberProfile::create(['account_id' => $account->id, 'display_name' => '会员', 'version' => 1]);
        MemberCrmProfile::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '会员',
            'version' => 1,
        ]);

        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/sites/{$site->id}/public-detail?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.name', '觅境瑜伽')
            ->assertJsonPath('data.phone', '13800000000')
            ->assertJsonPath('data.description', '欢迎光临');
    }
}
