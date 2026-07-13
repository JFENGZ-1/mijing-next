<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'actor_account_id', 'actor_staff_id', 'action',
        'subject_type', 'subject_id', 'request_id', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
