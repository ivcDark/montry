<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('short_label', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_for_site')->default(false);
            $table->boolean('default_enabled')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('unit_price_cents')->default(0);
            $table->string('currency', 3)->default('RUB');
            $table->string('unit_label', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('default_interval_seconds')->default(300);
            $table->unsignedInteger('default_timeout_ms')->default(10000);
            $table->json('default_settings')->nullable();
            $table->json('default_expected')->nullable();
            $table->json('ui_meta')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['is_paid', 'is_active']);
            $table->index(['is_default_for_site', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_types');
    }
};
