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
        Schema::create('site_monitors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('type', 50);
            $table->string('name');

            $table->boolean('is_enabled')->default(true);

            /**
             * Shared fields.
             */
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->unsignedInteger('timeout_ms')->default(10000);

            /**
             * Type-specific config.
             *
             * HTTP example:
             * {
             *   "method": "GET",
             *   "path": "/",
             *   "expected_status_min": 200,
             *   "expected_status_max": 399,
             *   "follow_redirects": true
             * }
             */
            $table->jsonb('settings')->nullable();

            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->index(['site_id', 'type']);
            $table->index(['is_enabled', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_monitors');
    }
};
