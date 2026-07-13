<?php

namespace App\Models;

use App\Enums\TenantSubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'code',
        'status',
        'timezone',
        'points_enabled',
        'show_month_rank',
        'points_description_text',
        'points_policy',
        'crm_field_policy',
        'membership_agreement_html',
        'chain_brand_name',
        'chain_brand_logo_url',
        'staff_support_config',
        'subscription_plan',
        'subscription_expires_at',
        'subscription_status',
    ];

    protected function casts(): array
    {
        return [
            'points_enabled' => 'boolean',
            'show_month_rank' => 'boolean',
            'crm_field_policy' => 'array',
            'points_policy' => 'array',
            'staff_support_config' => 'array',
            'subscription_expires_at' => 'datetime',
            'subscription_status' => TenantSubscriptionStatus::class,
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
