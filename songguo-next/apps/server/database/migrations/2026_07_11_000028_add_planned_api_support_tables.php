<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->string('display_color', 24)->nullable()->after('session_kind');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->json('points_policy')->nullable()->after('points_description_text');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('card_face_library')->nullable()->after('carousel_default_image_url');
        });

        Schema::create('order_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id');
            $table->text('body');
            $table->uuid('command_key');
            $table->foreignId('author_staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'order_internal_notes_command_unique');
            $table->index(['tenant_id', 'order_id', 'created_at'], 'order_internal_notes_order_idx');
            $table->foreign(['tenant_id', 'order_id'], 'order_internal_notes_tenant_order_fk')
                ->references(['tenant_id', 'id'])
                ->on('member_card_orders')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'author_staff_id'], 'order_internal_notes_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });

        Schema::create('ledger_reconciliation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('dry_run')->default(false);
            $table->string('command_key', 120);
            $table->foreignId('requested_by_staff_id')->constrained('staff')->restrictOnDelete();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'site_id', 'command_key'], 'ledger_reconciliation_jobs_command_unique');
            $table->index(['tenant_id', 'site_id', 'created_at']);
            $table->foreign(['tenant_id', 'requested_by_staff_id'], 'ledger_reconciliation_jobs_tenant_staff_fk')
                ->references(['tenant_id', 'id'])
                ->on('staff')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reconciliation_jobs');
        Schema::dropIfExists('order_internal_notes');

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('card_face_library');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('points_policy');
        });

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->dropColumn('display_color');
        });
    }
};
