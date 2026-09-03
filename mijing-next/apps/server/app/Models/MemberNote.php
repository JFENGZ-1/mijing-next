<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberNote extends Model
{
    protected $fillable = ['tenant_id', 'member_id', 'site_id', 'author_staff_id', 'correction_of_id', 'body', 'request_id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_staff_id');
    }
}
