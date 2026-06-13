<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('monitored_resource_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('status', 32)->default('unknown');
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->unsignedInteger('timeout_ms')->default(10000);
            $table->jsonb('settings');
            $table->jsonb('expected');
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_successes')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type']);
            $table->index(['enabled', 'next_check_at']);
            $table->index(['monitored_resource_id', 'type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
