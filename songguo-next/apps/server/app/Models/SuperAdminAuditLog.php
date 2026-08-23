<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'super_admin_id',
        'action',
        'method',
        'path',
        'subject',
        'request_id',
        'status_code',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status_code' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
