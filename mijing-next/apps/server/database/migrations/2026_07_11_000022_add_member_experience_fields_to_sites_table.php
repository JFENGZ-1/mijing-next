<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('member_warm_hints')->nullable()->after('business_hours');
            $table->json('member_miniapp_layout')->nullable()->after('member_warm_hints');
            $table->json('member_onboarding_help')->nullable()->after('member_miniapp_layout');
            $table->string('carousel_default_image_url', 512)->nullable()->after('member_onboarding_help');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'member_warm_hints',
                'member_miniapp_layout',
                'member_onboarding_help',
                'carousel_default_image_url',
            ]);
        });
    }
};
