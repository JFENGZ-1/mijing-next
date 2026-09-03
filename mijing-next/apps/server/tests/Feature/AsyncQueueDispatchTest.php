<?php

namespace Tests\Feature;

use App\Enums\ExportJobStatus;
use App\Jobs\ProcessExportJob;
use App\Models\Account;
use App\Models\ExportJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Exports\ExportJobService;
use App\Support\JobActorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsyncQueueDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_export_dispatches_queue_job_when_not_sync(): void
    {
        Bus::fake();
        Storage::fake('local');
        [$staff, $site] = $this->actAsStaff(['export.member.create', 'export.job.read', 'crm.member.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/exports/members", [
            'columns' => ['name', 'memberNo'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', ExportJobStatus::Pending->value)
            ->assertJsonPath('data.downloadAvailable', false);

        Bus::assertDispatched(ProcessExportJob::class, function (ProcessExportJob $job) use ($staff) {
            return $job->staffId === $staff->id && $job->exportJobId > 0;
        });

        $this->assertDatabaseHas('export_jobs', [
            'site_id' => $site->id,
            'status' => ExportJobStatus::Pending->value,
        ]);
    }

    public function test_process_export_job_marks_job_completed(): void
    {
        Storage::fake('local');
        [$staff, $site] = $this->actAsStaff(['export.member.create', 'export.job.read', 'crm.member.read']);

        $job = ExportJob::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'type' => 'member_export',
            'status' => ExportJobStatus::Pending,
            'requested_by_staff_id' => $staff->id,
            'filters' => ['columns' => ['name', 'memberNo']],
        ]);

        app(ExportJobService::class)->runExportJob(
            $job->id,
            new JobActorContext($staff->id, 'req-test'),
            $job->filters ?? [],
        );

        $job->refresh();
        $this->assertSame(ExportJobStatus::Completed, $job->status);
        $this->assertNotNull($job->file_path);
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site}
     */
    private function actAsStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Queue Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Queue Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Queue',
            'code' => 'queue-'.fake()->unique()->slug(1),
            'status' => 'active',
        ]);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'queue']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        Sanctum::actingAs($account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$tenant->id}"]);

        return [$staff, $site];
    }
}
