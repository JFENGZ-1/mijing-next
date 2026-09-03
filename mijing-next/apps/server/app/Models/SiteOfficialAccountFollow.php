<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteOfficialAccountFollow extends Model
{
    protected $table = 'site_official_account_follow';

    protected $fillable = [
        'tenant_id', 'site_id', 'image_url', 'instructions_text', 'status',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
