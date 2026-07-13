<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BookingPolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffBookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_defaults_then_persisted_policy_after_put(): void
    {
        [, $site] = $this->actAsStaff(['booking.policy.read', 'booking.policy.write']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking-policy")
            ->assertOk()
            ->assertJsonPath('data.version', 0)
            ->assertJsonPath('data.group.advanceBookingDays', 7)
            ->assertJsonPath('data.group.waitlistEnabled', true)
            ->assertJsonPath('data.private.advanceBookingDays', 14)
            ->assertJsonPath('data.updatedAt', null);

        $payload = $this->policyPayload(0, [
            'group' => [
                'advanceBookingDays' => 10,
                'waitlistEnabled' => false,
            ],
            'private' => [
                'slotIntervalMinutes' => 15,
            ],
            'rules' => ['note' => 'site policy'],
        ]);

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.group.advanceBookingDays', 10)
            ->assertJsonPath('data.group.waitlistEnabled', false)
            ->assertJsonPath('data.private.slotIntervalMinutes', 15)
            ->assertJsonPath('data.rules.note', 'site policy')
            ->assertJsonPath('data.updatedAt', fn ($value) => $value !== null);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking-policy")
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.group.advanceBookingDays', 10)
            ->assertJsonPath('data.group.waitlistEnabled', false);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site] = $this->actAsStaff(['crm.member.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/booking-policy")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0))
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_booking_policy_is_isolated_by_tenant_and_site(): void
    {
        [$staff, $site] = $this->actAsStaff(['booking.policy.read', 'booking.policy.write']);

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => fake()->unique()->slug(1)]);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        BookingPolicy::create([
            'tenant_id' => $otherTenant->id,
            'site_id' => $otherSite->id,
            'version' => 1,
            'policy' => BookingPolicyService::defaultPolicy(),
            'rules' => [],
        ]);

        $branchSite = Site::create(['tenant_id' => $staff->tenant_id, 'name' => 'Branch', 'code' => 'branch', 'status' => 'active']);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/booking-policy")->assertNotFound();
        $this->putJson("/api/v1/staff/sites/{$branchSite->id}/booking-policy", $this->policyPayload(0))
            ->assertNotFound();
    }

    public function test_validation_rejects_negative_and_illogical_cutoffs(): void
    {
        [, $site] = $this->actAsStaff(['booking.policy.write']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0, [
            'group' => [
                'advanceBookingDays' => -1,
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0, [
            'group' => [
                'bookingCutoffMinutesBeforeStart' => 120,
                'cancelCutoffMinutesBeforeStart' => 30,
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0, [
            'group' => [
                'autoCancelUnderMinStudentsMinutesBeforeStart' => 240,
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_version_conflict_on_stale_update(): void
    {
        [, $site] = $this->actAsStaff(['booking.policy.write']);

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0))
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->putJson("/api/v1/staff/sites/{$site->id}/booking-policy", $this->policyPayload(0))
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_POLICY_VERSION_CONFLICT');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function policyPayload(int $version, array $overrides = []): array
    {
        $defaults = BookingPolicyService::defaultPolicy();
        $defaults['version'] = $version;

        return array_replace_recursive($defaults, $overrides);
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
        $account = Account::create(['display_name' => 'Policy Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Policy Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Booking Policy', 'code' => 'booking-policy', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
