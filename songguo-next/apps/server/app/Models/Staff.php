<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = ['tenant_id', 'account_id', 'employee_no', 'name', 'gender', 'status', 'joined_on', 'left_on', 'version'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_staff')
            ->withPivot(['tenant_id', 'is_primary', 'capabilities'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_staff')->withPivot(['tenant_id', 'site_id'])->withTimestamps();
    }

    public function hasPermission(string $permission, ?int $siteId = null): bool
    {
        if ($siteId !== null && ! $this->sites()->where('sites.tenant_id', $this->tenant_id)->whereKey($siteId)->exists()) {
            return false;
        }

        return $this->roles()
            ->where('roles.tenant_id', $this->tenant_id)
            ->where('role_staff.tenant_id', $this->tenant_id)
            ->where('roles.status', 'active')
            ->where(function ($query) use ($siteId) {
                $query->whereNull('role_staff.site_id');
                if ($siteId !== null) {
                    $query->orWhere('role_staff.site_id', $siteId);
                }
            })
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }
}
