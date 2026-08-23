<?php

namespace Tests\Feature\Admin;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_admin_write_is_recorded_without_request_secrets(): void
    {
        $admin = SuperAdmin::query()->create([
            'username' => 'audit.admin',
            'name' => '审计管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);

        $this->putJson('/api/v1/admin/settings/payments/wechat', [
            'version' => 0,
            'enabled' => false,
            'apiV3Key' => str_repeat('s', 32),
        ])->assertOk();

        $response = $this->getJson('/api/v1/admin/audit-logs');
        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.actor.username', 'audit.admin')
            ->assertJsonPath('data.items.0.method', 'PUT')
            ->assertJsonPath('data.items.0.statusCode', 200);

        $this->assertStringNotContainsString(str_repeat('s', 32), json_encode($response->json(), JSON_THROW_ON_ERROR));
    }
}
