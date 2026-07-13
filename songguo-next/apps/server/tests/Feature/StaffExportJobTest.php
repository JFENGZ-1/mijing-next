<?php

namespace Tests\Feature;

use App\Enums\CardType;
use App\Enums\ExportJobStatus;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
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
