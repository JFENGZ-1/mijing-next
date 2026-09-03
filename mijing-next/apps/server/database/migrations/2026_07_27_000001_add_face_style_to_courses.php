<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // 课程卡背景图案（平台图案库 faceStyle 索引，对标原版 courseBacklog）
            $table->unsignedTinyInteger('face_style')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('face_style');
        });
    }
};
