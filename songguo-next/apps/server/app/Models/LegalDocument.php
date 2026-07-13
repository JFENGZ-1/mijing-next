<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = [
        'tenant_id', 'scope_key', 'type', 'version', 'title', 'content',
        'content_hash', 'status', 'is_required', 'published_at',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'published_at' => 'datetime'];
    }
}
