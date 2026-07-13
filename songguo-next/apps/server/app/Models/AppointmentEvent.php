<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'appointment_id', 'event_type', 'payload', 'command_key',
        'actor_staff_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }
}
