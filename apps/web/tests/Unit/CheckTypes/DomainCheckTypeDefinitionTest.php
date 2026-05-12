<?php

namespace Tests\Unit\CheckTypes;

use App\Modules\CheckTypes\DomainCheck\Domain\DomainCheckTypeDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DomainCheckTypeDefinitionTest extends TestCase
{
    public function test_it_normalizes_settings(): void
    {
        $definition = new DomainCheckTypeDefinition();

        $this->assertSame([
            'domain' => 'example.com',
            'warning_days' => [30, 14, 1],
        ], $definition->normalizeSettings([
            'domain' => 'EXAMPLE.COM',
            'warning_days' => [1, 14, 30],
        ]));
    }

    public function test_it_resolves_domain_status(): void
    {
        $definition = new DomainCheckTypeDefinition();

        $this->assertSame('success', $definition->resolveStatus([
            'registered' => true,
            'days_until_expiration' => 30,
        ], ['registered' => true]));

        $this->assertSame('failure', $definition->resolveStatus([
            'registered' => false,
            'days_until_expiration' => null,
        ], ['registered' => true]));
    }

    public function test_it_requires_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DomainCheckTypeDefinition())->validateSettings([]);
    }
}
