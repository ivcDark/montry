<?php

namespace App\Modules\Monitoring\Domain\Contracts;

interface CheckTypeDefinitionInterface
{
    public function type(): string;

    public function label(): string;

    public function defaultSettings(): array;

    public function defaultExpected(): array;

    public function validateSettings(array $settings): array;

    public function validateExpected(array $expected): array;

    public function normalizeSettings(array $settings): array;

    public function normalizeWorkerResult(array $result): array;

    public function resolveStatus(array $normalizedResult, array $expected): string;
}
