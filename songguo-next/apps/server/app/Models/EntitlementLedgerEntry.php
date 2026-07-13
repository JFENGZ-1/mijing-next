<?php

namespace App\Models;

use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class EntitlementLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'site_id', 'member_card_id', 'member_id', 'entry_type', 'direction',
        'amount_delta', 'count_delta', 'valid_from_after', 'valid_until_after',
        'count_group_key', 'reversal_of_id', 'command_key', 'reason', 'metadata',
        'actor_account_id', 'actor_staff_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => EntitlementLedgerEntryType::class,
            'direction' => EntitlementLedgerDirection::class,
            'amount_delta' => 'decimal:2',
            'valid_from_after' => 'date',
            'valid_until_after' => 'date',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Entitlement ledger entries are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Entitlement ledger entries are append-only.'));
    }

    public function memberCard(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function actorAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }

    public function actorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }
}
