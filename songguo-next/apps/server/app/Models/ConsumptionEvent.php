<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumptionEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date', 'deducted_amount_cents' => 'integer',
            'deducted_count' => 'integer', 'consumed_value_cents' => 'integer',
            'metadata' => 'array', 'occurred_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function memberCard(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class);
    }

    public function valueLot(): BelongsTo
    {
        return $this->belongsTo(MemberCardValueLot::class, 'value_lot_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionSettlementLine::class);
    }
}
