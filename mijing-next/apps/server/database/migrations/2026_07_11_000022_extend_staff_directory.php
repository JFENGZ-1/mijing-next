<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('name');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('owner_staff_id')->nullable()->after('tenant_id');
            $table->foreign(['tenant_id', 'owner_staff_id'], 'sites_tenant_owner_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });

        Schema::table('site_staff', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('site_staff', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign('sites_tenant_owner_staff_fk');
            $table->dropColumn('owner_staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
