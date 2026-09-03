<?php

namespace App\Models;

use App\Enums\CourseCatalogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'name', 'capacity', 'catalog_status',
        'sort_order', 'version', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'catalog_status' => CourseCatalogStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'default_room_id');
    }
}
