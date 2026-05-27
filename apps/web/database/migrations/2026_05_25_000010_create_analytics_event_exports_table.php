<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_event_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_event_id')->unique()->constrained('business_events')->cascadeOnDelete();
            $table->string('event_id', 36);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'business_event_id']);
            $table->index(['event_id']);
            $table->index(['last_attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_event_exports');
    }
};
