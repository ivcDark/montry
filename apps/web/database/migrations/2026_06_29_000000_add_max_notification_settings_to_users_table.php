<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('max_notifications_enabled')->default(false)->after('telegram_connected_at');
            $table->string('max_connection_token', 96)->nullable()->unique()->after('max_notifications_enabled');
            $table->string('max_chat_id')->nullable()->after('max_connection_token');
            $table->string('max_username')->nullable()->after('max_chat_id');
            $table->timestamp('max_connected_at')->nullable()->after('max_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['max_connection_token']);
            $table->dropColumn([
                'max_notifications_enabled',
                'max_connection_token',
                'max_chat_id',
                'max_username',
                'max_connected_at',
            ]);
        });
    }
};