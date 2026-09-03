<?php

namespace App\Models;

use App\Enums\PayrollSalesMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSalesConfig extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'enabled', 'mode', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mode' => PayrollSalesMode::class,
            'settings' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
