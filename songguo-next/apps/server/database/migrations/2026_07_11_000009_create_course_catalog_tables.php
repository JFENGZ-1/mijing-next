<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('name', 80);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('catalog_status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'rooms_tenant_id_id_unique');
            $table->unique(['tenant_id', 'site_id', 'name'], 'rooms_tenant_site_name_unique');
            $table->index(['tenant_id', 'site_id', 'catalog_status'], 'rooms_site_catalog_idx');
            $table->foreign(['tenant_id', 'site_id'], 'rooms_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('course_type', 24);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->unsignedInteger('min_capacity')->nullable();
            $table->unsignedInteger('max_capacity')->nullable();
            $table->foreignId('default_room_id')->nullable();
            $table->foreignId('coach_staff_id')->nullable();
            $table->json('tags')->nullable();
            $table->string('catalog_status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'courses_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'course_type', 'catalog_status'], 'courses_site_catalog_idx');
            $table->foreign(['tenant_id', 'site_id'], 'courses_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'default_room_id'], 'courses_tenant_default_room_fk')->references(['tenant_id', 'id'])->on('rooms')->restrictOnDelete();
            $table->foreign(['tenant_id', 'coach_staff_id'], 'courses_tenant_coach_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'courses_tenant_created_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('rooms');
    }
};
