<?php

namespace Tests\Unit\Database;

use Tests\TestCase;

final class PostgresTimezoneConfigurationTest extends TestCase
{
    public function test_postgres_connection_uses_utc_timezone(): void
    {
        $this->assertSame('UTC', config('database.connections.pgsql.timezone'));
    }
}
