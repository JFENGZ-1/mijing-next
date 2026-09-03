<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class PublicMediaAssetController extends Controller
{
    public function __invoke(string $uuid)
    {
        $asset = MediaAsset::query()
            ->where('uuid', $uuid)
            ->where('status', 'published')
            ->firstOrFail();

        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$asset->checksum_sha256.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
