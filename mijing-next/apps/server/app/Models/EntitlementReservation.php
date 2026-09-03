<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntitlementReservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reserved_amount_cents' => 'integer', 'reserved_count' => 'integer',
            'reserved_at' => 'datetime', 'released_at' => 'datetime', 'consumed_at' => 'datetime',
            'reversed_at' => 'datetime', 'metadata' => 'array',
        ];
    }
}
