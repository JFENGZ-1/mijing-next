<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id');
            $table->string('event_type', 48);
            $table->json('payload')->nullable();
            $table->uuid('command_key')->nullable();
            $table->foreignId('actor_staff_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'command_key'], 'appointment_events_tenant_command_key_unique');
            $table->index(['tenant_id', 'appointment_id', 'occurred_at'], 'appointment_events_appointment_idx');
            $table->foreign(['tenant_id', 'appointment_id'], 'appointment_events_tenant_appointment_fk')
                ->references(['tenant_id', 'id'])
                ->on('appointments')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_staff_id'], 'appointment_events_tenant_actor_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
    }
};
