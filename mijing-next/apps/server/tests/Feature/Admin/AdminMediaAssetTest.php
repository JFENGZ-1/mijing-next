<?php

namespace Tests\Feature\Admin;

use App\Models\MediaAsset;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMediaAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['admin.media_disk' => 'public']);
        $admin = SuperAdmin::query()->create([
            'username' => 'media.admin',
            'name' => '资源管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);
    }

    public function test_super_admin_can_upload_publish_and_archive_image(): void
    {
        $upload = $this->post('/api/v1/admin/media-assets', [
            'file' => UploadedFile::fake()->image('banner.jpg', 1200, 630),
            'title' => '会员端首页横幅',
            'altText' => '瑜伽课程宣传图',
        ]);

        $upload
            ->assertCreated()
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.publishedUrl', null);

        $asset = MediaAsset::query()->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        $this->get('/api/v1/media/'.$asset->uuid)->assertNotFound();

        $published = $this->postJson("/api/v1/admin/media-assets/{$asset->id}/publish", ['version' => 1]);
        $published
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 2);
        $this->get('/api/v1/media/'.$asset->uuid)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->postJson("/api/v1/admin/media-assets/{$asset->id}/archive", ['version' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        $this->get('/api/v1/media/'.$asset->uuid)->assertNotFound();
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_media_metadata_uses_optimistic_version_and_list_is_real(): void
    {
        $asset = MediaAsset::query()->create([
            'uuid' => fake()->uuid(),
            'kind' => 'image',
            'status' => 'draft',
            'title' => '旧标题',
            'original_name' => 'one.png',
            'disk' => 'public',
            'path' => 'media/one.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 12,
            'checksum_sha256' => str_repeat('a', 64),
            'version' => 1,
        ]);

        $this->putJson("/api/v1/admin/media-assets/{$asset->id}", [
            'version' => 1,
            'title' => '新标题',
        ])->assertOk()->assertJsonPath('data.version', 2);

        $this->putJson("/api/v1/admin/media-assets/{$asset->id}", [
            'version' => 1,
            'title' => '冲突标题',
        ])->assertUnprocessable()->assertJsonValidationErrors('version');

        $this->getJson('/api/v1/admin/media-assets?query=新标题')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.title', '新标题');
    }
}
