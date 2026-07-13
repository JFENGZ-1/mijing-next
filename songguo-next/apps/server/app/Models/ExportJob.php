<?php

namespace App\Models;

use App\Enums\ExportJobStatus;
use App\Enums\ExportJobType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ExportJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'type',
        'status',
        'file_path',
        'requested_by_staff_id',
        'filters',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExportJobType::class,
            'status' => ExportJobStatus::class,
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ExportJob $job) {
            if ($job->status === ExportJobStatus::Completed) {
                throw new RuntimeException('EXPORT_JOB_IMMUTABLE');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }
}
