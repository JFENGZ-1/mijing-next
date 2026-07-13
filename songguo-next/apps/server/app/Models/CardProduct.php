<?php

namespace App\Models;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardProduct extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'card_type', 'name', 'description', 'price', 'face_value',
        'initial_count', 'validity_days', 'validity_mode', 'activation_mode', 'scope_config',
        'booking_rules', 'sale_status', 'catalog_status', 'sort_order', 'version',
        'created_by_staff_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'card_type' => CardType::class,
            'sale_status' => CardProductSaleStatus::class,
            'catalog_status' => CardProductCatalogStatus::class,
            'price' => 'decimal:2',
            'face_value' => 'decimal:2',
            'scope_config' => 'array',
            'booking_rules' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function courseScopes(): HasMany
    {
        return $this->hasMany(CardProductCourseScope::class);
    }

    public function memberCards(): HasMany
    {
        return $this->hasMany(MemberCard::class);
    }
}
