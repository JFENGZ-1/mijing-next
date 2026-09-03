<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPrivateProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'coach_staff_id', 'tag_text', 'experience', 'specialty',
        'booking_windows', 'subject_mode', 'uniform_duration_minutes', 'uniform_course_id', 'version',
    ];

    protected function casts(): array
    {
        return [
            'booking_windows' => 'array',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    public function uniformCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'uniform_course_id');
    }
}
