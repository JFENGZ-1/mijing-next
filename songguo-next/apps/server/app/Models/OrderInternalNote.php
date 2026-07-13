<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInternalNote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'body',
        'command_key',
        'author_staff_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MemberCardOrder::class, 'order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_staff_id');
    }
}
