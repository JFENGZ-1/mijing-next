<?php

namespace App\Models;

use App\Enums\LedgerReconciliationJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerReconciliationJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'status',
        'from_date',
        'to_date',
        'dry_run',
        'command_key',
        'requested_by_staff_id',
        'result',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LedgerReconciliationJobStatus::class,
            'from_date' => 'date',
            'to_date' => 'date',
            'dry_run' => 'boolean',
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }
}
