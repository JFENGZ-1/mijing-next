<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberCardValueLot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_amount_cents' => 'integer', 'entitlement_amount_cents' => 'integer',
            'entitlement_count' => 'integer', 'remaining_count' => 'integer',
            'entitlement_days' => 'integer', 'product_version' => 'integer',
            'valid_from' => 'date', 'valid_until' => 'date', 'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
