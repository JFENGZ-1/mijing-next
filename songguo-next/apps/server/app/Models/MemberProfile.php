<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = [
        'account_id', 'display_name', 'avatar_object_key', 'gender', 'birth_date',
        'height_cm', 'weight_kg', 'mobile_ciphertext', 'mobile_hash', 'mobile_last4',
        'mobile_verified_at', 'mobile_verification_method', 'version',
    ];

    protected $hidden = ['mobile_ciphertext', 'mobile_hash'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'mobile_verified_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
