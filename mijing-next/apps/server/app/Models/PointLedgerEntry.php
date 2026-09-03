<?php

namespace App\Models;

use App\Enums\PointLedgerDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PointLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'member_id', 'amount_delta', 'direction', 'reason',
        'command_key', 'actor_staff_id', 'actor_account_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PointLedgerDirection::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Point ledger entries are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Point ledger entries are append-only.'));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function actorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }

    public function actorAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }
}
