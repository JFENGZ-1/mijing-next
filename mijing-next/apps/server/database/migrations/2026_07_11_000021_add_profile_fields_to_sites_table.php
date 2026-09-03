<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->after('address');
            $table->text('description')->nullable()->after('logo_url');
            $table->json('region')->nullable()->after('description');
            $table->json('business_hours')->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'description', 'region', 'business_hours']);
        });
    }
};
