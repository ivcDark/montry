<?php

namespace Tests\Unit\CheckTypes;

use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Tests\TestCase;

final class CheckTypesServiceProviderTest extends TestCase
{
    public function test_it_registers_mvp_check_types_in_registry(): void
    {
        $registry = $this->app->make(CheckTypeRegistry::class);

        $this->assertSame(['http', 'ssl', 'domain'], array_keys($registry->all()));
        $this->assertSame('HTTP', $registry->get('http')->label());
        $this->assertSame('SSL Certificate', $registry->get('ssl')->label());
        $this->assertSame('Domain Expiration', $registry->get('domain')->label());
    }
}
