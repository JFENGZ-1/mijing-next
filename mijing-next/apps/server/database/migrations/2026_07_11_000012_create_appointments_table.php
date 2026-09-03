<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->foreignId('session_id');
            $table->foreignId('member_id');
            $table->string('status', 24);
            $table->uuid('command_key');
            $table->foreignId('member_card_id')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('entitlement_ledger_entries')->restrictOnDelete();
            $table->foreignId('booked_by_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'appointments_tenant_id_id_unique');
            $table->unique(['tenant_id', 'command_key'], 'appointments_tenant_command_key_unique');
            $table->index(['tenant_id', 'session_id', 'status'], 'appointments_session_status_idx');
            $table->index(['tenant_id', 'member_id', 'status'], 'appointments_member_status_idx');
            $table->foreign(['tenant_id', 'site_id'], 'appointments_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'], 'appointments_tenant_session_fk')->references(['tenant_id', 'id'])->on('schedule_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'appointments_tenant_member_fk')->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'appointments_tenant_member_card_fk')->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'appointments_tenant_created_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
