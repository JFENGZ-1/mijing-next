<?php

namespace Tests\Feature\Admin;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = SuperAdmin::query()->create([
            'username' => 'queue.admin',
            'name' => '队列管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);
        config(['queue.connections.database.table' => 'jobs']);
    }

    public function test_queue_overview_and_job_list_use_real_queue_tables(): void
    {
        $now = now()->timestamp;
        DB::table('jobs')->insert([
            [
                'queue' => 'exports',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\ExportPayroll']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now - 120,
            ],
            [
                'queue' => 'exports',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateReport']),
                'attempts' => 1,
                'reserved_at' => $now - 10,
                'available_at' => $now - 30,
                'created_at' => $now - 40,
            ],
            [
                'queue' => 'notifications',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\SendReminder']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now + 300,
                'created_at' => $now,
            ],
        ]);

        $this->getJson('/api/v1/admin/queues/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.ready', 1)
            ->assertJsonPath('data.totals.reserved', 1)
            ->assertJsonPath('data.totals.delayed', 1);

        $this->getJson('/api/v1/admin/queues/jobs?status=ready')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.name', 'App\\Jobs\\ExportPayroll');
    }

    public function test_super_admin_can_retry_or_forget_failed_job_without_exposing_payload(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'exports',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\ExportPayroll',
                'data' => ['command' => 'serialized-sensitive-payload'],
            ]),
            'exception' => "RuntimeException: export failed\nfull stack with sensitive details",
            'failed_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/queues/failed')
            ->assertOk()
            ->assertJsonPath('data.items.0.uuid', $uuid)
            ->assertJsonPath('data.items.0.exception', 'RuntimeException: export failed')
            ->assertJsonMissing(['payload']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => [$uuid]])
            ->andReturn(0);
        $this->postJson("/api/v1/admin/queues/failed/{$uuid}/retry")
            ->assertOk()
            ->assertJsonPath('data.retried', true);

        $this->deleteJson("/api/v1/admin/queues/failed/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.forgotten', true);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }
}
