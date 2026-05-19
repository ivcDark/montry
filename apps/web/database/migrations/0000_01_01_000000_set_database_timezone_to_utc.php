<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SET TIME ZONE 'UTC'");
        DB::statement(sprintf(
            "ALTER DATABASE %s SET timezone TO 'UTC'",
            $this->quoteIdentifier(DB::getDatabaseName()),
        ));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER DATABASE %s RESET timezone',
            $this->quoteIdentifier(DB::getDatabaseName()),
        ));
        DB::statement('SET TIME ZONE DEFAULT');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
