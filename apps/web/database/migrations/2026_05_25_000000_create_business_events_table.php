<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 160);
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('plan_code', 64)->nullable();
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->string('status', 64)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->jsonb('payload');
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['occurred_at']);
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['plan_code', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'occurred_at']);
            $table->index(['correlation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_events');
    }
};

