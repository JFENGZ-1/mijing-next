<?php

namespace App\Models;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'course_type', 'name', 'description', 'duration_minutes',
        'difficulty', 'min_capacity', 'max_capacity', 'default_room_id', 'coach_staff_id',
        'tags', 'face_style', 'display_color', 'hidden_in_catalog', 'catalog_status', 'sort_order', 'version', 'created_by_staff_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'course_type' => CourseType::class,
            'catalog_status' => CourseCatalogStatus::class,
            'tags' => 'array',
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

    public function defaultRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'default_room_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function compensationRules(): HasMany
    {
        return $this->hasMany(CourseCompensationRule::class);
    }
}
