<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->unsignedSmallInteger('failure_threshold')->default(0)->after('interval_seconds');
        });

        foreach ([
            'http' => 2,
            'sitemap_xml' => 1,
            'robots_txt' => 1,
            'api_endpoint' => 2,
            'dns' => 2,
        ] as $type => $threshold) {
            DB::table('monitors')
                ->where('type', $type)
                ->update(['failure_threshold' => $threshold]);
        }
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn('failure_threshold');
        });
    }
};
