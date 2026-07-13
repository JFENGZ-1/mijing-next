<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPolicy extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'version',
        'policy',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'policy' => 'array',
            'rules' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
