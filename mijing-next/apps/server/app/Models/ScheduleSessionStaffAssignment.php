<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSessionStaffAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'schedule_session_id', 'staff_id',
        'compensation_role_id', 'is_primary', 'allocation_bps', 'assignment_version',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean', 'allocation_bps' => 'integer',
            'assignment_version' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompensationRole::class, 'compensation_role_id');
    }
}
