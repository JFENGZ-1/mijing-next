<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['display_name', 'mobile', 'avatar_url', 'status'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function wechatIdentities(): HasMany
    {
        return $this->hasMany(WechatIdentity::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
