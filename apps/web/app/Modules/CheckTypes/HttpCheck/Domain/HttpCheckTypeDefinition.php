<?php

namespace App\Modules\CheckTypes\HttpCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class HttpCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    public function type(): string
    {
        return 'http';
    }

    public function label(): string
    {
        return 'HTTP';
    }

    public function defaultSettings(): array
    {
        return [
            'method' => 'GET',
            'url' => null,
            'follow_redirects' => true,
            'verify_ssl' => true,
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'status_codes' => [200],
            'max_response_time_ms' => 5000,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $method = strtoupper((string) $settings['method']);

        if (! in_array($method, ['GET', 'POST', 'HEAD'], true)) {
            throw new InvalidArgumentException('HTTP method must be GET, POST or HEAD.');
        }

        if (isset($settings['url']) && $settings['url'] !== '' && filter_var($settings['url'], FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('HTTP url must be a valid URL.');
        }

        return [
            'method' => $method,
            'url' => $settings['url'],
            'follow_redirects' => (bool) $settings['follow_redirects'],
            'verify_ssl' => (bool) $settings['verify_ssl'],
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);
        $statusCodes = $expected['status_codes'];

        if (! is_array($statusCodes) || $statusCodes === []) {
            throw new InvalidArgumentException('HTTP expected status_codes must be a non-empty array.');
        }

        $statusCodes = array_values(array_map('intval', $statusCodes));

        foreach ($statusCodes as $statusCode) {
            if ($statusCode < 100 || $statusCode > 599) {
                throw new InvalidArgumentException('HTTP expected status codes must be between 100 and 599.');
            }
        }

        $maxResponseTimeMs = (int) $expected['max_response_time_ms'];

        if ($maxResponseTimeMs < 1) {
            throw new InvalidArgumentException('HTTP max_response_time_ms must be greater than zero.');
        }

        return [
            'status_codes' => $statusCodes,
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
            'status_code' => isset($result['status_code']) ? (int) $result['status_code'] : null,
            'response_time_ms' => isset($result['response_time_ms'])
                ? (int) $result['response_time_ms']
                : (isset($result['duration_ms']) ? (int) $result['duration_ms'] : null),
            'ip' => $result['ip'] ?? null,
            'headers' => is_array($result['headers'] ?? null) ? $result['headers'] : [],
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

        $statusCode = $normalizedResult['status_code'] ?? null;

        if (! in_array($statusCode, $expected['status_codes'], true)) {
            return 'failure';
        }

        $responseTimeMs = $normalizedResult['response_time_ms'] ?? null;

        if ($responseTimeMs !== null && $responseTimeMs > $expected['max_response_time_ms']) {
            return 'failure';
        }

        return 'success';
    }
}
