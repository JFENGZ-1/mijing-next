<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberTag extends Model
{
    protected $fillable = ['tenant_id', 'name', 'normalized_name', 'color', 'status', 'version'];
}
