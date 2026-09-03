<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPaymentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'enabled',
        'merchant_id',
        'merchant_serial_no',
        'private_key',
        'api_v3_key',
        'platform_public_key',
        'platform_public_key_id',
        'notify_url',
        'webhook_secret',
        'version',
        'updated_by_super_admin_id',
    ];

    protected $hidden = [
        'private_key',
        'api_v3_key',
        'platform_public_key',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'private_key' => 'encrypted',
            'api_v3_key' => 'encrypted',
            'platform_public_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'version' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'updated_by_super_admin_id');
    }
}
