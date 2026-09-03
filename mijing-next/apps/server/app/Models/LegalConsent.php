<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalConsent extends Model
{
    protected $fillable = [
        'account_id', 'member_id', 'legal_document_id', 'action', 'source',
        'request_id', 'ip_hash', 'user_agent_hash', 'occurred_at',
    ];

    protected $hidden = ['ip_hash', 'user_agent_hash'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
