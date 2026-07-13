<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->string('status', 24)->default('pending');
            $table->string('file_path', 500)->nullable();
            $table->foreignId('requested_by_staff_id')->constrained('staff')->restrictOnDelete();
            $table->json('filters')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'site_id', 'created_at']);
            $table->index(['tenant_id', 'site_id', 'status']);
            $table->foreign(['tenant_id', 'requested_by_staff_id'], 'export_jobs_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
