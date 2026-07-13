<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wechat_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('appid', 32);
            $table->string('openid', 64);
            $table->string('unionid', 64)->nullable()->index();
            $table->string('session_key_ciphertext', 500)->nullable();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamps();
            $table->unique(['appid', 'openid']);
            $table->unique(['account_id', 'appid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wechat_identities');
    }
};
