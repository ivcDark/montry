<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_resources', function (Blueprint $table): void {
            $table->jsonb('notification_channels')->nullable();
        });

        DB::table('monitored_resources')
            ->whereNull('notification_channels')
            ->update([
                'notification_channels' => json_encode([
                    'email' => true,
                    'telegram' => true,
                    'max' => true,
                ]),
            ]);
    }

    public function down(): void
    {
        Schema::table('monitored_resources', function (Blueprint $table): void {
            $table->dropColumn('notification_channels');
        });
    }
};