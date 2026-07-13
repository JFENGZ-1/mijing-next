<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_crm_profiles', function (Blueprint $table) {
            $table->string('sticky_remark', 500)->nullable()->after('mobile_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('member_crm_profiles', function (Blueprint $table) {
            $table->dropColumn('sticky_remark');
        });
    }
};
