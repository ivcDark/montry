<?php

namespace App\Modules\CheckTypes\TcpPortCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class TcpPortCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    public function type(): string
    {
        return 'tcp_port';
    }

    public function label(): string
    {
        return 'TCP Port';
    }

    public function defaultSettings(): array
    {
        return [
            'host' => null,
            'port' => 443,
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'open' => true,
            'max_response_time_ms' => 5000,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $host = trim((string) $settings['host']);

        if ($host === '') {
            throw new InvalidArgumentException('TCP host is required.');
        }

        $port = (int) $settings['port'];

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('TCP port must be between 1 and 65535.');
        }

        return [
            'host' => strtolower($host),
            'port' => $port,
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);
        $maxResponseTimeMs = (int) $expected['max_response_time_ms'];

        if ($maxResponseTimeMs < 1) {
            throw new InvalidArgumentException('TCP max_response_time_ms must be greater than zero.');
        }

        return [
            'open' => (bool) $expected['open'],
            'max_response_time_ms' => $maxResponseTimeMs,
        ];
    }

    public function normalizeSettings(array $settings): array
    {
        return $this->validateSettings($settings);
    }

    public function normalizeWorkerResult(array $result): array
    {
        return [
            'open' => (bool) ($result['open'] ?? false),
            'host' => $result['host'] ?? null,
            'port' => isset($result['port']) ? (int) $result['port'] : null,
            'response_time_ms' => isset($result['response_time_ms'])
                ? (int) $result['response_time_ms']
                : (isset($result['duration_ms']) ? (int) $result['duration_ms'] : null),
            'error_code' => $result['error']['code'] ?? $result['error_code'] ?? null,
            'error_message' => $result['error']['message'] ?? $result['error_message'] ?? null,
        ];
    }

    public function resolveStatus(array $normalizedResult, array $expected): string
    {
        $expected = $this->validateExpected($expected);

        if (($normalizedResult['error_code'] ?? null) !== null) {
            return 'failure';
        }

        if ($expected['open'] !== (bool) ($normalizedResult['open'] ?? false)) {
            return 'failure';
        }

        $responseTimeMs = $normalizedResult['response_time_ms'] ?? null;

        if ($responseTimeMs !== null && $responseTimeMs > $expected['max_response_time_ms']) {
            return 'failure';
        }

        return 'success';
    }
}
