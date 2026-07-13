<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPointBalance extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['tenant_id', 'member_id', 'balance'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
