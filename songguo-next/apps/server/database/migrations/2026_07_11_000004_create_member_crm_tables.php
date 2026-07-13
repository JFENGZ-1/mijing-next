<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('owner_staff_id')->nullable()->after('home_site_id');
            $table->string('app_access_status', 24)->default('allowed')->after('status');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreign(['tenant_id', 'owner_staff_id'], 'members_tenant_owner_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
            $table->foreign(['tenant_id', 'status_changed_by_staff_id'], 'members_tenant_status_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_crm_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('member_id')->unique();
            $table->string('name', 80);
            $table->string('gender', 24)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('mobile_ciphertext')->nullable();
            $table->char('mobile_hash', 64)->nullable();
            $table->char('mobile_last4', 4)->nullable();
            $table->string('mobile_source', 40)->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'mobile_hash']);
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->cascadeOnDelete();
        });

        Schema::create('member_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('member_id');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('reason', 500);
            $table->foreignId('site_id');
            $table->foreignId('actor_staff_id');
            $table->uuid('request_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_staff_id'])->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('member_id');
            $table->foreignId('site_id')->nullable();
            $table->foreignId('author_staff_id');
            $table->foreignId('correction_of_id')->nullable()->constrained('member_notes')->restrictOnDelete();
            $table->text('body');
            $table->uuid('request_id')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'author_staff_id'])->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('member_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('normalized_name', 80);
            $table->string('color', 16)->default('#667085');
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'normalized_name']);
            $table->unique(['tenant_id', 'id'], 'member_tags_tenant_id_id_unique');
        });

        Schema::create('member_tag_assignments', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('member_id');
            $table->foreignId('member_tag_id');
            $table->foreignId('assigned_by_staff_id');
            $table->timestamp('assigned_at');
            $table->primary(['member_id', 'member_tag_id']);
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'member_tag_id'])->references(['tenant_id', 'id'])->on('member_tags')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'assigned_by_staff_id'])->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('actor_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('action', 120)->index();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->uuid('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });

        DB::statement('INSERT INTO member_crm_profiles (tenant_id, member_id, name, created_at, updated_at) SELECT m.tenant_id, m.id, COALESCE(mp.display_name, a.display_name, CONCAT("会员", m.id)), NOW(), NOW() FROM members m LEFT JOIN accounts a ON a.id = m.account_id LEFT JOIN member_profiles mp ON mp.account_id = m.account_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('member_tag_assignments');
        Schema::dropIfExists('member_tags');
        Schema::dropIfExists('member_notes');
        Schema::dropIfExists('member_status_events');
        Schema::dropIfExists('member_crm_profiles');
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign('members_tenant_owner_staff_fk');
            $table->dropForeign('members_tenant_status_staff_fk');
            $table->dropColumn(['owner_staff_id', 'app_access_status', 'status_changed_at', 'status_changed_by_staff_id', 'archived_at']);
        });
    }
};
