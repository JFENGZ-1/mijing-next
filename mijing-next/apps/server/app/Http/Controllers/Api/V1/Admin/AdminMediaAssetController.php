<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaAssetRequest;
use App\Http\Requests\Admin\UpdateMediaAssetRequest;
use App\Models\MediaAsset;
use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminMediaAssetController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'kind' => ['sometimes', 'nullable', 'in:image,video'],
            'status' => ['sometimes', 'nullable', 'in:draft,published,archived'],
            'tenantId' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = MediaAsset::query()
            ->with(['tenant:id,name,code', 'uploadedBy:id,username,name'])
            ->when($validated['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(array_key_exists('tenantId', $validated), fn ($query) => $query->where('tenant_id', $validated['tenantId']))
            ->when($validated['query'] ?? null, fn ($query, $keyword) => $query->where(
                fn ($nested) => $nested->where('title', 'like', "%{$keyword}%")
                    ->orWhere('original_name', 'like', "%{$keyword}%")
                    ->orWhere('uuid', 'like', "%{$keyword}%")
            ))
            ->latest('id')
            ->paginate($validated['perPage'] ?? 24);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (MediaAsset $asset) => $this->assetData($asset))->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreMediaAssetRequest $request)
    {
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'video';
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = now()->format('Y/m')."/{$uuid}".($extension !== '' ? ".{$extension}" : '');
        $disk = (string) config('admin.media_disk', 'public');
        $dimensions = $kind === 'image' ? @getimagesize($file->getRealPath()) : false;

        $stored = Storage::disk($disk)->putFileAs('media', $file, $path);
        if (! $stored) {
            return ApiResponse::error('MEDIA_STORAGE_FAILED', '资源文件保存失败', 500);
        }

        try {
            /** @var SuperAdmin $admin */
            $admin = $request->user();
            $asset = MediaAsset::query()->create([
                'uuid' => $uuid,
                'tenant_id' => $request->validated('tenantId'),
                'kind' => $kind,
                'status' => 'draft',
                'title' => $request->validated('title'),
                'alt_text' => $request->validated('altText'),
                'original_name' => $file->getClientOriginalName(),
                'disk' => $disk,
                'path' => $stored,
                'mime_type' => $mime,
                'extension' => $extension ?: null,
                'size_bytes' => $file->getSize(),
                'width' => $dimensions ? $dimensions[0] : null,
                'height' => $dimensions ? $dimensions[1] : null,
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'version' => 1,
                'uploaded_by_super_admin_id' => $admin->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($stored);
            throw $exception;
        }

        return ApiResponse::success($this->assetData($asset->load(['tenant', 'uploadedBy'])), 201);
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $mediaAsset)
    {
        $asset = $this->mutateWithVersion($mediaAsset, (int) $request->validated('version'), function (MediaAsset $asset) use ($request) {
            foreach (['title' => 'title', 'altText' => 'alt_text', 'tenantId' => 'tenant_id'] as $input => $column) {
                if ($request->exists($input)) {
                    $asset->{$column} = $request->validated($input);
                }
            }
        });

        return ApiResponse::success($this->assetData($asset->load(['tenant', 'uploadedBy'])));
    }

    public function publish(Request $request, MediaAsset $mediaAsset)
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $asset = $this->mutateWithVersion($mediaAsset, (int) $validated['version'], function (MediaAsset $asset) {
            $asset->status = 'published';
            $asset->published_at = now();
        });

        return ApiResponse::success($this->assetData($asset->load(['tenant', 'uploadedBy'])));
    }

    public function archive(Request $request, MediaAsset $mediaAsset)
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $asset = $this->mutateWithVersion($mediaAsset, (int) $validated['version'], function (MediaAsset $asset) {
            $asset->status = 'archived';
        });

        return ApiResponse::success($this->assetData($asset->load(['tenant', 'uploadedBy'])));
    }

    public function content(MediaAsset $mediaAsset)
    {
        abort_unless(Storage::disk($mediaAsset->disk)->exists($mediaAsset->path), 404);

        return Storage::disk($mediaAsset->disk)->response($mediaAsset->path, null, [
            'Content-Type' => $mediaAsset->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function mutateWithVersion(MediaAsset $mediaAsset, int $version, callable $mutation): MediaAsset
    {
        return DB::transaction(function () use ($mediaAsset, $version, $mutation) {
            $asset = MediaAsset::query()->lockForUpdate()->findOrFail($mediaAsset->id);
            if ($asset->version !== $version) {
                throw ValidationException::withMessages(['version' => ['资源已被其他管理员修改，请刷新后重试。']]);
            }

            $mutation($asset);
            $asset->version++;
            $asset->save();

            return $asset;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function assetData(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'kind' => $asset->kind,
            'status' => $asset->status,
            'title' => $asset->title,
            'altText' => $asset->alt_text,
            'originalName' => $asset->original_name,
            'mimeType' => $asset->mime_type,
            'extension' => $asset->extension,
            'sizeBytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'durationSeconds' => $asset->duration_seconds,
            'checksumSha256' => $asset->checksum_sha256,
            'tenant' => $asset->tenant ? [
                'id' => $asset->tenant->id,
                'name' => $asset->tenant->name,
                'code' => $asset->tenant->code,
            ] : null,
            'uploadedBy' => $asset->uploadedBy ? [
                'id' => $asset->uploadedBy->id,
                'username' => $asset->uploadedBy->username,
                'name' => $asset->uploadedBy->name,
            ] : null,
            'previewUrl' => route('admin.media-assets.content', $asset),
            'previewPath' => "/admin/media-assets/{$asset->id}/content",
            'publishedUrl' => $asset->status === 'published' ? route('platform-media.show', $asset->uuid) : null,
            'version' => $asset->version,
            'publishedAt' => $asset->published_at?->toISOString(),
            'createdAt' => $asset->created_at?->toISOString(),
            'updatedAt' => $asset->updated_at?->toISOString(),
        ];
    }
}
