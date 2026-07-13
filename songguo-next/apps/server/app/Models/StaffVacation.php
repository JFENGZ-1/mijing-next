<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffVacation extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'staff_id', 'begin_at', 'end_at',
        'group_booking_policy', 'private_booking_policy', 'status',
        'remark', 'created_by_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'begin_at' => 'datetime',
            'end_at' => 'datetime',
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}
