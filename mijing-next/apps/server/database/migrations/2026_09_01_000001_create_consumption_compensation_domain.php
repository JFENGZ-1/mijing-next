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
            $table->json('allowed_payment_methods')->nullable()->after('activation_mode');
        });

        Schema::table('member_card_orders', function (Blueprint $table) {
            $table->string('payment_method', 24)->nullable()->after('amount')->index();
            $table->unsignedBigInteger('paid_amount_cents')->nullable()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('paid_amount_cents');
        });

        Schema::table('member_cards', function (Blueprint $table) {
            $table->unsignedInteger('share_assignment_version')->default(0)->after('version');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('command_payload_hash', 64)->nullable()->after('command_key');
            $table->string('cancel_command_key', 120)->nullable()->after('cancelled_at');
            $table->string('cancel_payload_hash', 64)->nullable()->after('cancel_command_key');
            $table->unique(['tenant_id', 'cancel_command_key'], 'appointments_cancel_command_uq');
        });

        Schema::create('catalog_change_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id');
            $table->string('action', 24);
            $table->string('command_key', 120);
            $table->string('payload_hash', 64);
            $table->unsignedInteger('result_version');
            $table->string('reason', 500)->nullable();
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'catalog_change_command_uq');
            $table->index(['tenant_id', 'resource_type', 'resource_id'], 'catalog_change_resource_idx');
            $table->foreign(['tenant_id', 'site_id'], 'catalog_change_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });

        Schema::create('compensation_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('code', 80);
            $table->string('name', 120);
            $table->string('role_type', 24);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id', 'code'], 'comp_roles_site_code_uq');
            $table->unique(['tenant_id', 'site_id', 'id'], 'comp_roles_tenant_site_id_uq');
            $table->foreign(['tenant_id', 'site_id'], 'comp_roles_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'comp_roles_creator_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('staff_compensation_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('staff_id');
            $table->foreignId('compensation_role_id');
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('assigned_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'site_id', 'staff_id', 'compensation_role_id', 'version'],
                'staff_comp_role_assignment_uq',
            );
            $table->index(['tenant_id', 'site_id', 'staff_id', 'status'], 'staff_comp_role_staff_idx');
            $table->foreign(['tenant_id', 'site_id'], 'staff_comp_role_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'staff_comp_role_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'compensation_role_id'],
                'staff_comp_role_role_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
            $table->foreign(['tenant_id', 'assigned_by_staff_id'], 'staff_comp_role_actor_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('compensation_role_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('compensation_role_id');
            $table->string('action', 24);
            $table->string('command_key', 120);
            $table->string('payload_hash', 64);
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('result_version');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'comp_role_command_uq');
            $table->foreign(['tenant_id', 'site_id'], 'comp_role_command_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'site_id', 'compensation_role_id'], 'comp_role_command_role_fk')
                ->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
        });

        Schema::create('staff_compensation_role_assignment_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('staff_id');
            $table->foreignId('staff_compensation_role_assignment_id')->nullable();
            $table->string('action', 24);
            $table->string('command_key', 120);
            $table->string('payload_hash', 64);
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'staff_comp_role_command_uq');
            $table->foreign(['tenant_id', 'site_id'], 'staff_comp_role_command_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'staff_comp_role_command_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign('staff_compensation_role_assignment_id', 'staff_comp_role_command_assignment_fk')
                ->references('id')->on('staff_compensation_role_assignments')->restrictOnDelete();
        });

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->foreignId('delivery_role_id')->nullable()->after('coach_staff_id');
            $table->foreign(['tenant_id', 'site_id', 'delivery_role_id'], 'sessions_delivery_role_fk')
                ->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
        });

        Schema::create('schedule_session_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('schedule_session_id');
            $table->foreignId('staff_id');
            $table->foreignId('compensation_role_id');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('allocation_bps')->default(10000);
            $table->unsignedInteger('assignment_version')->default(1);
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'schedule_session_id', 'staff_id', 'compensation_role_id'],
                'session_staff_assignment_uq',
            );
            $table->index(['tenant_id', 'site_id', 'schedule_session_id'], 'session_staff_assignment_session_idx');
            $table->foreign(['tenant_id', 'site_id'], 'session_staff_assignment_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'schedule_session_id'], 'session_staff_assignment_session_fk')
                ->references(['tenant_id', 'id'])->on('schedule_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'session_staff_assignment_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'compensation_role_id'],
                'session_staff_assignment_role_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
        });

        Schema::create('schedule_session_assignment_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('schedule_session_id');
            $table->string('command_key', 120);
            $table->string('payload_hash', 64);
            $table->unsignedInteger('result_version');
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'session_assignment_cmd_uq');
            $table->foreign(['tenant_id', 'site_id'], 'session_assignment_cmd_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'schedule_session_id'], 'session_assignment_cmd_session_fk')
                ->references(['tenant_id', 'id'])->on('schedule_sessions')->restrictOnDelete();
        });

        Schema::create('card_product_course_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('card_product_id');
            $table->foreignId('course_id');
            $table->string('deduction_type', 24);
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->unsignedInteger('count_units')->nullable();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('supersedes_id')->nullable();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'card_product_id', 'course_id', 'version'],
                'card_course_rule_version_uq',
            );
            $table->index(
                ['tenant_id', 'site_id', 'card_product_id', 'course_id', 'status'],
                'card_course_rule_current_idx',
            );
            $table->foreign(['tenant_id', 'site_id'], 'card_course_rule_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'card_product_id'], 'card_course_rule_product_fk')
                ->references(['tenant_id', 'id'])->on('card_products')->restrictOnDelete();
            $table->foreign(['tenant_id', 'course_id'], 'card_course_rule_course_fk')
                ->references(['tenant_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign('supersedes_id', 'card_course_rule_previous_fk')
                ->references('id')->on('card_product_course_rules')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'card_course_rule_creator_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('course_compensation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('course_id');
            $table->unsignedBigInteger('session_fee_cents')->default(0);
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('supersedes_id')->nullable();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'course_id', 'version'], 'course_comp_rule_version_uq');
            $table->index(['tenant_id', 'site_id', 'course_id', 'status'], 'course_comp_rule_current_idx');
            $table->unique(['tenant_id', 'site_id', 'id'], 'course_comp_rule_tenant_site_id_uq');
            $table->foreign(['tenant_id', 'site_id'], 'course_comp_rule_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'course_id'], 'course_comp_rule_course_fk')
                ->references(['tenant_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign('supersedes_id', 'course_comp_rule_previous_fk')
                ->references('id')->on('course_compensation_rules')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'course_comp_rule_creator_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('course_compensation_role_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('course_compensation_rule_id');
            $table->foreignId('compensation_role_id');
            $table->unsignedInteger('rate_bps');
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'course_compensation_rule_id', 'compensation_role_id'],
                'course_comp_role_rate_uq',
            );
            $table->foreign(
                ['tenant_id', 'site_id', 'course_compensation_rule_id'],
                'course_comp_rate_rule_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('course_compensation_rules')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'compensation_role_id'],
                'course_comp_rate_role_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
        });

        Schema::create('member_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('member_id');
            $table->bigInteger('balance_cents')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'member_id'], 'member_wallet_tenant_member_uq');
            $table->unique(['tenant_id', 'id'], 'member_wallet_tenant_id_uq');
            $table->foreign(['tenant_id', 'member_id'], 'member_wallet_member_fk')
                ->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
        });

        Schema::create('member_wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id')->nullable();
            $table->foreignId('member_wallet_id');
            $table->foreignId('member_id');
            $table->string('entry_type', 40);
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('command_key', 120);
            $table->foreignId('order_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable();
            $table->foreignId('actor_account_id')->nullable();
            $table->foreignId('actor_staff_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'member_wallet_ledger_command_uq');
            $table->index(['tenant_id', 'member_id', 'occurred_at'], 'member_wallet_ledger_member_idx');
            $table->foreign(['tenant_id', 'site_id'], 'member_wallet_ledger_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_wallet_id'], 'member_wallet_ledger_wallet_fk')
                ->references(['tenant_id', 'id'])->on('member_wallets')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'member_wallet_ledger_member_fk')
                ->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_id'], 'member_wallet_ledger_order_fk')
                ->references(['tenant_id', 'id'])->on('member_card_orders')->restrictOnDelete();
            $table->foreign('reversal_of_id', 'member_wallet_ledger_reversal_fk')
                ->references('id')->on('member_wallet_ledger_entries')->restrictOnDelete();
            $table->foreign('actor_account_id', 'member_wallet_ledger_account_fk')
                ->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_staff_id'], 'member_wallet_ledger_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_card_value_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_id');
            $table->foreignId('member_card_id');
            $table->foreignId('order_id')->nullable();
            $table->foreignId('source_ledger_entry_id')->nullable();
            $table->string('source_type', 32);
            $table->string('payment_method', 24)->nullable();
            $table->string('value_provenance', 24)->default('unknown');
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->unsignedBigInteger('entitlement_amount_cents')->nullable();
            $table->unsignedInteger('entitlement_count')->nullable();
            $table->unsignedInteger('remaining_count')->nullable();
            $table->unsignedInteger('entitlement_days')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('product_version')->nullable();
            $table->string('command_key', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'command_key'], 'member_card_value_lot_command_uq');
            $table->index(['tenant_id', 'member_card_id', 'id'], 'member_card_value_lot_card_idx');
            $table->foreign(['tenant_id', 'site_id'], 'member_card_value_lot_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'member_card_value_lot_member_fk')
                ->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'member_card_value_lot_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_id'], 'member_card_value_lot_order_fk')
                ->references(['tenant_id', 'id'])->on('member_card_orders')->restrictOnDelete();
            $table->foreign('source_ledger_entry_id', 'member_card_value_lot_ledger_fk')
                ->references('id')->on('entitlement_ledger_entries')->restrictOnDelete();
        });

        Schema::create('member_card_share_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_card_id');
            $table->foreignId('staff_id');
            $table->foreignId('compensation_role_id');
            $table->unsignedInteger('allocation_bps')->default(10000);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('command_key', 120)->nullable();
            $table->foreignId('assigned_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'member_card_id', 'staff_id', 'compensation_role_id', 'version'],
                'member_card_share_version_uq',
            );
            $table->unique(['tenant_id', 'command_key'], 'member_card_share_command_uq');
            $table->index(['tenant_id', 'member_card_id', 'status'], 'member_card_share_current_idx');
            $table->foreign(['tenant_id', 'site_id'], 'member_card_share_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'member_card_share_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'member_card_share_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'site_id', 'compensation_role_id'],
                'member_card_share_role_fk',
            )->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
            $table->foreign(['tenant_id', 'assigned_by_staff_id'], 'member_card_share_actor_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_card_share_assignment_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_card_id');
            $table->string('command_key', 120);
            $table->string('payload_hash', 64);
            $table->string('actor_type', 24)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('result_version');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'member_card_share_cmd_uq');
            $table->foreign(['tenant_id', 'site_id'], 'member_card_share_cmd_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'member_card_share_cmd_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
        });

        Schema::create('entitlement_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('appointment_id');
            $table->foreignId('session_id');
            $table->foreignId('course_id');
            $table->foreignId('member_id');
            $table->foreignId('member_card_id');
            $table->foreignId('card_product_course_rule_id')->nullable();
            $table->foreignId('ledger_entry_id')->nullable();
            $table->string('deduction_type', 24);
            $table->unsignedBigInteger('reserved_amount_cents')->nullable();
            $table->unsignedInteger('reserved_count')->nullable();
            $table->string('status', 24)->default('reserved')->index();
            $table->string('command_key', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'appointment_id'], 'entitlement_reservation_appointment_uq');
            $table->unique(['tenant_id', 'command_key'], 'entitlement_reservation_command_uq');
            $table->index(['tenant_id', 'member_card_id', 'status'], 'entitlement_reservation_card_idx');
            $table->foreign(['tenant_id', 'site_id'], 'entitlement_reservation_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'appointment_id'], 'entitlement_reservation_appt_fk')
                ->references(['tenant_id', 'id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'], 'entitlement_reservation_session_fk')
                ->references(['tenant_id', 'id'])->on('schedule_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'course_id'], 'entitlement_reservation_course_fk')
                ->references(['tenant_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'entitlement_reservation_member_fk')
                ->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'entitlement_reservation_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign('card_product_course_rule_id', 'entitlement_reservation_rule_fk')
                ->references('id')->on('card_product_course_rules')->restrictOnDelete();
            $table->foreign('ledger_entry_id', 'entitlement_reservation_ledger_fk')
                ->references('id')->on('entitlement_ledger_entries')->restrictOnDelete();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24)->default('open')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('create_command_key', 120)->nullable();
            $table->string('create_reason', 500)->nullable();
            $table->string('created_by_type', 24)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->string('close_command_key', 120)->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->string('closed_by_type', 24)->nullable();
            $table->unsignedBigInteger('closed_by_id')->nullable();
            $table->foreignId('closed_by_staff_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->timestamp('metrics_snapshotted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id', 'starts_on', 'ends_on'], 'payroll_period_range_uq');
            $table->unique(['tenant_id', 'create_command_key'], 'payroll_period_create_cmd_uq');
            $table->unique(['tenant_id', 'close_command_key'], 'payroll_period_close_cmd_uq');
            $table->unique(['tenant_id', 'site_id', 'id'], 'payroll_period_tenant_site_id_uq');
            $table->foreign(['tenant_id', 'site_id'], 'payroll_period_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'payroll_period_creator_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'closed_by_staff_id'], 'payroll_period_closer_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('consumption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('appointment_id');
            $table->foreignId('session_id');
            $table->foreignId('course_id');
            $table->foreignId('member_id');
            $table->foreignId('member_card_id');
            $table->foreignId('coach_staff_id');
            $table->foreignId('delivery_role_id')->nullable();
            $table->foreignId('entitlement_reservation_id')->nullable();
            $table->foreignId('ledger_entry_id')->nullable();
            $table->foreignId('card_product_course_rule_id')->nullable();
            $table->foreignId('course_compensation_rule_id')->nullable();
            $table->foreignId('value_lot_id')->nullable();
            $table->date('business_date');
            $table->string('card_type', 24);
            $table->unsignedBigInteger('deducted_amount_cents')->nullable();
            $table->unsignedInteger('deducted_count')->nullable();
            $table->unsignedBigInteger('consumed_value_cents')->nullable();
            $table->string('value_provenance', 24)->default('unknown');
            $table->string('status', 24)->default('final')->index();
            $table->string('source', 24);
            $table->string('command_key', 120);
            $table->string('reversal_command_key', 120)->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->string('reversed_by_type', 24)->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'appointment_id'], 'consumption_event_appointment_uq');
            $table->unique(['tenant_id', 'command_key'], 'consumption_event_command_uq');
            $table->unique(['tenant_id', 'reversal_command_key'], 'consumption_event_reversal_command_uq');
            $table->unique(['tenant_id', 'site_id', 'id'], 'consumption_event_tenant_site_id_uq');
            $table->index(['tenant_id', 'site_id', 'business_date', 'status'], 'consumption_event_site_day_idx');
            $table->index(['tenant_id', 'member_id', 'business_date'], 'consumption_event_member_day_idx');
            $table->index(['tenant_id', 'coach_staff_id', 'business_date'], 'consumption_event_coach_day_idx');
            $table->foreign(['tenant_id', 'site_id'], 'consumption_event_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'appointment_id'], 'consumption_event_appt_fk')
                ->references(['tenant_id', 'id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'], 'consumption_event_session_fk')
                ->references(['tenant_id', 'id'])->on('schedule_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'course_id'], 'consumption_event_course_fk')
                ->references(['tenant_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'consumption_event_member_fk')
                ->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'consumption_event_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'coach_staff_id'], 'consumption_event_coach_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'site_id', 'delivery_role_id'], 'consumption_event_delivery_role_fk')
                ->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
            $table->foreign('entitlement_reservation_id', 'consumption_event_reservation_fk')
                ->references('id')->on('entitlement_reservations')->restrictOnDelete();
            $table->foreign('ledger_entry_id', 'consumption_event_ledger_fk')
                ->references('id')->on('entitlement_ledger_entries')->restrictOnDelete();
            $table->foreign('card_product_course_rule_id', 'consumption_event_card_rule_fk')
                ->references('id')->on('card_product_course_rules')->restrictOnDelete();
            $table->foreign('course_compensation_rule_id', 'consumption_event_comp_rule_fk')
                ->references('id')->on('course_compensation_rules')->restrictOnDelete();
            $table->foreign('value_lot_id', 'consumption_event_value_lot_fk')
                ->references('id')->on('member_card_value_lots')->restrictOnDelete();
        });

        Schema::create('period_day_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_card_id');
            $table->foreignId('value_lot_id')->nullable();
            $table->date('business_date');
            $table->unsignedBigInteger('day_value_cents')->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('latest_revision')->default(0);
            $table->string('value_provenance', 24)->default('unknown');
            $table->string('status', 24)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'member_card_id', 'business_date'], 'period_day_bucket_card_date_uq');
            $table->index(['tenant_id', 'site_id', 'business_date'], 'period_day_bucket_site_date_idx');
            $table->foreign(['tenant_id', 'site_id'], 'period_day_bucket_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'period_day_bucket_card_fk')
                ->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign('value_lot_id', 'period_day_bucket_value_lot_fk')
                ->references('id')->on('member_card_value_lots')->restrictOnDelete();
        });

        Schema::create('period_day_bucket_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('period_day_bucket_id');
            $table->unsignedInteger('revision');
            $table->unsignedBigInteger('day_value_cents')->nullable();
            $table->unsignedInteger('event_count');
            $table->unsignedBigInteger('allocated_value_cents')->nullable();
            $table->string('reason', 120);
            $table->string('command_key', 120);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'period_day_bucket_id', 'revision'], 'period_day_bucket_revision_uq');
            $table->unique(['tenant_id', 'command_key'], 'period_day_bucket_revision_command_uq');
            $table->foreign('period_day_bucket_id', 'period_day_bucket_revision_bucket_fk')
                ->references('id')->on('period_day_buckets')->restrictOnDelete();
        });

        Schema::create('commission_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('consumption_event_id');
            $table->foreignId('period_day_bucket_revision_id')->nullable();
            $table->foreignId('staff_id');
            $table->foreignId('compensation_role_id')->nullable();
            $table->foreignId('course_compensation_rule_id')->nullable();
            $table->foreignId('payroll_period_id')->nullable();
            $table->string('component', 32);
            $table->string('line_type', 24);
            $table->unsignedBigInteger('base_value_cents')->default(0);
            $table->unsignedInteger('rate_bps')->nullable();
            $table->integer('allocation_bps')->nullable();
            $table->bigInteger('amount_cents');
            $table->foreignId('reverses_line_id')->nullable();
            $table->string('command_key', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'commission_settlement_line_command_uq');
            $table->index(['tenant_id', 'site_id', 'staff_id', 'occurred_at'], 'commission_line_staff_idx');
            $table->index(['tenant_id', 'consumption_event_id', 'component'], 'commission_line_event_idx');
            $table->foreign(['tenant_id', 'site_id'], 'commission_line_site_fk')
                ->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign('consumption_event_id', 'commission_line_event_fk')
                ->references('id')->on('consumption_events')->restrictOnDelete();
            $table->foreign('period_day_bucket_revision_id', 'commission_line_bucket_revision_fk')
                ->references('id')->on('period_day_bucket_revisions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_id'], 'commission_line_staff_fk')
                ->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'site_id', 'compensation_role_id'], 'commission_line_role_fk')
                ->references(['tenant_id', 'site_id', 'id'])->on('compensation_roles')->restrictOnDelete();
            $table->foreign('course_compensation_rule_id', 'commission_line_rule_fk')
                ->references('id')->on('course_compensation_rules')->restrictOnDelete();
            $table->foreign('payroll_period_id', 'commission_line_period_fk')
                ->references('id')->on('payroll_periods')->restrictOnDelete();
            $table->foreign('reverses_line_id', 'commission_line_reversal_fk')
                ->references('id')->on('commission_settlement_lines')->restrictOnDelete();
        });

        $now = now();
        $permissionRows = [
            ['name' => '查看业务角色', 'code' => 'compensation.role.read', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理业务角色', 'code' => 'compensation.role.write', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看耗卡规则', 'code' => 'compensation.rule.read', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理耗卡规则', 'code' => 'compensation.rule.write', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看耗卡结算', 'code' => 'consumption.read', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '调整耗卡结算', 'code' => 'consumption.adjust', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员钱包', 'code' => 'wallet.read', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '调整会员钱包', 'code' => 'wallet.adjust', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理工资期间', 'code' => 'payroll.period.close', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
        ];
        $permissionCodes = collect($permissionRows)->pluck('code')->all();
        $existingPermissions = DB::table('permissions')
            ->whereIn('code', $permissionCodes)
            ->get()
            ->keyBy('code');
        Schema::create('consumption_permission_migration_state', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->boolean('was_existing');
            $table->string('original_name')->nullable();
            $table->string('original_module')->nullable();
            $table->timestamp('original_updated_at')->nullable();
        });
        Schema::create('consumption_permission_grant_state', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['role_id', 'permission_id'], 'consumption_permission_grant_state_pk');
        });
        foreach ($permissionCodes as $code) {
            $existing = $existingPermissions->get($code);
            DB::table('consumption_permission_migration_state')->insert([
                'code' => $code,
                'permission_id' => $existing?->id,
                'was_existing' => $existing !== null,
                'original_name' => $existing?->name,
                'original_module' => $existing?->module,
                'original_updated_at' => $existing?->updated_at,
            ]);
        }

        DB::table('permissions')->upsert($permissionRows, ['code'], ['name', 'module', 'updated_at']);

        $newPermissionIds = DB::table('permissions')->whereIn('code', [
            'compensation.role.read',
            'compensation.role.write',
            'compensation.rule.read',
            'compensation.rule.write',
            'consumption.read',
            'consumption.adjust',
            'wallet.read',
            'wallet.adjust',
            'payroll.period.close',
        ])->pluck('id');
        foreach (DB::table('permissions')->whereIn('code', $permissionCodes)->get() as $permission) {
            DB::table('consumption_permission_migration_state')
                ->where('code', $permission->code)
                ->update(['permission_id' => $permission->id]);
        }
        $ownerRoleIds = DB::table('roles')
            ->where('is_system', true)
            ->where('status', 'active')
            ->where('code', 'owner')
            ->pluck('id');
        $ownerGrants = [];
        foreach ($ownerRoleIds as $roleId) {
            foreach ($newPermissionIds as $permissionId) {
                $grant = ['role_id' => $roleId, 'permission_id' => $permissionId];
                if (! DB::table('permission_role')->where($grant)->exists()) {
                    $ownerGrants[] = $grant;
                    DB::table('consumption_permission_grant_state')->insert($grant);
                }
            }
        }
        if ($ownerGrants !== []) {
            DB::table('permission_role')->insertOrIgnore($ownerGrants);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE compensation_roles ADD CONSTRAINT compensation_roles_type_chk CHECK (role_type IN ('delivery', 'share'))");
            DB::statement('ALTER TABLE course_compensation_role_rates ADD CONSTRAINT course_comp_rates_bps_chk CHECK (rate_bps <= 10000)');
            DB::statement('ALTER TABLE staff_compensation_role_assignments ADD CONSTRAINT staff_comp_role_dates_chk CHECK (active_until IS NULL OR active_from IS NULL OR active_until >= active_from)');
            DB::statement('ALTER TABLE member_card_share_assignments ADD CONSTRAINT member_card_share_bps_chk CHECK (allocation_bps <= 10000)');
            DB::statement('ALTER TABLE schedule_session_staff_assignments ADD CONSTRAINT session_staff_assignment_bps_chk CHECK (allocation_bps <= 10000)');
            DB::statement('ALTER TABLE member_wallets ADD CONSTRAINT member_wallet_balance_chk CHECK (balance_cents >= 0)');
            DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT payroll_period_dates_chk CHECK (ends_on >= starts_on)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consumption_permission_grant_state')) {
            foreach (DB::table('consumption_permission_grant_state')->get() as $grant) {
                DB::table('permission_role')
                    ->where('role_id', $grant->role_id)
                    ->where('permission_id', $grant->permission_id)
                    ->delete();
            }
        }
        if (Schema::hasTable('consumption_permission_migration_state')) {
            foreach (DB::table('consumption_permission_migration_state')->get() as $state) {
                if ((bool) $state->was_existing) {
                    DB::table('permissions')->where('id', $state->permission_id)->update([
                        'name' => $state->original_name,
                        'module' => $state->original_module,
                        'updated_at' => $state->original_updated_at,
                    ]);

                    continue;
                }
                // Do not destroy grants made after deployment. A newly-created
                // permission is removed only when no external role now references it.
                if (! DB::table('permission_role')->where('permission_id', $state->permission_id)->exists()) {
                    DB::table('permissions')->where('id', $state->permission_id)->delete();
                }
            }
        }
        Schema::dropIfExists('consumption_permission_grant_state');
        Schema::dropIfExists('consumption_permission_migration_state');

        Schema::dropIfExists('commission_settlement_lines');
        Schema::dropIfExists('period_day_bucket_revisions');
        Schema::dropIfExists('period_day_buckets');
        Schema::dropIfExists('consumption_events');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('entitlement_reservations');
        Schema::dropIfExists('member_card_share_assignment_commands');
        Schema::dropIfExists('member_card_share_assignments');
        Schema::dropIfExists('member_card_value_lots');
        Schema::dropIfExists('member_wallet_ledger_entries');
        Schema::dropIfExists('member_wallets');
        Schema::dropIfExists('course_compensation_role_rates');
        Schema::dropIfExists('course_compensation_rules');
        Schema::dropIfExists('card_product_course_rules');

        Schema::dropIfExists('schedule_session_assignment_commands');
        Schema::dropIfExists('schedule_session_staff_assignments');

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->dropForeign('sessions_delivery_role_fk');
            $table->dropColumn('delivery_role_id');
        });

        Schema::dropIfExists('staff_compensation_role_assignment_commands');
        Schema::dropIfExists('compensation_role_commands');
        Schema::dropIfExists('staff_compensation_role_assignments');
        Schema::dropIfExists('compensation_roles');
        Schema::dropIfExists('catalog_change_commands');

        Schema::table('member_card_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_amount_cents', 'paid_at']);
        });

        Schema::table('member_cards', function (Blueprint $table) {
            $table->dropColumn('share_assignment_version');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_cancel_command_uq');
            $table->dropColumn(['command_payload_hash', 'cancel_command_key', 'cancel_payload_hash']);
        });

        Schema::table('card_products', function (Blueprint $table) {
            $table->dropColumn('allowed_payment_methods');
        });
    }
};
