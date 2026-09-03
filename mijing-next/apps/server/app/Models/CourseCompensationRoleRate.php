<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCompensationRoleRate extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'course_compensation_rule_id', 'compensation_role_id', 'rate_bps',
    ];

    protected function casts(): array
    {
        return ['rate_bps' => 'integer'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompensationRole::class, 'compensation_role_id');
    }
}
