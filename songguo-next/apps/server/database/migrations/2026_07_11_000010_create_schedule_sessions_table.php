<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->foreignId('course_id');
            $table->foreignId('room_id')->nullable();
            $table->foreignId('coach_staff_id');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('booked_count')->default(0);
            $table->string('status', 24)->default('scheduled');
            $table->string('session_kind', 24);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'schedule_sessions_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'starts_at'], 'schedule_sessions_site_day_idx');
            $table->index(['tenant_id', 'site_id', 'starts_at', 'ends_at'], 'schedule_sessions_site_range_idx');
            $table->index(['tenant_id', 'site_id', 'status', 'starts_at'], 'schedule_sessions_site_status_day_idx');
            $table->index(['tenant_id', 'room_id', 'starts_at', 'ends_at'], 'schedule_sessions_room_overlap_idx');
            $table->foreign(['tenant_id', 'site_id'], 'schedule_sessions_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'course_id'], 'schedule_sessions_tenant_course_fk')->references(['tenant_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'room_id'], 'schedule_sessions_tenant_room_fk')->references(['tenant_id', 'id'])->on('rooms')->restrictOnDelete();
            $table->foreign(['tenant_id', 'coach_staff_id'], 'schedule_sessions_tenant_coach_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'schedule_sessions_tenant_created_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sessions');
    }
};
