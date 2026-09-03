<?php

namespace App\Models;

use App\Enums\PayrollCoachMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCoachConfig extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'enabled', 'mode',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mode' => PayrollCoachMode::class,
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
