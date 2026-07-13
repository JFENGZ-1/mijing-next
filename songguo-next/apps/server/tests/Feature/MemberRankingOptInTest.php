<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Member;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberRankingOptInTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_opt_in_and_out_when_monthly_ranking_enabled(): void
    {
        [$account, $tenant, $member] = $this->seedFixture(showMonthRank: true);
        $this->assertFalse($member->ranking_opt_in);

        $this->actAsMember($account);

        $this->patchJson("/api/v1/member/profile/ranking-opt-in?tenantId={$tenant->id}", [
            'optIn' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.rankingOptIn', true);

        $this->assertTrue($member->fresh()->ranking_opt_in);

        $this->patchJson("/api/v1/member/profile/ranking-opt-in?tenantId={$tenant->id}", [
            'optIn' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.rankingOptIn', false);

        $this->assertFalse($member->fresh()->ranking_opt_in);
    }

    public function test_ranking_opt_in_returns_not_found_when_disabled(): void
    {
        [$account, $tenant] = $this->seedFixture(showMonthRank: false);
        $this->actAsMember($account);

        $this->patchJson("/api/v1/member/profile/ranking-opt-in?tenantId={$tenant->id}", [
            'optIn' => true,
        ])->assertNotFound();
    }

    public function test_cross_tenant_ranking_opt_in_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedFixture(showMonthRank: true);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-opt-in', 'show_month_rank' => true]);

        $this->actAsMember($account);

        $this->patchJson("/api/v1/member/profile/ranking-opt-in?tenantId={$otherTenant->id}", [
            'optIn' => true,
        ])->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member}
     */
    private function seedFixture(bool $showMonthRank): array
    {
        $tenant = Tenant::create([
            'name' => 'Opt-In Tenant',
            'code' => fake()->unique()->slug(1),
            'show_month_rank' => $showMonthRank,
        ]);
        $account = Account::create(['display_name' => 'Opt-In Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-OPT-IN',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'ranking_opt_in' => false,
        ]);

        return [$account, $tenant, $member];
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
