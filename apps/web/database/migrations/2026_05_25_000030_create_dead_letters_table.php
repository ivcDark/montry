<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('source', 64);
            $table->string('type', 128);
            $table->string('status', 32)->default('open');
            $table->boolean('recoverable')->default(false);
            $table->string('idempotency_key', 256)->nullable()->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->string('error_class', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('payload');
            $table->jsonb('context');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamp('failed_at');
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamps();

            $table->index(['source', 'failed_at']);
            $table->index(['type', 'failed_at']);
            $table->index(['status', 'failed_at']);
            $table->index(['recoverable', 'status']);
            $table->index(['organization_id', 'failed_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['correlation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letters');
    }
};

