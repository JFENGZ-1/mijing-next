<?php

namespace App\Models;

use App\Enums\OrderAmountCorrectionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class OrderAmountCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'order_id', 'entry_type', 'corrected_amount', 'reversal_of_id',
        'command_key', 'reason', 'actor_staff_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => OrderAmountCorrectionType::class,
            'corrected_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Order amount corrections are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Order amount corrections are append-only.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MemberCardOrder::class, 'order_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
