<?php

namespace Tests\Unit\Monitoring;

use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CheckTypeRegistryTest extends TestCase
{
    public function test_it_registers_and_returns_check_type_definitions(): void
    {
        $registry = new CheckTypeRegistry();
        $definition = new class implements CheckTypeDefinitionInterface {
            public function type(): string
            {
                return 'fake';
            }

            public function label(): string
            {
                return 'Fake';
            }

            public function defaultSettings(): array
            {
                return [];
            }

            public function defaultExpected(): array
            {
                return [];
            }

            public function validateSettings(array $settings): array
            {
                return $settings;
            }

            public function validateExpected(array $expected): array
            {
                return $expected;
            }

            public function normalizeSettings(array $settings): array
            {
                return $settings;
            }

            public function normalizeWorkerResult(array $result): array
            {
                return $result;
            }

            public function resolveStatus(array $normalizedResult, array $expected): string
            {
                return 'success';
            }
        };

        $registry->register($definition);

        $this->assertSame($definition, $registry->get('fake'));
        $this->assertSame(['fake' => $definition], $registry->all());
    }

    public function test_it_rejects_unknown_check_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown check type: missing');

        (new CheckTypeRegistry())->get('missing');
    }
}
