<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('site_id');
            $table->unsignedInteger('version')->default(1);
            $table->json('policy');
            $table->json('rules')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'site_id'], 'booking_policies_site_unique');
            $table->foreign(['tenant_id', 'site_id'], 'booking_policies_tenant_site_fk')->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_policies');
    }
};
