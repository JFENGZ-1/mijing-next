<?php

namespace App\Models;

use App\Enums\PayrollRecomputeJobStatus;
use App\Enums\PayrollRecomputeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PayrollRecomputeJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'status',
        'scope',
        'year',
        'month',
        'staff_id',
        'command_key',
        'requested_by_staff_id',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollRecomputeJobStatus::class,
            'scope' => PayrollRecomputeScope::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PayrollRecomputeJob $job) {
            if ($job->status === PayrollRecomputeJobStatus::Completed) {
                throw new RuntimeException('PAYROLL_RECOMPUTE_JOB_IMMUTABLE');
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }
}
