<?php

namespace Tests\Unit\Monitoring;

use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Application\Services\MonitorStatusResolver;
use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use PHPUnit\Framework\TestCase;

final class MonitorStatusResolverTest extends TestCase
{
    public function test_it_resolves_status_through_registered_check_type_definition(): void
    {
        $registry = new CheckTypeRegistry();
        $registry->register(new class implements CheckTypeDefinitionInterface {
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
                return ['status_code' => 200];
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
                return $normalizedResult['status_code'] === $expected['status_code']
                    ? 'success'
                    : 'failure';
            }
        });

        $monitor = new Monitor([
            'type' => 'fake',
            'expected' => ['status_code' => 200],
        ]);

        $resolver = new MonitorStatusResolver($registry);

        $this->assertSame('success', $resolver->resolve($monitor, ['status_code' => 200]));
        $this->assertSame('failure', $resolver->resolve($monitor, ['status_code' => 500]));
    }
}
