<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\PayrollRecomputeJobStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CompensationRole;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\PayrollRecomputeJob;
use App\Models\PayrollReportSnapshot;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Payroll\PayrollReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPayrollReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_report_returns_amounts_from_seed_appointments_and_rules(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.report.read', 'payroll.config.write']);
        $coach = $this->createSiteStaff($site, 'Paid Coach');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Payroll Member');
        $groupCourse = $this->createCourse($site, $viewer, CourseType::Group, 'Payroll Group');

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'fixed_hours',
        ])->assertOk();

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}", [
            'groupCourses' => [
                ['courseId' => $groupCourse->id, 'unitPriceCents' => 15000],
            ],
            'privateCourses' => [],
        ])->assertOk();

        $this->seedCompletedGroupSession(
            $site,
            $groupCourse,
            $coach,
            $member,
            now()->startOfMonth()->addDays(2),
        );
        $this->seedCompletedGroupSession(
            $site,
            $groupCourse,
            $coach,
            $member,
            now()->startOfMonth()->addDays(4),
        );

        $year = now()->year;
        $month = now()->month;

        $list = $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.source', 'computed')
            ->assertJsonPath('data.items.0.staffId', $coach->id)
            ->assertJsonPath('data.items.0.groupSessionCount', 2)
            ->assertJsonPath('data.items.0.totalPayCents', 30000);

        $detail = $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports/{$coach->id}?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.source', 'computed')
            ->assertJsonPath('data.totals.totalPayCents', 30000)
            ->assertJsonPath('data.totals.groupSessionCount', 2)
            ->assertJsonPath('data.courseLines.0.courseId', $groupCourse->id)
            ->assertJsonPath('data.courseLines.0.payCents', 30000);

        $commission = $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/course-commission?year={$year}&month={$month}&staffId={$coach->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'computed')
            ->assertJsonPath('data.totals.commissionCents', 30000)
            ->assertJsonPath('data.items.0.deliveredCount', 2);
    }

    public function test_sales_report_returns_commission_from_seed_orders_and_flat_rate_config(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.report.read', 'payroll.config.write']);
        $salesStaff = $this->createSiteStaff($site, 'Sales Payroll');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Buyer');

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/sales-config", [
            'enabled' => true,
            'mode' => 'flat_rate',
            'settings' => [
                'newSaleRatePercent' => 10,
                'renewalRatePercent' => 5,
            ],
        ])->assertOk();

        $this->createPaidOrder($site, $member, $salesStaff, 1000, ['saleCategory' => 'new']);
        $this->createPaidOrder($site, $member, $salesStaff, 500, ['saleCategory' => 'renewal']);

        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/sales-reports?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.source', 'computed')
            ->assertJsonPath('data.items.0.staffId', $salesStaff->id)
            ->assertJsonPath('data.items.0.cardSalesCount', 2)
            ->assertJsonPath('data.items.0.revenueCents', 150000)
            ->assertJsonPath('data.items.0.commissionCents', 12500);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/sales-reports/{$salesStaff->id}?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.totals.commissionCents', 12500)
            ->assertJsonPath('data.totals.newSaleCommissionCents', 10000)
            ->assertJsonPath('data.totals.renewalCommissionCents', 2500)
            ->assertJsonCount(2, 'data.orderLines');
    }

    public function test_private_payroll_uses_completed_fulfillment_and_includes_all_delivery_staff(): void
    {
        $this->travelTo(now()->startOfMonth()->addDay()->setTime(9, 0));
        [$viewer, $site] = $this->actAsStaff(['payroll.report.read', 'payroll.config.write']);
        $primary = $this->createSiteStaff($site, 'Primary Coach');
        $secondary = $this->createSiteStaff($site, 'Secondary Coach');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Private Member');
        $course = $this->createCourse($site, $viewer, CourseType::Private, 'Private Payroll');
        $role = CompensationRole::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'code' => 'private-delivery',
            'name' => '私教授课者',
            'role_type' => 'delivery',
            'status' => 'active',
            'version' => 1,
        ]);

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'fixed_hours',
        ])->assertOk();
        foreach ([$primary, $secondary] as $coach) {
            $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}", [
                'groupCourses' => [],
                'privateCourses' => [['courseId' => $course->id, 'unitPriceCents' => 7000]],
            ])->assertOk();
        }

        foreach ([AppointmentStatus::Confirmed, AppointmentStatus::Absent] as $index => $status) {
            $future = ScheduleSession::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'course_id' => $course->id,
                'coach_staff_id' => $primary->id,
                'starts_at' => now()->addDays($index + 1),
                'ends_at' => now()->addDays($index + 1)->addHour(),
                'capacity' => 1,
                'booked_count' => 1,
                'status' => ScheduleSessionStatus::Scheduled,
                'session_kind' => ScheduleSessionKind::Private,
                'version' => 1,
            ]);
            $this->createAppointment($site, $future, $member, $status);
        }

        $completed = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $primary->id,
            'delivery_role_id' => $role->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'capacity' => 1,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Private,
            'version' => 1,
        ]);
        $this->createAppointment($site, $completed, $member, AppointmentStatus::Completed);
        foreach ([[$primary, true], [$secondary, false]] as [$coach, $isPrimary]) {
            ScheduleSessionStaffAssignment::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'schedule_session_id' => $completed->id,
                'staff_id' => $coach->id,
                'compensation_role_id' => $role->id,
                'is_primary' => $isPrimary,
                'allocation_bps' => 5000,
                'assignment_version' => 1,
            ]);
        }

        $year = now()->year;
        $month = now()->month;
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PayrollReportEngine::class)->computeCoachReportSummaries($viewer, $site, $year, $month);
        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
        DB::disableQueryLog();

        $list = $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports?year={$year}&month={$month}")
            ->assertOk();
        $rows = collect($list->json('data.items'))->keyBy('staffId');
        $this->assertSame(1, $rows[$primary->id]['privateSessionCount']);
        $this->assertSame(7000, $rows[$primary->id]['totalPayCents']);
        $this->assertSame(1, $rows[$secondary->id]['privateSessionCount']);
        $this->assertSame(7000, $rows[$secondary->id]['totalPayCents']);
    }

    public function test_recompute_job_is_idempotent_and_persists_snapshots(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.recompute.execute', 'payroll.report.read', 'payroll.config.write']);
        $coach = $this->createSiteStaff($site, 'Snapshot Coach');
        $member = $this->createMemberAtSite($viewer->tenant_id, $site, 'Snapshot Member');
        $course = $this->createCourse($site, $viewer, CourseType::Group, 'Snapshot Group');

        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-config", [
            'enabled' => true,
            'mode' => 'fixed_hours',
        ]);
        $this->putJson("/api/v1/staff/sites/{$site->id}/payroll/coach-rules?staffId={$coach->id}", [
            'groupCourses' => [['courseId' => $course->id, 'unitPriceCents' => 8000]],
            'privateCourses' => [],
        ]);
        $this->seedCompletedGroupSession($site, $course, $coach, $member, now()->startOfMonth()->addDay());

        $year = now()->year;
        $month = now()->month;
        $commandKey = 'payroll-recompute-'.Str::uuid();

        $first = $this->postJson("/api/v1/staff/sites/{$site->id}/payroll/recompute-jobs", [
            'year' => $year,
            'month' => $month,
            'scope' => 'coach',
            'staffId' => $coach->id,
            'commandKey' => $commandKey,
        ])->assertCreated()
            ->assertJsonPath('data.status', PayrollRecomputeJobStatus::Completed->value)
            ->assertJsonPath('data.scope', 'coach');

        $jobId = $first->json('data.id');

        $second = $this->postJson("/api/v1/staff/sites/{$site->id}/payroll/recompute-jobs", [
            'year' => $year,
            'month' => $month,
            'scope' => 'coach',
            'staffId' => $coach->id,
            'commandKey' => $commandKey,
        ])->assertCreated()
            ->assertJsonPath('data.id', $jobId);

        $this->assertDatabaseCount('payroll_recompute_jobs', 1);
        $this->assertDatabaseHas('payroll_report_snapshots', [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'year' => $year,
            'month' => $month,
            'staff_id' => $coach->id,
            'recompute_job_id' => $jobId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payroll.recompute.job.created',
            'subject_type' => 'payroll_recompute_job',
            'subject_id' => $jobId,
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.source', 'snapshot')
            ->assertJsonPath('data.items.0.totalPayCents', 8000);
    }

    public function test_recompute_job_cannot_target_unassigned_site(): void
    {
        [$viewer, $site] = $this->actAsStaff(['payroll.recompute.execute']);
        $otherSite = Site::create([
            'tenant_id' => $viewer->tenant_id,
            'name' => 'Other Payroll Site',
            'code' => 'payroll-other-site',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$otherSite->id}/payroll/recompute-jobs", [
            'year' => now()->year,
            'month' => now()->month,
            'scope' => 'site',
            'commandKey' => 'blocked-site-scope',
        ])->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/payroll/recompute-jobs")
            ->assertNotFound();
    }

    public function test_payroll_report_endpoints_require_permissions(): void
    {
        [$viewer, $site] = $this->actAsStaff([]);
        $coach = $this->createSiteStaff($site, 'Denied Coach');
        $year = now()->year;
        $month = now()->month;

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports?year={$year}&month={$month}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/coach-reports/{$coach->id}?year={$year}&month={$month}")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/sales-reports?year={$year}&month={$month}")
            ->assertForbidden();

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/course-commission?year={$year}&month={$month}&staffId={$coach->id}")
            ->assertForbidden();

        $this->postJson("/api/v1/staff/sites/{$site->id}/payroll/recompute-jobs", [
            'year' => $year,
            'month' => $month,
            'scope' => 'site',
            'commandKey' => 'denied-recompute',
        ])->assertForbidden();

        [, $site] = $this->actAsStaff(['payroll.report.read']);
        $this->postJson("/api/v1/staff/sites/{$site->id}/payroll/recompute-jobs", [
            'year' => $year,
            'month' => $month,
            'scope' => 'site',
            'commandKey' => 'read-only-recompute',
        ])->assertForbidden();
    }

    public function test_completed_payroll_snapshot_is_immutable(): void
    {
        [$staff, $site] = $this->actAsStaff(['payroll.recompute.execute']);
        $coach = $this->createSiteStaff($site, 'Immutable Coach');

        $job = PayrollRecomputeJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'status' => PayrollRecomputeJobStatus::Completed,
            'scope' => 'coach',
            'year' => now()->year,
            'month' => now()->month,
            'staff_id' => $coach->id,
            'command_key' => 'immutable-job',
            'requested_by_staff_id' => $staff->id,
            'completed_at' => now(),
        ]);

        $snapshot = PayrollReportSnapshot::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'year' => now()->year,
            'month' => now()->month,
            'report_type' => 'coach',
            'staff_id' => $coach->id,
            'payload' => ['totalPayCents' => 100],
            'recompute_job_id' => $job->id,
            'computed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PAYROLL_REPORT_SNAPSHOT_IMMUTABLE');
        $snapshot->delete();
    }

    public function test_list_recompute_jobs_returns_paginated_history(): void
    {
        [$staff, $site] = $this->actAsStaff(['payroll.recompute.execute']);

        PayrollRecomputeJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'status' => PayrollRecomputeJobStatus::Completed,
            'scope' => 'sales',
            'year' => now()->year,
            'month' => now()->month,
            'command_key' => 'listed-job',
            'requested_by_staff_id' => $staff->id,
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/payroll/recompute-jobs")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.scope', 'sales')
            ->assertJsonPath('data.pagination.total', 1);
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
        $account = Account::create(['display_name' => 'Payroll Report Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Payroll Report Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'PayrollReport', 'code' => 'payroll-report', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'payroll']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createSiteStaff(Site $site, string $name): Staff
    {
        $staff = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => $name, 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => $name,
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $site->tenant_id, 'is_primary' => false]);

        return $staff;
    }

    private function createMemberAtSite(int $tenantId, Site $site, string $name): Member
    {
        $member = Member::create([
            'tenant_id' => $tenantId,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    private function createCourse(Site $site, Staff $staff, CourseType $type, string $name): Course
    {
        return Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => $type,
            'name' => $name,
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
            'created_by_staff_id' => $staff->id,
        ]);
    }

    private function seedCompletedGroupSession(
        Site $site,
        Course $course,
        Staff $coach,
        Member $member,
        $startsAt,
    ): ScheduleSession {
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 12,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Completed,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);
        $this->createAppointment($site, $session, $member, AppointmentStatus::Completed);

        return $session;
    }

    private function createAppointment(
        Site $site,
        ScheduleSession $session,
        Member $member,
        AppointmentStatus $status,
    ): Appointment {
        return Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => $status,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
    }

    private function createPaidOrder(
        Site $site,
        Member $member,
        Staff $salesStaff,
        float $amount,
        array $metadata = [],
    ): MemberCardOrder {
        return MemberCardOrder::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'order_no' => 'ORD-'.strtoupper(Str::random(8)),
            'amount' => $amount,
            'status' => MemberCardOrderStatus::Paid,
            'created_by_staff_id' => $salesStaff->id,
            'metadata' => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
