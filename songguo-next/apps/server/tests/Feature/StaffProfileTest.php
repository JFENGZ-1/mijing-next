<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_reads_own_profile(): void
    {
        [$staff] = $this->actAsStaff();

        $this->getJson('/api/v1/staff/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.displayName', 'Profile Staff')
            ->assertJsonPath('data.employeeNo', $staff->employee_no)
            ->assertJsonPath('data.tenantId', $staff->tenant_id)
            ->assertJsonPath('data.version', 1);
    }

    public function test_staff_updates_display_name(): void
    {
        [$staff] = $this->actAsStaff();

        $this->patchJson('/api/v1/staff/profile', [
            'displayName' => '新昵称',
            'version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.displayName', '新昵称')
            ->assertJsonPath('data.version', 2);

        $staff->refresh();
        $this->assertSame('新昵称', $staff->name);
        $this->assertSame('新昵称', $staff->account->fresh()->display_name);
    }

    public function test_staff_avatar_upload_updates_profile_read(): void
    {
        Storage::fake('public');
        [$staff] = $this->actAsStaff();

        $response = $this->post('/api/v1/staff/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertOk();

        $avatarUrl = $response->json('data.avatarUrl');
        $this->assertNotEmpty($avatarUrl);

        $this->getJson('/api/v1/staff/profile')
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', $avatarUrl);

        $this->assertSame($avatarUrl, $staff->account->fresh()->avatar_url);
    }

    public function test_logout_invalidates_staff_token(): void
    {
        [$staff] = $this->makeStaff([]);
        $token = $staff->account->createToken('test-device', [
            'api',
            'client:staff',
            "staff:{$staff->id}",
            "tenant:{$staff->tenant_id}",
        ]);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.loggedOut', true);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->refreshApplication();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/staff/profile')
            ->assertUnauthorized();
    }

    public function test_self_profile_endpoint_never_exposes_other_staff(): void
    {
        [$staffA, $site] = $this->makeStaff([]);
        $accountB = Account::create(['display_name' => 'Other Staff', 'status' => 'active']);
        $staffB = Staff::create([
            'tenant_id' => $staffA->tenant_id,
            'account_id' => $accountB->id,
            'employee_no' => 'EMP-OTHER',
            'name' => 'Other Staff',
            'status' => 'active',
        ]);
        $staffB->sites()->attach($site->id, ['tenant_id' => $staffA->tenant_id, 'is_primary' => true]);

        Sanctum::actingAs($staffA->account, ['api', 'client:staff', "staff:{$staffA->id}", "tenant:{$staffA->tenant_id}"]);

        $this->getJson('/api/v1/staff/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $staffA->id)
            ->assertJsonPath('data.displayName', 'Profile Staff')
            ->assertJsonMissing(['id' => $staffB->id])
            ->assertJsonMissing(['displayName' => 'Other Staff']);
    }

    public function test_member_token_cannot_read_staff_profile(): void
    {
        $account = Account::create(['display_name' => 'Member', 'status' => 'active']);
        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson('/api/v1/staff/profile')->assertForbidden();
    }

    private function actAsStaff(): array
    {
        [$staff, $site] = $this->makeStaff([]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Profile Staff', 'mobile' => '13800138000', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Profile Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        if ($permissions !== []) {
            $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Profile', 'code' => 'profile', 'status' => 'active']);
            foreach ($permissions as $code) {
                $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'identity']);
                $role->permissions()->attach($permission->id);
            }
            $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        }

        return [$staff, $site];
    }
}
