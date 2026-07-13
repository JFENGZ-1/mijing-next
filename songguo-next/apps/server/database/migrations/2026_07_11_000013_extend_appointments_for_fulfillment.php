<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('staff_notes')->nullable()->after('cancelled_at');
            $table->timestamp('absent_marked_at')->nullable()->after('staff_notes');
            $table->foreignId('rescheduled_from_session_id')->nullable()->after('absent_marked_at');
            $table->foreignId('penalty_ledger_entry_id')->nullable()->after('rescheduled_from_session_id');

            $table->foreign(['tenant_id', 'rescheduled_from_session_id'], 'appointments_tenant_rescheduled_session_fk')
                ->references(['tenant_id', 'id'])
                ->on('schedule_sessions')
                ->restrictOnDelete();
            $table->foreign('penalty_ledger_entry_id', 'appointments_penalty_ledger_fk')
                ->references('id')
                ->on('entitlement_ledger_entries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('appointments_tenant_rescheduled_session_fk');
            $table->dropForeign('appointments_penalty_ledger_fk');
            $table->dropColumn([
                'staff_notes',
                'absent_marked_at',
                'rescheduled_from_session_id',
                'penalty_ledger_entry_id',
            ]);
        });
    }
};
