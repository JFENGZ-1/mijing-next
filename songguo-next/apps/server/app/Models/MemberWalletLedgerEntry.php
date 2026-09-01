<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MemberWalletLedgerEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer', 'balance_after_cents' => 'integer',
            'metadata' => 'array', 'occurred_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Wallet ledger entries are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Wallet ledger entries are append-only.'));
    }
}
