<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('ranking_opt_in')->default(false)->after('app_access_status');
        });

        Schema::create('point_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->integer('amount_delta');
            $table->string('direction', 16);
            $table->string('reason', 500);
            $table->uuid('command_key');
            $table->foreignId('actor_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('actor_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'point_ledger_entries_tenant_command_key_unique');
            $table->index(['tenant_id', 'member_id', 'created_at'], 'point_ledger_entries_member_feed_idx');
        });

        Schema::create('member_point_balances', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamp('updated_at')->useCurrent();
            $table->primary(['tenant_id', 'member_id']);
        });

        Schema::create('site_official_account_follow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('image_url', 512);
            $table->text('instructions_text');
            $table->string('status', 24)->default('published')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id'], 'site_official_account_follow_tenant_site_unique');
            $table->foreign(['tenant_id', 'site_id'], 'site_official_account_follow_tenant_site_fk')
                ->references(['tenant_id', 'id'])
                ->on('sites')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_official_account_follow');
        Schema::dropIfExists('member_point_balances');
        Schema::dropIfExists('point_ledger_entries');
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('ranking_opt_in');
        });
    }
};
