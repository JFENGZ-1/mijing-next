<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_permission_can_list_rooms(): void
    {
        [, $site] = $this->actAsStaff(['site.rooms.read']);
        $room = $this->createRoom($site, 'A教室');

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $room->id)
            ->assertJsonPath('data.items.0.name', 'A教室');

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'A教室');
    }

    public function test_staff_can_create_and_update_room(): void
    {
        [, $site] = $this->actAsStaff(['site.rooms.write', 'site.rooms.read']);

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/rooms", [
            'name' => 'B教室',
            'capacity' => 15,
            'sortOrder' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'B教室')
            ->assertJsonPath('data.capacity', 15)
            ->assertJsonPath('data.version', 1);

        $roomId = $create->json('data.id');

        $this->putJson("/api/v1/staff/sites/{$site->id}/rooms/{$roomId}", [
            'version' => 1,
            'name' => 'B教室（大）',
            'capacity' => 18,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'B教室（大）')
            ->assertJsonPath('data.capacity', 18)
            ->assertJsonPath('data.version', 2);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);
        $this->createRoom($site, 'A教室');

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->postJson("/api/v1/staff/sites/{$site->id}/rooms", ['name' => '新教室'])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_rooms_are_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['site.rooms.read', 'site.rooms.write']);
        $visible = $this->createRoom($site, '本馆教室');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherRoom = Room::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'name' => '外馆教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $secondSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);
        $branchRoom = $this->createRoom($secondSite, '分店教室');

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $visible->id);

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms/{$otherRoom->id}")->assertNotFound();
        $this->putJson("/api/v1/staff/sites/{$site->id}/rooms/{$branchRoom->id}", [
            'version' => 1,
            'name' => '越权',
        ])->assertNotFound();
    }

    public function test_physical_delete_is_forbidden(): void
    {
        [, $site] = $this->actAsStaff(['site.rooms.write']);
        $room = $this->createRoom($site, 'A教室');

        $this->deleteJson("/api/v1/staff/sites/{$site->id}/rooms/{$room->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'ROOM_DELETE_FORBIDDEN');
    }

    public function test_staff_can_archive_room(): void
    {
        [, $site] = $this->actAsStaff(['site.rooms.write', 'site.rooms.read']);
        $room = $this->createRoom($site, 'A教室');

        $this->postJson("/api/v1/staff/sites/{$site->id}/rooms/{$room->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.catalogStatus', 'archived')
            ->assertJsonPath('data.archivedAt', fn ($value) => filled($value));

        $this->getJson("/api/v1/staff/sites/{$site->id}/rooms")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    private function createRoom(Site $site, string $name): Room
    {
        return Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => $name,
            'catalog_status' => CourseCatalogStatus::Active,
            'sort_order' => 0,
            'version' => 1,
        ]);
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
        $account = Account::create(['display_name' => 'Room Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Room Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Rooms', 'code' => 'rooms', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'organization']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
