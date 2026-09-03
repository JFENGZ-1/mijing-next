<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'staff_tenant_id_id_unique');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'roles_tenant_id_id_unique');
        });
        Schema::table('site_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('site_id');
        });
        Schema::table('role_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('role_id');
        });

        DB::statement('UPDATE site_staff ss JOIN staff s ON s.id = ss.staff_id SET ss.tenant_id = s.tenant_id');
        DB::statement('UPDATE role_staff rs JOIN staff s ON s.id = rs.staff_id SET rs.tenant_id = s.tenant_id');
        DB::statement('ALTER TABLE site_staff MODIFY tenant_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE role_staff MODIFY tenant_id BIGINT UNSIGNED NOT NULL');

        Schema::table('site_staff', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'staff_id'], 'site_staff_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'site_id'], 'site_staff_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->cascadeOnDelete();
        });
        Schema::table('role_staff', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'staff_id'], 'role_staff_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'role_id'], 'role_staff_tenant_role_fk')->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'site_id'], 'role_staff_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->cascadeOnDelete();
        });
        Schema::table('members', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'registration_site_id'], 'members_tenant_registration_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'home_site_id'], 'members_tenant_home_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign('members_tenant_registration_site_fk');
            $table->dropForeign('members_tenant_home_site_fk');
        });
        Schema::table('role_staff', function (Blueprint $table) {
            $table->dropForeign('role_staff_tenant_staff_fk');
            $table->dropForeign('role_staff_tenant_role_fk');
            $table->dropForeign('role_staff_tenant_site_fk');
            $table->dropColumn('tenant_id');
        });
        Schema::table('site_staff', function (Blueprint $table) {
            $table->dropForeign('site_staff_tenant_staff_fk');
            $table->dropForeign('site_staff_tenant_site_fk');
            $table->dropColumn('tenant_id');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_tenant_id_id_unique');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique('staff_tenant_id_id_unique');
        });
    }
};
