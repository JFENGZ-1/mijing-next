<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodDayBucket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date', 'day_value_cents' => 'integer',
            'event_count' => 'integer', 'latest_revision' => 'integer', 'closed_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PeriodDayBucketRevision::class);
    }
}
