<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subscription_plan', 40)->nullable()->after('status');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
            $table->string('subscription_status', 24)->default('active')->after('subscription_expires_at');
        });

        Schema::create('platform_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 80);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('original_price_cents');
            $table->string('currency', 8)->default('CNY');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('platform_service_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('title', 120);
            $table->text('html');
            $table->timestamp('effective_at');
            $table->string('status', 24)->default('current')->index();
            $table->boolean('support_contact_enabled')->default(false);
            $table->string('support_contact_name', 80)->nullable();
            $table->string('support_protocol_url', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_service_agreements');
        Schema::dropIfExists('platform_subscription_plans');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan', 'subscription_expires_at', 'subscription_status']);
        });
    }
};
