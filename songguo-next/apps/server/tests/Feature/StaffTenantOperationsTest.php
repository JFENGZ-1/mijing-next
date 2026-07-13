<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffTenantOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_agreement_read_and_write(): void
    {
        [, $site] = $this->actAsStaff([
            'tenant.legal.membership-agreement.read',
            'tenant.legal.membership-agreement.write',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/legal/membership-agreement")
            ->assertOk()
            ->assertJsonPath('data.html', '');

        $this->putJson("/api/v1/staff/sites/{$site->id}/legal/membership-agreement", [
            'html' => '<p>会员协议正文</p>',
        ])
            ->assertOk()
            ->assertJsonPath('data.html', '<p>会员协议正文</p>');
    }

    public function test_closure_calendar_crud(): void
    {
        [$staff, $site] = $this->actAsStaff([
            'tenant.site.closure-calendar.read',
            'tenant.site.closure-calendar.write',
        ]);

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/closure-calendar", [
            'reason' => '春节假期',
            'beginDate' => now()->addDays(2)->toDateString(),
            'endDate' => now()->addDays(5)->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.reason', '春节假期')
            ->assertJsonPath('data.lifecycleStatus', 'scheduled');

        $closureId = $create->json('data.id');

        $this->getJson("/api/v1/staff/sites/{$site->id}/closure-calendar")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.summary.total', 1);

        $this->patchJson("/api/v1/staff/sites/{$site->id}/closure-calendar/{$closureId}", [
            'reason' => '春节放假',
            'status' => 'cancelled',
        ])
            ->assertOk()
            ->assertJsonPath('data.lifecycleStatus', 'cancelled');
    }

    public function test_staff_vacation_rollup_and_detail(): void
    {
        [$actor, $site] = $this->actAsStaff([
            'tenant.staff.vacation.read',
            'tenant.staff.vacation.write',
        ]);

        $coach = $this->createCoach($site, '王教练');

        $this->postJson("/api/v1/staff/sites/{$site->id}/staff/{$coach->id}/vacations", [
            'beginAt' => now()->addDay()->toIso8601String(),
            'endAt' => now()->addDays(3)->toIso8601String(),
            'groupBookingPolicy' => 'block',
            'privateBookingPolicy' => 'allow',
            'remark' => '年假',
        ])
            ->assertCreated()
            ->assertJsonPath('data.remark', '年假');

        $rollup = $this->getJson("/api/v1/staff/sites/{$site->id}/staff-vacations")
            ->assertOk()
            ->json('data.items');

        $coachRow = collect($rollup)->firstWhere('staff.id', $coach->id);
        $this->assertNotNull($coachRow);
        $this->assertSame('王教练', $coachRow['staff']['displayName']);
        $this->assertCount(1, $coachRow['vacations']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff/{$coach->id}/vacations")
            ->assertOk()
            ->assertJsonPath('data.staff.id', $coach->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_notification_channel_config_read_and_write(): void
    {
        [$staff, $site] = $this->actAsStaff([
            'notification.channel.config.read',
            'notification.channel.config.write',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/notification-channels")
            ->assertOk()
            ->assertJsonStructure(['data' => ['channels', 'managerRecipients']]);

        $this->putJson("/api/v1/staff/sites/{$site->id}/notification-channels", [
            'channels' => [
                ['key' => 'member_appointment_success', 'enabled' => false],
            ],
            'managerStaffIds' => [$staff->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.channels.0.enabled', false)
            ->assertJsonPath('data.managerRecipients.0.id', $staff->id);
    }

    public function test_site_notice_crud_and_archive(): void
    {
        [, $site] = $this->actAsStaff([
            'notice.announcement.read',
            'notice.announcement.write',
        ]);

        $create = $this->postJson("/api/v1/staff/sites/{$site->id}/notices", [
            'title' => '暑期课程通知',
            'body' => '暑期课程安排已更新',
            'displayDays' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.displayStatus', 'active');

        $noticeId = $create->json('data.id');

        $this->getJson("/api/v1/staff/sites/{$site->id}/notices")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->patchJson("/api/v1/staff/sites/{$site->id}/notices/{$noticeId}", [
            'title' => '暑期通知',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', '暑期通知');

        $this->postJson("/api/v1/staff/sites/{$site->id}/notices/{$noticeId}/archive")
            ->assertOk()
            ->assertJsonPath('data.displayStatus', 'expired');
    }

    public function test_payment_marketing_static_hub_payload(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payment-marketing")
            ->assertOk()
            ->assertJsonStructure(['data' => ['cards', 'supportHint']]);
    }

    public function test_operations_endpoints_require_permissions(): void
    {
        [, $site] = $this->actAsStaff(['tenant.settings.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/legal/membership-agreement")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/closure-calendar")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/staff-vacations")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/notification-channels")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/notices")
            ->assertForbidden();
    }

    public function test_settings_hub_lists_operations_items_with_routes(): void
    {
        [, $site] = $this->actAsStaff([
            'tenant.settings.read',
            'tenant.settings.defaults.read',
            'tenant.settings.operations.read',
            'tenant.legal.membership-agreement.read',
            'tenant.site.closure-calendar.read',
            'tenant.staff.vacation.read',
            'notice.announcement.read',
            'notification.channel.config.read',
            'export.member.create',
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/settings-hub")
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'membership-agreement',
                'route' => '/pages/settings/legal/membership-agreement/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'site-closures',
                'route' => '/pages/settings/operations/closure-calendar/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'announcements',
                'route' => '/pages/settings/operations/notices/index',
                'implemented' => true,
            ])
            ->assertJsonFragment([
                'key' => 'data-export',
                'route' => '/pages/report/exports/index',
                'implemented' => true,
            ]);
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Ops Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Ops Admin',
            'status' => 'active',
        ]);
        $staff->setRelation('tenant', $tenant);
        $staff->sites()->attach($site->id, [
            'tenant_id' => $tenant->id,
            'is_primary' => true,
            'capabilities' => json_encode(['coach', 'sales'], JSON_THROW_ON_ERROR),
        ]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Ops', 'code' => 'ops', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'tenant-config']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createCoach(Site $site, string $name): Staff
    {
        $account = Account::create(['display_name' => $name, 'status' => 'active']);
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => 'active',
        ]);
        $coach->sites()->attach($site->id, [
            'tenant_id' => $site->tenant_id,
            'is_primary' => false,
            'capabilities' => json_encode(['coach'], JSON_THROW_ON_ERROR),
        ]);

        return $coach;
    }
}
