<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\ExportJobStatus;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use App\Jobs\ProcessExportJob;
use App\Models\Account;
use App\Models\ExportJob;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Exports\ExportJobService;
use App\Services\Exports\MemberExportGenerator;
use App\Support\JobActorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffExportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_member_export_job_generates_csv_and_returns_job_id(): void
    {
        Storage::fake('local');
        [$staff, $site] = $this->actAsStaff(['export.member.create', 'export.job.read', 'crm.member.read']);

        $member = $this->createMemberAtSite($site, 'Export Member');
        $this->createActiveCard($site, $member, 'MC-EXPORT-1');

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members", [
            'columns' => ['name', 'memberNo', 'joinedAt'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ExportJobStatus::Completed->value)
            ->assertJsonPath('data.type', 'member_export')
            ->assertJsonPath('data.downloadAvailable', true);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('export_jobs', [
            'id' => $jobId,
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'status' => ExportJobStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'export.job.created',
            'subject_type' => 'export_job',
            'subject_id' => $jobId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'export.job.completed',
            'subject_type' => 'export_job',
            'subject_id' => $jobId,
        ]);

        $download = $this->get("/api/v1/staff/sites/{$site->id}/exports/jobs/{$jobId}/download");
        $download->assertOk();
        $content = method_exists($download, 'streamedContent') ? $download->streamedContent() : $download->getContent();
        $this->assertStringContainsString('Export Member', $content);
        $this->assertStringContainsString('会员号', $content);
        $this->assertStringNotContainsString('mobile_ciphertext', $content);
    }

    public function test_list_export_jobs_returns_paginated_history(): void
    {
        [$staff, $site] = $this->actAsStaff(['export.member.create', 'export.job.read']);

        ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Completed,
            'file_path' => 'exports/test.csv',
            'requested_by_staff_id' => $staff->id,
            'filters' => ['sumMode' => 'all'],
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', 'member_export')
            ->assertJsonPath('data.items.0.status', ExportJobStatus::Completed->value)
            ->assertJsonPath('data.items.0.filters.sumMode', 'all')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_staff_without_export_permissions_is_denied(): void
    {
        [, $site] = $this->actAsStaff([]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_export_endpoints_are_scoped_to_assigned_site_and_tenant(): void
    {
        Storage::fake('local');
        [$staff, $site] = $this->actAsStaff(['export.member.create', 'export.job.read']);
        $member = $this->createMemberAtSite($site, 'Scoped Export');
        $this->createActiveCard($site, $member, 'MC-SCOPED-EXPORT');

        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-export']);
        $otherSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'code' => 'other',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$otherSite->id}/exports/members")
            ->assertNotFound();

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/exports/jobs")
            ->assertNotFound();

        $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertCreated();
    }

    public function test_creator_can_download_without_export_job_read_permission(): void
    {
        Storage::fake('local');
        [$staff, $site] = $this->actAsStaff(['export.member.create']);

        $member = $this->createMemberAtSite($site, 'Creator Download');
        $this->createActiveCard($site, $member, 'MC-CREATOR-DL');

        $jobId = $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertCreated()
            ->json('data.id');

        $this->get("/api/v1/staff/sites/{$site->id}/exports/jobs/{$jobId}/download")
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'export.job.downloaded',
            'subject_type' => 'export_job',
            'subject_id' => $jobId,
        ]);
    }

    public function test_creator_can_follow_own_job_status_without_export_job_read_permission(): void
    {
        Storage::fake('local');
        [$creator, $site] = $this->actAsStaff(['export.member.create']);
        $this->createMemberAtSite($site, 'Creator Status');

        $jobId = $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.id', $jobId)
            ->assertJsonPath('data.requestedByStaffId', $creator->id)
            ->assertJsonPath('data.status', ExportJobStatus::Completed->value)
            ->assertJsonPath('data.downloadAvailable', true);
    }

    public function test_non_creator_without_export_job_read_cannot_follow_job_status(): void
    {
        Storage::fake('local');
        [$creator, $site] = $this->makeStaff(['export.member.create']);
        Sanctum::actingAs($creator->account, ['api', 'client:staff', "staff:{$creator->id}", "tenant:{$creator->tenant_id}"]);
        $jobId = $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertCreated()
            ->json('data.id');

        [$otherStaff] = $this->makeStaff(['export.member.create'], $site->tenant_id, $site);
        Sanctum::actingAs($otherStaff->account, ['api', 'client:staff', "staff:{$otherStaff->id}", "tenant:{$otherStaff->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/{$jobId}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_non_creator_without_export_job_read_cannot_download(): void
    {
        Storage::fake('local');
        [$creator, $site] = $this->makeStaff(['export.member.create']);
        $member = $this->createMemberAtSite($site, 'Restricted Download');
        $this->createActiveCard($site, $member, 'MC-RESTRICTED-DL');

        Sanctum::actingAs($creator->account, ['api', 'client:staff', "staff:{$creator->id}", "tenant:{$creator->tenant_id}"]);
        $jobId = $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members")
            ->assertCreated()
            ->json('data.id');

        [$otherStaff] = $this->makeStaff(['export.member.create'], $site->tenant_id, $site);
        Sanctum::actingAs($otherStaff->account, ['api', 'client:staff', "staff:{$otherStaff->id}", "tenant:{$otherStaff->tenant_id}"]);

        $this->get("/api/v1/staff/sites/{$site->id}/exports/jobs/{$jobId}/download")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_reader_can_follow_and_download_another_staff_export_job(): void
    {
        Storage::fake('local');
        [$creator, $site] = $this->makeStaff([]);
        $path = 'exports/reader-job.csv';
        Storage::disk('local')->put($path, "name\nExport Member");

        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Completed,
            'file_path' => $path,
            'requested_by_staff_id' => $creator->id,
            'filters' => [],
            'completed_at' => now(),
        ]);

        [$reader] = $this->makeStaff(['export.job.read'], $site->tenant_id, $site);
        Sanctum::actingAs($reader->account, ['api', 'client:staff', "staff:{$reader->id}", "tenant:{$reader->tenant_id}"]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->id);

        $this->get("/api/v1/staff/sites/{$site->id}/exports/jobs/{$job->id}/download")
            ->assertOk();
    }

    public function test_export_job_show_and_download_are_scoped_to_site_and_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff(['export.job.read']);
        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Pending,
            'requested_by_staff_id' => $staff->id,
            'filters' => [],
        ]);

        $otherSite = Site::create([
            'tenant_id' => $site->tenant_id,
            'name' => 'Other Site',
            'code' => 'other-site',
            'status' => 'active',
        ]);
        $staff->sites()->attach($otherSite->id, [
            'tenant_id' => $site->tenant_id,
            'is_primary' => false,
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/exports/jobs/{$job->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/exports/jobs/{$job->id}/download")
            ->assertNotFound();

        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'code' => 'other-export-tenant']);
        $otherTenantSite = Site::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Site',
            'code' => 'other-tenant-site',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/staff/sites/{$otherTenantSite->id}/exports/jobs/{$job->id}")
            ->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherTenantSite->id}/exports/jobs/{$job->id}/download")
            ->assertNotFound();
    }

    public function test_unauthorized_download_does_not_reveal_pending_or_missing_file_state(): void
    {
        [$creator, $site] = $this->makeStaff([]);
        $pendingJob = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Pending,
            'requested_by_staff_id' => $creator->id,
            'filters' => [],
        ]);
        $missingFileJob = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Completed,
            'file_path' => null,
            'requested_by_staff_id' => $creator->id,
            'filters' => [],
            'completed_at' => now(),
        ]);

        [$otherStaff] = $this->makeStaff([], $site->tenant_id, $site);
        Sanctum::actingAs($otherStaff->account, ['api', 'client:staff', "staff:{$otherStaff->id}", "tenant:{$otherStaff->tenant_id}"]);

        foreach ([$pendingJob, $missingFileJob] as $job) {
            $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/{$job->id}/download")
                ->assertForbidden()
                ->assertJsonPath('code', 'PERMISSION_DENIED');
        }
    }

    public function test_export_job_routes_reject_non_numeric_job_ids(): void
    {
        [, $site] = $this->actAsStaff(['export.job.read']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/not-a-number")
            ->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/exports/jobs/not-a-number/download")
            ->assertNotFound();
    }

    public function test_transient_export_failure_returns_to_pending_and_can_retry(): void
    {
        [$staff, $site] = $this->makeStaff([]);
        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Pending,
            'requested_by_staff_id' => $staff->id,
            'filters' => [],
        ]);

        $calls = 0;
        $generator = $this->mock(MemberExportGenerator::class);
        $generator->shouldReceive('generate')
            ->twice()
            ->andReturnUsing(function () use (&$calls): string {
                $calls++;
                if ($calls === 1) {
                    throw new \RuntimeException('TRANSIENT_EXPORT_FAILURE');
                }

                return 'exports/retried.csv';
            });

        $service = app(ExportJobService::class);
        $queuedJob = new ProcessExportJob($job->id, $staff->id, 'retry-request');
        try {
            $queuedJob->handle($service);
            $this->fail('The first attempt should throw so the queue can retry it.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('TRANSIENT_EXPORT_FAILURE', $exception->getMessage());
        }

        $job->refresh();
        $this->assertSame(ExportJobStatus::Pending, $job->status);
        $this->assertNull($job->completed_at);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'export.job.failed',
            'subject_type' => 'export_job',
            'subject_id' => $job->id,
        ]);

        (new ProcessExportJob($job->id, $staff->id, 'retry-request'))->handle($service);

        $job->refresh();
        $this->assertSame(ExportJobStatus::Completed, $job->status);
        $this->assertSame('exports/retried.csv', $job->file_path);
        $this->assertSame(2, $calls);
    }

    public function test_final_export_failure_is_recorded_after_last_attempt(): void
    {
        [$staff, $site] = $this->makeStaff([]);
        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Pending,
            'requested_by_staff_id' => $staff->id,
            'filters' => [],
        ]);

        $generator = $this->mock(MemberExportGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('FINAL_EXPORT_FAILURE'));

        try {
            app(ExportJobService::class)->runExportJob(
                $job->id,
                new JobActorContext($staff->id, 'final-request'),
                [],
                3,
                3,
            );
            $this->fail('The final attempt should still rethrow the export failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('FINAL_EXPORT_FAILURE', $exception->getMessage());
        }

        $job->refresh();
        $this->assertSame(ExportJobStatus::Failed, $job->status);
        $this->assertNotNull($job->completed_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'export.job.failed',
            'subject_type' => 'export_job',
            'subject_id' => $job->id,
        ]);
    }

    public function test_completed_export_job_metadata_cannot_be_deleted(): void
    {
        [$staff, $site] = $this->actAsStaff(['export.job.read']);

        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Completed,
            'file_path' => 'exports/test.csv',
            'requested_by_staff_id' => $staff->id,
            'filters' => [],
            'completed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXPORT_JOB_IMMUTABLE');
        $job->delete();
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions, ?int $tenantId = null, ?Site $existingSite = null): array
    {
        $tenant = $tenantId
            ? Tenant::findOrFail($tenantId)
            : Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);

        $account = Account::create(['display_name' => 'Export Admin', 'status' => 'active']);
        $site = $existingSite ?? Site::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'main',
            'status' => 'active',
        ]);

        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Export Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Export',
            'code' => 'export-'.fake()->unique()->slug(1),
            'status' => 'active',
        ]);

        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'export']);
            $role->permissions()->attach($permission->id);
        }

        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createMemberAtSite(Site $site, string $name): Member
    {
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'M'.strtoupper(fake()->unique()->bothify('??####')),
            'status' => 'active',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => $name,
        ]);
        DB::table('member_sites')->insert([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'relationship_type' => 'registered',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member->load('crmProfile');
    }

    private function createActiveCard(Site $site, Member $member, string $cardNo): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_no' => $cardNo,
            'card_type' => CardType::Period,
            'status' => MemberCardStatus::Active,
            'member_visibility' => MemberCardVisibility::Visible,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'issued_at' => now(),
            'product_snapshot' => ['name' => 'Test Card'],
        ]);
    }
}
