<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_link_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('lead_member_id');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('resolved_member_id')->nullable();
            $table->string('status', 40)->index();
            $table->string('member_decision', 24)->nullable();
            $table->string('evidence_type', 40);
            $table->char('evidence_hash', 64);
            $table->unsignedInteger('member_profile_version');
            $table->char('active_key', 64)->nullable()->unique();
            $table->timestamp('member_decided_at')->nullable();
            $table->foreignId('reviewed_by_staff_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'lead_member_id'])->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'resolved_member_id'])->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'reviewed_by_staff_id'])->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->index(['tenant_id', 'account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_link_requests');
    }
};
