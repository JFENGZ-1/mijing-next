<?php

namespace Tests\Feature;

use App\Enums\PointLedgerDirection;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberPointBalance;
use App\Models\MemberProfile;
use App\Models\PointLedgerEntry;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class MemberPointLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_lists_points_ledger_with_balance_summary(): void
    {
        [$account, $tenant, $member] = $this->seedPointsFixture(pointsEnabled: true);
        $tenant->update(['points_description_text' => '<p>积分说明</p>']);
        $this->seedLedgerEntry($member, PointLedgerDirection::Credit, 100, '签到奖励');
        $this->seedLedgerEntry($member, PointLedgerDirection::Debit, 30, '积分兑换');
        MemberPointBalance::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'balance' => 70,
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/points/ledger?tenantId={$tenant->id}&page=1")
            ->assertOk()
            ->assertJsonPath('data.totalPoint', 70)
            ->assertJsonPath('data.descriptionText', '<p>积分说明</p>')
            ->assertJsonPath('data.displayName', 'Points Member')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.amountDelta', -30)
            ->assertJsonPath('data.items.1.amountDelta', 100)
            ->assertJsonPath('data.pagination.page', 1);
    }

    public function test_points_ledger_returns_not_found_when_points_disabled(): void
    {
        [$account, $tenant] = $this->seedPointsFixture(pointsEnabled: false);
        $this->actAsMember($account);

        $this->getJson("/api/v1/member/points/ledger?tenantId={$tenant->id}")
            ->assertNotFound();
    }

    public function test_cross_tenant_points_ledger_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedPointsFixture(pointsEnabled: true);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-points', 'points_enabled' => true]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/points/ledger?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    public function test_point_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        [, $tenant, $member] = $this->seedPointsFixture(pointsEnabled: true);
        $entry = $this->seedLedgerEntry($member, PointLedgerDirection::Credit, 10, '测试');

        try {
            $entry->update(['reason' => 'mutated']);
            $this->fail('Expected point ledger update to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Expected point ledger delete to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('point_ledger_entries', ['id' => $entry->id, 'reason' => '测试']);
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member}
     */
    private function seedPointsFixture(bool $pointsEnabled): array
    {
        $tenant = Tenant::create([
            'name' => 'Points Tenant',
            'code' => fake()->unique()->slug(1),
            'points_enabled' => $pointsEnabled,
        ]);
        $account = Account::create(['display_name' => 'Points Member', 'status' => 'active']);
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Points Member']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-POINTS',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$account, $tenant, $member];
    }

    private function seedLedgerEntry(Member $member, PointLedgerDirection $direction, int $amount, string $reason): PointLedgerEntry
    {
        return PointLedgerEntry::create([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'amount_delta' => $amount,
            'direction' => $direction,
            'reason' => $reason,
            'command_key' => (string) Str::uuid(),
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
