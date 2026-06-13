<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 64);
            $table->string('level', 16);
            $table->string('event', 128);
            $table->text('message')->nullable();
            $table->string('request_method', 16)->nullable();
            $table->string('request_path')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['provider', 'event']);
            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
