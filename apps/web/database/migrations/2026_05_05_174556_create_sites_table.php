<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('folder_id')
                ->constrained('folders')
                ->restrictOnDelete();

            $table->foreignId('created_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('url');
            $table->string('scheme', 16)->default('https');
            $table->string('host');
            $table->unsignedInteger('port')->nullable();
            $table->string('path')->default('/');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
