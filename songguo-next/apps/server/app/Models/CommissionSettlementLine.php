<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class CommissionSettlementLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_value_cents' => 'integer', 'rate_bps' => 'integer',
            'allocation_bps' => 'integer', 'amount_cents' => 'integer',
            'metadata' => 'array', 'occurred_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Commission settlement lines are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Commission settlement lines are append-only.'));
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompensationRole::class, 'compensation_role_id');
    }
}
