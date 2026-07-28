<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'tenant_id', 'site_id', 'session_id', 'member_id', 'status', 'command_key',
        'member_card_id', 'ledger_entry_id', 'booked_by_account_id', 'created_by_staff_id',
        'booked_at', 'cancelled_at', 'staff_notes', 'member_remark', 'absent_marked_at',
        'rescheduled_from_session_id', 'penalty_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'absent_marked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class, 'session_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function memberCard(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(EntitlementLedgerEntry::class, 'ledger_entry_id');
    }

    public function bookedByAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'booked_by_account_id');
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}
