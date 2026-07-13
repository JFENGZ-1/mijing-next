<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteClosurePeriod extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'reason', 'begin_date', 'end_date',
        'status', 'created_by_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'begin_date' => 'date',
            'end_date' => 'date',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}
