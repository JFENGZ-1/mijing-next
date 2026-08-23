<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'kind',
        'status',
        'title',
        'alt_text',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'checksum_sha256',
        'metadata',
        'version',
        'published_at',
        'uploaded_by_super_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'published_at' => 'datetime',
            'version' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'uploaded_by_super_admin_id');
    }
}
