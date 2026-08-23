<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('merchant_id', 64)->nullable();
            $table->string('merchant_serial_no', 128)->nullable();
            $table->longText('private_key')->nullable();
            $table->text('api_v3_key')->nullable();
            $table->longText('platform_public_key')->nullable();
            $table->string('platform_public_key_id', 128)->nullable();
            $table->string('notify_url', 500)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by_super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_configs');
    }
};
