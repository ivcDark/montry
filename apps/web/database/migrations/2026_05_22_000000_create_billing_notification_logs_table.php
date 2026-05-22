<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->date('event_date');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['subscription_id', 'event_type', 'event_date'], 'billing_notification_unique_event');
            $table->index(['organization_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notification_logs');
    }
};
