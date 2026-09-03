<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCardShareAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allocation_bps' => 'integer', 'version' => 'integer',
            'effective_from' => 'date', 'effective_until' => 'date', 'archived_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompensationRole::class, 'compensation_role_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
