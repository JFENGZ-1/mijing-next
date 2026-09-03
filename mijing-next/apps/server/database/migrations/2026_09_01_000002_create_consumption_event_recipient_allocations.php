<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_products', function (Blueprint $table) {
            $table->unsignedInteger('course_rule_version')->default(0)->after('version');
        });

        Schema::create('consumption_event_recipient_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('consumption_event_id');
            $table->foreignId('staff_id');
            $table->foreignId('compensation_role_id')->nullable();
            // MySQL unique indexes treat NULL values as distinct. A non-null role key
            // keeps legacy/no-role delivery projections truly unique as well.
            $table->unsignedBigInteger('role_key')->default(0);
            $table->string('recipient_type', 24);
            $table->unsignedSmallInteger('allocation_bps');
            $table->unsignedBigInteger('allocated_value_cents')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'site_id', 'consumption_event_id', 'recipient_type', 'role_key', 'staff_id'],
                'cons_event_recipient_unique',
            );
            $table->index(['tenant_id', 'site_id', 'recipient_type', 'staff_id'], 'cons_event_recipient_report_idx');
            $table->foreign(['tenant_id', 'site_id'], 'cons_event_recipient_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'consumption_event_id'],
                'cons_event_recipient_event_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('consumption_events')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'cons_event_recipient_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'compensation_role_id'],
                'cons_event_recipient_role_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE consumption_event_recipient_allocations ADD CONSTRAINT cons_event_recipient_role_key_chk CHECK (role_key = COALESCE(compensation_role_id, 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_event_recipient_allocations');
        Schema::table('card_products', function (Blueprint $table) {
            $table->dropColumn('course_rule_version');
        });
    }
};
