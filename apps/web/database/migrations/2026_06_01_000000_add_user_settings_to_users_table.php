<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('telegram_notifications_enabled')->default(false)->after('is_blocked');
            $table->string('telegram_connection_token', 96)->nullable()->unique()->after('telegram_notifications_enabled');
            $table->string('telegram_chat_id')->nullable()->after('telegram_connection_token');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->timestamp('telegram_connected_at')->nullable()->after('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_connection_token']);
            $table->dropColumn([
                'telegram_notifications_enabled',
                'telegram_connection_token',
                'telegram_chat_id',
                'telegram_username',
                'telegram_connected_at',
            ]);
        });
    }
};
