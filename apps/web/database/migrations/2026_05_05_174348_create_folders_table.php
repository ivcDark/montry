<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order');
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX folders_one_default_per_organization
                   ON folders (organization_id)
                   WHERE is_default = true'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS folders_one_default_per_organization');

        Schema::dropIfExists('folders');
    }
};
