<?php

namespace App\Models;

use App\Enums\MemberCardOrderStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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

    /**
     * Reporting attribution uses the payment fact timestamp. Orders created before
     * paid_at was introduced retain their original created_at as a compatibility
     * fallback.
     */
    public function scopeWherePaidAtBetween(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->whereRaw(
            'COALESCE(member_card_orders.paid_at, member_card_orders.created_at) BETWEEN ? AND ?',
            [$start, $end],
        );
    }

    public function scopeOrderByPaidAt(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(
            "COALESCE(member_card_orders.paid_at, member_card_orders.created_at) {$direction}",
        );
    }

    public function reportingPaidAt(): ?Carbon
    {
        return $this->paid_at ?? $this->created_at;
    }
}
