<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('monitor_types')->where('code', 'dns')->first();

        if ($row === null) {
            return;
        }

        $settings = json_decode((string) $row->default_settings, true) ?: [];
        $settings['warn_on_change'] = false;

        DB::table('monitor_types')
            ->where('code', 'dns')
            ->update([
                'default_settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $row = DB::table('monitor_types')->where('code', 'dns')->first();

        if ($row === null) {
            return;
        }

        $settings = json_decode((string) $row->default_settings, true) ?: [];
        unset($settings['warn_on_change']);

        DB::table('monitor_types')
            ->where('code', 'dns')
            ->update([
                'default_settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
};
