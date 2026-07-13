<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSubscriptionPlan extends Model
{
    protected $fillable = [
        'code',
        'label',
        'duration_days',
        'price_cents',
        'original_price_cents',
        'currency',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_cents' => 'integer',
            'original_price_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
