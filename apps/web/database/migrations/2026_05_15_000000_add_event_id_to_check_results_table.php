<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('check_results', 'event_id')) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->string('event_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('check_results', 'event_id')) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->dropUnique(['event_id']);
                $table->dropColumn('event_id');
            });
        }
    }
};
