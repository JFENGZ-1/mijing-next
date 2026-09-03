<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCompensationRule extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'course_id', 'session_fee_cents', 'version', 'status',
        'supersedes_id', 'created_by_staff_id', 'effective_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'session_fee_cents' => 'integer', 'version' => 'integer',
            'effective_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function roleRates(): HasMany
    {
        return $this->hasMany(CourseCompensationRoleRate::class);
    }
}
