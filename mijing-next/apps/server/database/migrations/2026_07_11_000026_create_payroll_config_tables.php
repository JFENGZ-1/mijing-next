<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_coach_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->boolean('enabled')->default(false);
            $table->string('mode', 32)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id'], 'payroll_coach_configs_site_unique');
            $table->foreign(['tenant_id', 'site_id'], 'payroll_coach_configs_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });

        Schema::create('payroll_coach_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('staff_id')->nullable();
            $table->json('matrix');
            $table->unsignedInteger('matrix_version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id', 'staff_id'], 'payroll_coach_rules_staff_unique');
            $table->foreign(['tenant_id', 'site_id'], 'payroll_coach_rules_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'payroll_coach_rules_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('payroll_sales_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->boolean('enabled')->default(false);
            $table->string('mode', 32)->nullable();
            $table->json('settings');
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id'], 'payroll_sales_configs_site_unique');
            $table->foreign(['tenant_id', 'site_id'], 'payroll_sales_configs_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_sales_configs');
        Schema::dropIfExists('payroll_coach_rules');
        Schema::dropIfExists('payroll_coach_configs');
    }
};
