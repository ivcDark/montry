<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $limits = ['free' => 1, 'pro' => null, 'team' => null];

            foreach ($limits as $code => $limit) {
                $planId = DB::table('plans')->where('code', $code)->value('id');
                if ($planId === null) continue;

                DB::table('plan_limits')->updateOrInsert(
                    ['plan_id' => $planId, 'key' => 'max_projects'],
                    [
                        'value' => json_encode(['limit' => $limit], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        $planIds = DB::table('plans')->whereIn('code', ['free', 'pro', 'team'])->pluck('id');
        DB::table('plan_limits')->whereIn('plan_id', $planIds)->where('key', 'max_projects')->delete();
    }
};