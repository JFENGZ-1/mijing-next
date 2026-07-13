<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'tenant_id', 'account_id', 'member_no', 'status', 'source',
        'registration_site_id', 'home_site_id', 'owner_staff_id', 'app_access_status', 'ranking_opt_in',
        'status_changed_at', 'status_changed_by_staff_id', 'archived_at', 'joined_at', 'version',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'archived_at' => 'datetime',
            'ranking_opt_in' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'member_sites')
            ->withPivot(['tenant_id', 'relationship_type', 'status', 'first_seen_at', 'last_seen_at'])
            ->withTimestamps();
    }

    public function crmProfile(): HasOne
    {
        return $this->hasOne(MemberCrmProfile::class);
    }

    public function homeSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'home_site_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MemberNote::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MemberTag::class, 'member_tag_assignments')
            ->withPivot(['tenant_id', 'assigned_by_staff_id', 'assigned_at']);
    }

    public function memberCards(): HasMany
    {
        return $this->hasMany(MemberCard::class);
    }
}
