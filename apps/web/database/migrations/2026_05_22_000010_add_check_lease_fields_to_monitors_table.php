<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->timestamp('check_in_progress_until')->nullable()->after('next_check_at');
            $table->uuid('last_check_event_id')->nullable()->after('check_in_progress_until');

            $table->index(['enabled', 'next_check_at', 'check_in_progress_until'], 'monitors_due_lease_index');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropIndex('monitors_due_lease_index');
            $table->dropColumn(['check_in_progress_until', 'last_check_event_id']);
        });
    }
};
