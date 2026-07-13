<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_link_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('member_profile_version')->index();
        });
    }

    public function down(): void
    {
        Schema::table('member_link_requests', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
