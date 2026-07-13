<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('points_enabled')->default(false)->after('timezone');
            $table->boolean('show_month_rank')->default(false)->after('points_enabled');
        });

        Schema::create('site_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('title', 120);
            $table->text('body');
            $table->string('cover_image_url', 512)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'site_notices_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'status', 'published_at'], 'site_notices_site_feed_idx');
            $table->foreign(['tenant_id', 'site_id'], 'site_notices_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });

        Schema::create('site_carousel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id');
            $table->string('image_url', 512);
            $table->string('link_url', 512)->nullable();
            $table->string('status', 24)->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'site_carousel_items_tenant_id_id_unique');
            $table->index(['tenant_id', 'site_id', 'status', 'sort_order'], 'site_carousel_items_site_feed_idx');
            $table->foreign(['tenant_id', 'site_id'], 'site_carousel_items_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_carousel_items');
        Schema::dropIfExists('site_notices');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['points_enabled', 'show_month_rank']);
        });
    }
};
