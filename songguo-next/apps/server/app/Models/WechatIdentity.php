<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WechatIdentity extends Model
{
    protected $fillable = ['account_id', 'appid', 'openid', 'unionid', 'session_key_ciphertext', 'last_authenticated_at'];

    protected $hidden = ['session_key_ciphertext'];

    protected function casts(): array
    {
        return ['last_authenticated_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
