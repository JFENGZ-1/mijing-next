<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberPrivateCoachBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_load_private_coach_time_slots(): void
    {
        [$member, $account, $site, $coachStaffId, $courseId] = $this->seedMemberPrivateFixture();
        Sanctum::actingAs($account, ['api', 'client:member']);

        $date = now()->addDays(2)->format('Y-m-d');
        $this->getJson("/api/v1/member/booking/private-coaches/time-slots?tenantId={$site->tenant_id}&siteId={$site->id}&coachStaffId={$coachStaffId}&date={$date}&courseId={$courseId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['slots', 'limits' => ['privateLastBookableDate']]]);
    }

    /**
     * @return array{0: Member, 1: Account, 2: Site, 3: int, 4: int}
     */
    private function seedMemberPrivateFixture(): array
    {
        $tenant = Tenant::create(['name' => 'T', 'code' => 't']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'S', 'code' => 's', 'status' => 'active']);
        $account = Account::create(['display_name' => 'M', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'M1',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'account_id' => $account->id,
        ]);

        [$staff] = $this->makeStaff($site, ['course-catalog.write', 'booking.appointment.create']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$tenant->id}"]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'C', 'status' => 'active'])->id,
            'employee_no' => 'C1',
            'name' => 'Coach',
            'status' => 'active',
        ]);
        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'per_course',
            'courses' => [['name' => '私教', 'durationMinutes' => 60, 'feeList' => []]],
        ])->assertOk()->json('data');

        return [$member, $account, $site, $coach->id, $profile['courses'][0]['id']];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff}
     */
    private function makeStaff(Site $site, array $permissions): array
    {
        $account = Account::create(['display_name' => 'Admin', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => $account->id,
            'employee_no' => 'E1',
            'name' => 'Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $site->tenant_id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $site->tenant_id, 'name' => 'R', 'code' => 'r', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $site->tenant_id, 'site_id' => null]);

        return [$staff];
    }
}
