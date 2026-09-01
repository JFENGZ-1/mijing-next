<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardProductCourseRule extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'card_product_id', 'course_id', 'deduction_type',
        'amount_cents', 'count_units', 'version', 'status', 'supersedes_id',
        'created_by_staff_id', 'effective_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer', 'count_units' => 'integer', 'version' => 'integer',
            'effective_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function cardProduct(): BelongsTo
    {
        return $this->belongsTo(CardProduct::class);
    }
}
