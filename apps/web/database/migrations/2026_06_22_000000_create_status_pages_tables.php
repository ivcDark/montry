<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_incident_history')->default(true);
            $table->string('accent_color', 7)->default('#2FA568');
            $table->timestamps();

            $table->index(['organization_id', 'is_published']);
        });

        Schema::create('status_page_monitors', function (Blueprint $table): void {
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['status_page_id', 'monitor_id']);
            $table->index(['status_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_monitors');
        Schema::dropIfExists('status_pages');
    }
};
