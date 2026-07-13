<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteNotice extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'title', 'body', 'display_days', 'cover_image_url',
        'published_at', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
