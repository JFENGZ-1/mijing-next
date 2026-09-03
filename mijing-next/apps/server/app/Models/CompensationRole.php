<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompensationRole extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'code', 'name', 'role_type', 'status', 'version',
        'created_by_staff_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'archived_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffCompensationRoleAssignment::class);
    }
}
