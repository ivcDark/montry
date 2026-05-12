<?php

namespace Tests\Unit\CheckTypes;

use App\Modules\CheckTypes\HttpCheck\Domain\HttpCheckTypeDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HttpCheckTypeDefinitionTest extends TestCase
{
    public function test_it_normalizes_settings_and_expected_values(): void
    {
        $definition = new HttpCheckTypeDefinition();

        $this->assertSame([
            'method' => 'HEAD',
            'url' => 'https://example.com',
            'follow_redirects' => false,
            'verify_ssl' => true,
        ], $definition->normalizeSettings([
            'method' => 'head',
            'url' => 'https://example.com',
            'follow_redirects' => false,
        ]));

        $this->assertSame([
            'status_codes' => [200, 204],
            'max_response_time_ms' => 1000,
        ], $definition->validateExpected([
            'status_codes' => ['200', '204'],
            'max_response_time_ms' => '1000',
        ]));
    }

    public function test_it_resolves_http_status(): void
    {
        $definition = new HttpCheckTypeDefinition();

        $result = $definition->normalizeWorkerResult([
            'status_code' => 200,
            'duration_ms' => 120,
        ]);

        $this->assertSame('success', $definition->resolveStatus($result, [
            'status_codes' => [200],
            'max_response_time_ms' => 500,
        ]));

        $this->assertSame('failure', $definition->resolveStatus($result, [
            'status_codes' => [201],
            'max_response_time_ms' => 500,
        ]));
    }

    public function test_it_rejects_invalid_method(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new HttpCheckTypeDefinition())->validateSettings([
            'method' => 'DELETE',
        ]);
    }
}
