<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberWallet extends Model
{
    protected $fillable = ['tenant_id', 'member_id', 'balance_cents', 'version'];

    protected function casts(): array
    {
        return ['balance_cents' => 'integer', 'version' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MemberWalletLedgerEntry::class);
    }
}
