<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_batch_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->uuid('command_key');
            $table->string('operation', 24);
            $table->json('result');
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'command_key'], 'schedule_batch_commands_tenant_command_key_unique');
            $table->foreign(['tenant_id', 'site_id'], 'schedule_batch_commands_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'schedule_batch_commands_tenant_created_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_batch_commands');
    }
};
