<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCoachRule extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'staff_id', 'matrix', 'matrix_version',
    ];

    protected function casts(): array
    {
        return [
            'matrix' => 'array',
            'matrix_version' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
