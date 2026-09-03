<?php

namespace App\Models;

use App\Enums\PayrollReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PayrollReportSnapshot extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'year',
        'month',
        'report_type',
        'staff_id',
        'payload',
        'recompute_job_id',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => PayrollReportType::class,
            'payload' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('PAYROLL_REPORT_SNAPSHOT_IMMUTABLE'));
        static::updating(fn () => throw new RuntimeException('PAYROLL_REPORT_SNAPSHOT_IMMUTABLE'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function recomputeJob(): BelongsTo
    {
        return $this->belongsTo(PayrollRecomputeJob::class);
    }
}
