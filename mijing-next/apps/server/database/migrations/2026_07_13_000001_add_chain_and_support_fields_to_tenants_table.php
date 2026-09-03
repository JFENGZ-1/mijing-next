<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('chain_brand_name', 120)->nullable()->after('membership_agreement_html');
            $table->string('chain_brand_logo_url', 512)->nullable()->after('chain_brand_name');
            $table->json('staff_support_config')->nullable()->after('chain_brand_logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['chain_brand_name', 'chain_brand_logo_url', 'staff_support_config']);
        });
    }
};
