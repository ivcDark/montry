<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents')->default(0);
            $table->string('currency', 3)->default('RUB');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'code']);
            $table->index(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
