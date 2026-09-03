<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_recompute_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('scope', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('command_key', 120);
            $table->foreignId('requested_by_staff_id')->constrained('staff')->restrictOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'site_id', 'command_key'], 'payroll_recompute_jobs_command_unique');
            $table->index(['tenant_id', 'site_id', 'created_at']);
            $table->index(['tenant_id', 'site_id', 'status']);
            $table->foreign(['tenant_id', 'requested_by_staff_id'], 'payroll_recompute_jobs_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });

        Schema::create('payroll_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('report_type', 32);
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->json('payload');
            $table->foreignId('recompute_job_id')->nullable()->constrained('payroll_recompute_jobs')->nullOnDelete();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'site_id', 'year', 'month', 'report_type', 'staff_id', 'computed_at'], 'payroll_report_snapshots_lookup_idx');
            $table->foreign(['tenant_id', 'staff_id'], 'payroll_report_snapshots_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_report_snapshots');
        Schema::dropIfExists('payroll_recompute_jobs');
    }
};
