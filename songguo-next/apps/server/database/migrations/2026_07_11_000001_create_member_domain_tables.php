<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'sites_tenant_id_id_unique');
        });

        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 80)->nullable();
            $table->string('avatar_object_key', 500)->nullable();
            $table->string('gender', 24)->nullable();
            $table->date('birth_date')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('mobile_ciphertext')->nullable();
            $table->char('mobile_hash', 64)->nullable()->unique();
            $table->char('mobile_last4', 4)->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_no', 40);
            $table->string('status', 24)->default('lead')->index();
            $table->string('source', 40)->default('member-miniapp');
            $table->foreignId('registration_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('home_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'member_no']);
            $table->unique(['tenant_id', 'account_id']);
            $table->unique(['tenant_id', 'id'], 'members_tenant_id_id_unique');
        });

        Schema::create('member_sites', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->string('relationship_type', 24)->default('registered');
            $table->string('status', 24)->default('active');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->primary(['member_id', 'site_id']);
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->cascadeOnDelete();
        });

        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 64)->default('global');
            $table->string('type', 32);
            $table->string('version', 40);
            $table->string('title', 160);
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_required')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['scope_key', 'type', 'version']);
        });

        Schema::create('legal_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();
            $table->string('action', 24)->default('accepted');
            $table->string('source', 40)->default('member-miniapp');
            $table->uuid('request_id')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['account_id', 'legal_document_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_consents');
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('member_sites');
        Schema::dropIfExists('members');
        Schema::dropIfExists('member_profiles');
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique('sites_tenant_id_id_unique');
        });
    }
};
