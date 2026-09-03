<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'version' => 'integer', 'closed_at' => 'datetime',
            'metrics_snapshot' => 'array', 'metrics_snapshotted_at' => 'datetime',
        ];
    }
}
