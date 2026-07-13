<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLinkRequest extends Model
{
    protected $fillable = [
        'public_id', 'tenant_id', 'site_id', 'lead_member_id', 'account_id',
        'resolved_member_id', 'status', 'member_decision', 'evidence_type',
        'evidence_hash', 'member_profile_version', 'active_key', 'member_decided_at',
        'expires_at', 'reviewed_by_staff_id', 'reviewed_at', 'review_reason', 'request_id', 'version',
    ];

    protected $hidden = ['evidence_hash', 'active_key'];

    protected function casts(): array
    {
        return [
            'member_decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'lead_member_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function resolvedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'resolved_member_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reviewed_by_staff_id');
    }
}
