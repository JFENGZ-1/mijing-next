<?php

namespace App\Models;

use App\Enums\CardProductCourseScopeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardProductCourseScope extends Model
{
    protected $fillable = [
        'tenant_id', 'card_product_id', 'scope_kind', 'scope_key', 'display_name',
        'price_override', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scope_kind' => CardProductCourseScopeKind::class,
            'price_override' => 'decimal:2',
        ];
    }

    public function cardProduct(): BelongsTo
    {
        return $this->belongsTo(CardProduct::class);
    }
}
