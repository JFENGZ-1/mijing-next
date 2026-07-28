<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 私教档案（对标原版 drainer：私教=教练维度的预约时间制，不走排课）
        Schema::create('coach_private_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('coach_staff_id');
            $table->string('tag_text', 40)->nullable();
            $table->text('experience')->nullable();
            $table->text('specialty')->nullable();
            // [{days:[1..7], start:"08:00", end:"21:00"}, ...]
            $table->json('booking_windows')->nullable();
            $table->string('subject_mode', 20)->default('uniform'); // uniform | per_course
            $table->unsignedSmallInteger('uniform_duration_minutes')->default(60);
            $table->unsignedBigInteger('uniform_course_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'site_id', 'coach_staff_id'], 'coach_private_profiles_site_coach_unique');
            $table->index(['tenant_id', 'site_id']);
        });

        // 统一模式的隐藏课目（仅用于挂卡扣费），不出现在课程库列表
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('hidden_in_catalog')->default(false)->after('display_color');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_private_profiles');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('hidden_in_catalog');
        });
    }
};
