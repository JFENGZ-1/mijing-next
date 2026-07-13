<?php

namespace Tests\Feature;

use App\Enums\PointLedgerDirection;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberPointAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_credit_and_debit_points_with_cached_balance(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['points.adjust'], pointsEnabled: true);

        $this->postJson($this->path($site, $member), [
            'direction' => 'credit',
            'amount' => 100,
            'reason' => '活动赠送',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.totalPoint', 100)
            ->assertJsonPath('data.created', true);

        $this->postJson($this->path($site, $member), [
            'direction' => 'debit',
            'amount' => 40,
            'reason' => '兑换扣减',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.totalPoint', 60);

        $this->assertDatabaseHas('point_ledger_entries', [
            'member_id' => $member->id,
            'direction' => PointLedgerDirection::Credit->value,
            'amount_delta' => 100,
            'actor_staff_id' => $staff->id,
        ]);
    }

    public function test_point_adjustment_is_idempotent_by_command_key(): void
    {
        [, $site, $member] = $this->actAsStaff(['points.adjust'], pointsEnabled: true);
        $commandKey = (string) Str::uuid();

        $this->postJson($this->path($site, $member), [
            'direction' => 'credit',
            'amount' => 25,
            'reason' => '重复请求',
            'commandKey' => $commandKey,
        ])->assertCreated();

        $this->postJson($this->path($site, $member), [
            'direction' => 'credit',
            'amount' => 25,
            'reason' => '重复请求',
            'commandKey' => $commandKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.totalPoint', 25);

        $this->assertSame(1, DB::table('point_ledger_entries')->where('command_key', $commandKey)->count());
    }

    public function test_staff_without_points_adjust_permission_is_denied(): void
    {
        [, $site, $member] = $this->actAsStaff(['crm.member.read'], pointsEnabled: true);

        $this->postJson($this->path($site, $member), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => '无权限',
            'commandKey' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: Member}
     */
    private function actAsStaff(array $permissions, bool $pointsEnabled): array
    {
        [$staff, $site, $member] = $this->makeStaff($permissions, $pointsEnabled);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site, $member];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site, 2: Member}
     */
    private function makeStaff(array $permissions, bool $pointsEnabled): array
    {
        $tenant = Tenant::create([
            'name' => 'Staff Points Tenant',
            'code' => fake()->unique()->slug(1),
            'points_enabled' => $pointsEnabled,
        ]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staffAccount = Account::create(['display_name' => 'Staff', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $staffAccount->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Manager', 'code' => 'manager', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'points']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        $memberAccount = Account::create(['display_name' => 'Member', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $memberAccount->id,
            'member_no' => 'MEM-STAFF-POINTS',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $member->sites()->attach($site->id, [
            'tenant_id' => $tenant->id,
            'relationship_type' => 'home',
            'status' => 'active',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        MemberCrmProfile::create(['tenant_id' => $tenant->id, 'member_id' => $member->id, 'name' => 'Member']);

        return [$staff, $site, $member];
    }

    private function path(Site $site, Member $member): string
    {
        return "/api/v1/staff/sites/{$site->id}/members/{$member->id}/point-adjustments";
    }
}
