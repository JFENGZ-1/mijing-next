<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCompensationRoleAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'staff_id', 'compensation_role_id', 'active_from',
        'active_until', 'status', 'version', 'assigned_by_staff_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'date', 'active_until' => 'date', 'version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompensationRole::class, 'compensation_role_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
