<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCrmProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'member_id', 'name', 'gender', 'birth_date', 'national_id_ciphertext',
        'national_id_hash', 'national_id_last4', 'height_cm', 'weight_kg', 'mobile_ciphertext',
        'mobile_hash', 'mobile_last4', 'mobile_source', 'mobile_verified_at', 'sticky_remark', 'version',
    ];

    protected $hidden = ['mobile_ciphertext', 'mobile_hash', 'national_id_ciphertext', 'national_id_hash'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'mobile_verified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
