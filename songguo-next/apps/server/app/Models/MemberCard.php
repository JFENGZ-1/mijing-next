<?php

namespace App\Models;

use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Enums\MemberCardVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberCard extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'member_id', 'card_product_id', 'card_type', 'card_no',
        'status', 'member_visibility', 'product_snapshot', 'valid_from', 'valid_until',
        'cached_balance', 'cached_remaining_count', 'freeze_state', 'version',
        'issued_at', 'issued_by_staff_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'card_type' => CardType::class,
            'status' => MemberCardStatus::class,
            'member_visibility' => MemberCardVisibility::class,
            'product_snapshot' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'cached_balance' => 'decimal:2',
            'freeze_state' => 'array',
            'issued_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function cardProduct(): BelongsTo
    {
        return $this->belongsTo(CardProduct::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'issued_by_staff_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EntitlementLedgerEntry::class);
    }
}
