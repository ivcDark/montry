<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_state_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['monitor_id', 'changed_at']);
            $table->index(['organization_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_state_changes');
    }
};
