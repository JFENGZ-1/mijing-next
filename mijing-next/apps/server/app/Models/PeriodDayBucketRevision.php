<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PeriodDayBucketRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'day_value_cents' => 'integer', 'event_count' => 'integer',
            'allocated_value_cents' => 'integer', 'occurred_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Period bucket revisions are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Period bucket revisions are append-only.'));
    }
}
