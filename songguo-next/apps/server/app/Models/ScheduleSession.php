<?php

namespace App\Models;

use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSession extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'course_id', 'room_id', 'coach_staff_id', 'delivery_role_id',
        'starts_at', 'ends_at', 'capacity', 'booked_count', 'status',
        'session_kind', 'display_color', 'version', 'created_by_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ScheduleSessionStatus::class,
            'session_kind' => ScheduleSessionKind::class,
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'session_id');
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(ScheduleSessionStaffAssignment::class);
    }
}
