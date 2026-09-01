<?php

namespace App\Models;

use App\Enums\MemberCardOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class MemberCardOrder extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'member_id', 'member_card_id', 'order_no', 'amount',
        'status', 'command_key', 'metadata', 'created_by_staff_id', 'voided_at',
        'payment_method', 'paid_amount_cents', 'paid_at',
        'payment_expires_at', 'closed_at', 'close_reason', 'payment_transaction_id',
        'payment_state_version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => MemberCardOrderStatus::class,
            'metadata' => 'array',
            'voided_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'closed_at' => 'datetime',
            'payment_state_version' => 'integer',
            'paid_amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('Orders cannot be physically deleted.'));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function memberCard(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class);
    }

    public function amountCorrections(): HasMany
    {
        return $this->hasMany(OrderAmountCorrection::class, 'order_id');
    }
}
