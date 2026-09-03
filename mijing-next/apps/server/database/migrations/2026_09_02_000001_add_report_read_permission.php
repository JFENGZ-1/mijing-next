<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_read_permission_migration_state', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id')->primary();
            $table->boolean('was_existing');
        });
        Schema::create('report_read_permission_grant_state', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['role_id', 'permission_id'], 'report_read_permission_grant_state_pk');
        });

        $permission = DB::table('permissions')->where('code', 'report.read')->first();
        $wasExisting = $permission !== null;
        if ($permission === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => '查看会员卡经营报表',
                'code' => 'report.read',
                'module' => 'reporting',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = (int) $permission->id;
        }

        DB::table('report_read_permission_migration_state')->insert([
            'permission_id' => $permissionId,
            'was_existing' => $wasExisting,
        ]);

        $ownerRoleIds = DB::table('roles')
            ->where('is_system', true)
            ->where('status', 'active')
            ->where('code', 'owner')
            ->pluck('id');
        foreach ($ownerRoleIds as $roleId) {
            $grant = ['role_id' => $roleId, 'permission_id' => $permissionId];
            if (DB::table('permission_role')->where($grant)->exists()) {
                continue;
            }
            DB::table('permission_role')->insert($grant);
            DB::table('report_read_permission_grant_state')->insert($grant);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('report_read_permission_grant_state')) {
            foreach (DB::table('report_read_permission_grant_state')->get() as $grant) {
                DB::table('permission_role')
                    ->where('role_id', $grant->role_id)
                    ->where('permission_id', $grant->permission_id)
                    ->delete();
            }
        }

        if (Schema::hasTable('report_read_permission_migration_state')) {
            $state = DB::table('report_read_permission_migration_state')->first();
            if ($state !== null
                && ! (bool) $state->was_existing
                && ! DB::table('permission_role')->where('permission_id', $state->permission_id)->exists()) {
                DB::table('permissions')->where('id', $state->permission_id)->delete();
            }
        }

        Schema::dropIfExists('report_read_permission_grant_state');
        Schema::dropIfExists('report_read_permission_migration_state');
    }
};
