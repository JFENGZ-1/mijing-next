<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_crm_profiles', function (Blueprint $table) {
            $table->text('national_id_ciphertext')->nullable()->after('birth_date');
            $table->char('national_id_hash', 64)->nullable()->after('national_id_ciphertext');
            $table->char('national_id_last4', 4)->nullable()->after('national_id_hash');
            $table->decimal('height_cm', 5, 2)->nullable()->after('national_id_last4');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('height_cm');
            $table->index(['tenant_id', 'national_id_hash'], 'member_crm_profiles_tenant_national_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('member_crm_profiles', function (Blueprint $table) {
            $table->dropIndex('member_crm_profiles_tenant_national_id_idx');
            $table->dropColumn([
                'national_id_ciphertext',
                'national_id_hash',
                'national_id_last4',
                'height_cm',
                'weight_kg',
            ]);
        });
    }
};
