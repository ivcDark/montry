<?php

namespace Tests\Unit\CheckTypes;

use App\Modules\CheckTypes\SslCheck\Domain\SslCheckTypeDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SslCheckTypeDefinitionTest extends TestCase
{
    public function test_it_normalizes_settings(): void
    {
        $definition = new SslCheckTypeDefinition();

        $this->assertSame([
            'domain' => 'example.com',
            'port' => 443,
            'warning_days' => [30, 7, 1],
        ], $definition->normalizeSettings([
            'domain' => 'EXAMPLE.COM',
            'warning_days' => [1, 30, 7, 7],
        ]));
    }

    public function test_it_resolves_ssl_status(): void
    {
        $definition = new SslCheckTypeDefinition();

        $this->assertSame('success', $definition->resolveStatus([
            'valid' => true,
            'days_until_expiration' => 10,
        ], ['valid' => true]));

        $this->assertSame('failure', $definition->resolveStatus([
            'valid' => false,
            'days_until_expiration' => 10,
        ], ['valid' => true]));
    }

    public function test_it_requires_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SslCheckTypeDefinition())->validateSettings([]);
    }
}
