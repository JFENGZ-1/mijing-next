<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_card_orders', function (Blueprint $table) {
            $table->timestamp('payment_expires_at')->nullable()->after('voided_at');
            $table->timestamp('closed_at')->nullable()->after('payment_expires_at');
            $table->string('close_reason', 64)->nullable()->after('closed_at');
            $table->string('payment_transaction_id', 64)->nullable()->after('close_reason');
            $table->unsignedInteger('payment_state_version')->default(1)->after('payment_transaction_id');
            $table->index(
                ['status', 'payment_expires_at', 'id'],
                'member_card_orders_payment_expiry_idx',
            );
            $table->unique(
                ['tenant_id', 'payment_transaction_id'],
                'member_card_orders_payment_transaction_unique',
            );
        });

        Schema::create('payment_notification_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24);
            $table->string('notification_id', 96);
            $table->string('event_type', 64);
            $table->string('order_no', 40)->nullable();
            $table->string('transaction_id', 64)->nullable();
            $table->unsignedBigInteger('amount_total')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('appid', 64)->nullable();
            $table->string('merchant_id', 32)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'notification_id'],
                'payment_notification_inbox_provider_notification_unique',
            );
            $table->index(
                ['status', 'created_at', 'id'],
                'payment_notification_inbox_dispatch_idx',
            );
            $table->index(['provider', 'order_no'], 'payment_notification_inbox_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notification_inbox');

        Schema::table('member_card_orders', function (Blueprint $table) {
            $table->dropUnique('member_card_orders_payment_transaction_unique');
            $table->dropIndex('member_card_orders_payment_expiry_idx');
            $table->dropColumn([
                'payment_expires_at',
                'closed_at',
                'close_reason',
                'payment_transaction_id',
                'payment_state_version',
            ]);
        });
    }
};
