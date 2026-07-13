<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Site extends Model
{
    protected $fillable = [
        'tenant_id', 'owner_staff_id', 'name', 'code', 'status', 'phone', 'address',
        'logo_url', 'description', 'region', 'business_hours',
        'member_warm_hints', 'member_miniapp_layout', 'member_onboarding_help',
        'notification_channel_config', 'carousel_default_image_url', 'card_face_library',
        'longitude', 'latitude', 'timezone', 'version',
    ];

    protected function casts(): array
    {
        return [
            'region' => 'array',
            'business_hours' => 'array',
            'member_warm_hints' => 'array',
            'member_miniapp_layout' => 'array',
            'member_onboarding_help' => 'array',
            'notification_channel_config' => 'array',
            'card_face_library' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'site_staff')
            ->withPivot(['tenant_id', 'is_primary', 'capabilities'])
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }
}
