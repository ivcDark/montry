<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncPaidChannels(['email', 'telegram', 'max']);
    }

    public function down(): void
    {
        $this->syncPaidChannels(['email', 'telegram']);
    }

    /**
     * @param array<int, string> $channels
     */
    private function syncPaidChannels(array $channels): void
    {
        DB::table('plan_limits')
            ->where('key', 'notification_channels')
            ->whereIn('plan_id', function ($query): void {
                $query->select('id')
                    ->from('plans')
                    ->whereIn('code', ['pro', 'team', 'plus']);
            })
            ->update([
                'value' => json_encode(['channels' => $channels]),
                'updated_at' => now(),
            ]);
    }
};