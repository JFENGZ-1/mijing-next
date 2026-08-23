<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 255)->index();
            $table->string('method', 12);
            $table->string('path', 500);
            $table->string('subject', 500)->nullable();
            $table->string('request_id', 100)->nullable()->index();
            $table->unsignedSmallInteger('status_code');
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_audit_logs');
    }
};
