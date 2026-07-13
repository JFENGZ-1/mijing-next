<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('card_type', 24);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('face_value', 12, 2)->nullable();
            $table->unsignedInteger('initial_count')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->string('validity_mode', 32)->nullable();
            $table->string('activation_mode', 32)->default('immediate');
            $table->json('scope_config')->nullable();
            $table->json('booking_rules')->nullable();
            $table->string('sale_status', 24)->default('on_sale')->index();
            $table->string('catalog_status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'card_products_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'catalog_status', 'sale_status'], 'card_products_site_catalog_idx');
            $table->foreign(['tenant_id', 'site_id'], 'card_products_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_staff_id'], 'card_products_tenant_created_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('card_product_course_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('card_product_id');
            $table->string('scope_kind', 24);
            $table->string('scope_key', 80);
            $table->string('display_name', 120)->nullable();
            $table->decimal('price_override', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'card_product_id', 'scope_kind', 'scope_key'], 'card_product_course_scopes_unique');
            $table->foreign(['tenant_id', 'card_product_id'], 'card_product_course_scopes_product_fk')->references(['tenant_id', 'id'])->on('card_products')->cascadeOnDelete();
        });

        Schema::create('member_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_id');
            $table->foreignId('card_product_id')->nullable();
            $table->string('card_type', 24);
            $table->string('card_no', 40);
            $table->string('status', 24)->default('pending_activation')->index();
            $table->string('member_visibility', 24)->default('visible');
            $table->json('product_snapshot');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('cached_balance', 12, 2)->nullable();
            $table->unsignedInteger('cached_remaining_count')->nullable();
            $table->json('freeze_state')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by_staff_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'member_cards_tenant_id_id_unique');
            $table->unique(['tenant_id', 'card_no'], 'member_cards_tenant_card_no_unique');
            $table->index(['tenant_id', 'member_id', 'status'], 'member_cards_member_status_idx');
            $table->foreign(['tenant_id', 'site_id'], 'member_cards_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'member_cards_tenant_member_fk')->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign(['tenant_id', 'card_product_id'], 'member_cards_tenant_product_fk')->references(['tenant_id', 'id'])->on('card_products')->restrictOnDelete();
            $table->foreign(['tenant_id', 'issued_by_staff_id'], 'member_cards_tenant_issued_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('entitlement_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->foreignId('member_card_id');
            $table->foreignId('member_id');
            $table->string('entry_type', 40);
            $table->string('direction', 16);
            $table->decimal('amount_delta', 12, 2)->nullable();
            $table->integer('count_delta')->nullable();
            $table->date('valid_from_after')->nullable();
            $table->date('valid_until_after')->nullable();
            $table->string('count_group_key', 80)->nullable();
            $table->foreignId('reversal_of_id')->nullable();
            $table->uuid('command_key')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('actor_account_id')->nullable();
            $table->foreignId('actor_staff_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'command_key'], 'entitlement_ledger_entries_command_unique');
            $table->index(['tenant_id', 'member_card_id', 'occurred_at'], 'entitlement_ledger_entries_card_idx');
            $table->index(['tenant_id', 'member_id', 'occurred_at'], 'entitlement_ledger_entries_member_idx');
            $table->foreign(['tenant_id', 'site_id'], 'entitlement_ledger_entries_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_card_id'], 'entitlement_ledger_entries_tenant_card_fk')->references(['tenant_id', 'id'])->on('member_cards')->restrictOnDelete();
            $table->foreign(['tenant_id', 'member_id'], 'entitlement_ledger_entries_tenant_member_fk')->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('entitlement_ledger_entries')->restrictOnDelete();
            $table->foreign('actor_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_staff_id'], 'entitlement_ledger_entries_tenant_staff_fk')->references(['tenant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_ledger_entries');
        Schema::dropIfExists('member_cards');
        Schema::dropIfExists('card_product_course_scopes');
        Schema::dropIfExists('card_products');
    }
};
