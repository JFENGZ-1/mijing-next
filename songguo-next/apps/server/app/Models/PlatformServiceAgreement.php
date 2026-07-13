<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformServiceAgreement extends Model
{
    protected $fillable = [
        'version',
        'title',
        'html',
        'effective_at',
        'status',
        'support_contact_enabled',
        'support_contact_name',
        'support_protocol_url',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'support_contact_enabled' => 'boolean',
        ];
    }
}
