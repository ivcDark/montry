<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->timestamp('occurred_at');
            $table->string('category', 64);
            $table->string('action', 160);
            $table->string('outcome', 32);
            $table->string('source', 64)->default('web');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('target_type', 128)->nullable();
            $table->string('target_id', 128)->nullable();
            $table->string('route_name', 160)->nullable();
            $table->string('request_method', 16)->nullable();
            $table->string('request_path', 512)->nullable();
            $table->string('ip_hash', 128)->nullable();
            $table->string('user_agent_hash', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->jsonb('metadata');
            $table->timestamps();

            $table->index(['category', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['outcome', 'occurred_at']);
            $table->index(['source', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['correlation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

