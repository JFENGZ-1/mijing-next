<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_card_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_id');
            $table->foreignId('member_card_id')->nullable();
            $table->string('order_no', 40);
            $table->decimal('amount', 12, 2);
            $table->string('status', 24)->default('pending_payment')->index();
            $table->uuid('command_key')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'member_card_orders_tenant_id_id_unique');
            $table->unique(['tenant_id', 'order_no'], 'member_card_orders_tenant_order_no_unique');
            $table->unique(['tenant_id', 'command_key'], 'member_card_orders_command_unique');
            $table->index(['tenant_id', 'site_id', 'member_id', 'status'], 'member_card_orders_member_idx');
            $table->foreign(['tenant_id', 'site_id'], 'member_card_orders_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'member_card_orders_tenant_member_fk')->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'member_card_orders_tenant_card_fk')->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'member_card_orders_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('order_amount_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('order_id');
            $table->string('entry_type', 24);
            $table->decimal('corrected_amount', 12, 2)->nullable();
            $table->foreignId('reversal_of_id')->nullable();
            $table->uuid('command_key')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('actor_staff_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'order_amount_corrections_command_unique');
            $table->index(['tenant_id', 'order_id', 'occurred_at'], 'order_amount_corrections_order_idx');
            $table->foreign(['tenant_id', 'order_id'], 'order_amount_corrections_tenant_order_fk')->references(['tenant_id', 'id'])->on('member_card_orders')->restrictOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('order_amount_corrections')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_staff_id'], 'order_amount_corrections_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_card_reminder_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->json('config');
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id'], 'member_card_reminder_configs_site_unique');
            $table->foreign(['tenant_id', 'site_id'], 'member_card_reminder_configs_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_card_reminder_configs');
        Schema::dropIfExists('order_amount_corrections');
        Schema::dropIfExists('member_card_orders');
    }
};
