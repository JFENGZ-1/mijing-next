<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('membership_agreement_html')->nullable()->after('crm_field_policy');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('notification_channel_config')->nullable()->after('member_onboarding_help');
        });

        Schema::table('site_notices', function (Blueprint $table) {
            $table->unsignedSmallInteger('display_days')->nullable()->after('body');
        });

        Schema::create('site_closure_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('reason', 240)->nullable();
            $table->date('begin_date');
            $table->date('end_date');
            $table->string('status', 24)->default('scheduled')->index();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'site_closure_periods_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'begin_date'], 'site_closure_periods_site_feed_idx');
            $table->foreign(['tenant_id', 'site_id'], 'site_closure_periods_tenant_site_fk')
                ->references(['tenant_id', 'id'])
                ->on('sites')
                ->restrictOnDelete();
        });

        Schema::create('staff_vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->foreignId('staff_id');
            $table->timestamp('begin_at');
            $table->timestamp('end_at');
            $table->string('group_booking_policy', 24)->default('block');
            $table->string('private_booking_policy', 24)->default('allow');
            $table->string('status', 24)->default('scheduled')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'staff_vacations_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'staff_id', 'begin_at'], 'staff_vacations_site_staff_idx');
            $table->foreign(['tenant_id', 'site_id'], 'staff_vacations_tenant_site_fk')
                ->references(['tenant_id', 'id'])
                ->on('sites')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'staff_vacations_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_vacations');
        Schema::dropIfExists('site_closure_periods');

        Schema::table('site_notices', function (Blueprint $table) {
            $table->dropColumn('display_days');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('notification_channel_config');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('membership_agreement_html');
        });
    }
};
