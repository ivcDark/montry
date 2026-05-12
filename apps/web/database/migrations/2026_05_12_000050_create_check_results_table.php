<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 50);
            $table->string('status', 32);
            $table->timestamp('checked_at');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('raw_result')->nullable();
            $table->jsonb('normalized_result')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'checked_at']);
            $table->index(['organization_id', 'checked_at']);
            $table->index(['check_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_results');
    }
};
