<?php

namespace App\Modules\CheckTypes\ApiEndpointCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class ApiEndpointCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function type(): string
    {
        return 'api_endpoint';
    }

    public function label(): string
    {
        return 'API Endpoint';
    }

    public function defaultSettings(): array
    {
        return [
            'method' => 'GET',
            'url' => null,
            'headers' => [],
            'body' => null,
            'follow_redirects' => true,
            'verify_ssl' => true,
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'status_codes' => [200],
            'max_response_time_ms' => 5000,
            'response_contains' => null,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $method = strtoupper((string) $settings['method']);
        $url = trim((string) $settings['url']);

        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new InvalidArgumentException('API method is not supported.');
        }

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('API url must be a valid URL.');
        }

        return [
            'method' => $method,
            'url' => $url,
            'headers' => $this->normalizeHeaders($settings['headers']),
            'body' => $settings['body'] === null ? null : (string) $settings['body'],
            'follow_redirects' => (bool) $settings['follow_redirects'],
            'verify_ssl' => (bool) $settings['verify_ssl'],
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);
        $maxResponseTimeMs = (int) $expected['max_response_time_ms'];

        if ($maxResponseTimeMs < 1) {
            throw new InvalidArgumentException('API max_response_time_ms must be greater than zero.');
        }

        return [
            'status_codes' => $this->normalizeStatusCodes($expected['status_codes']),
            'max_response_time_ms' => $maxResponseTimeMs,
            'response_contains' => $this->normalizeNullableString($expected['response_contains']),
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
            'headers' => is_array($result['headers'] ?? null) ? $result['headers'] : [],
            'response_contains_matched' => isset($result['response_contains_matched'])
                ? (bool) $result['response_contains_matched']
                : null,
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

        if ($expected['response_contains'] !== null
            && ($normalizedResult['response_contains_matched'] ?? null) === false) {
            return 'failure';
        }

        return 'success';
    }

    private function normalizeHeaders(mixed $headers): array
    {
        if ($headers === null) {
            return [];
        }

        if (! is_array($headers)) {
            throw new InvalidArgumentException('API headers must be an array.');
        }

        $normalized = [];

        foreach ($headers as $key => $value) {
            $header = trim((string) $key);

            if ($header === '') {
                continue;
            }

            $normalized[$header] = trim((string) $value);
        }

        return $normalized;
    }

    private function normalizeStatusCodes(mixed $statusCodes): array
    {
        if (! is_array($statusCodes) || $statusCodes === []) {
            throw new InvalidArgumentException('API expected status_codes must be a non-empty array.');
        }

        $statusCodes = array_values(array_unique(array_map('intval', $statusCodes)));

        foreach ($statusCodes as $statusCode) {
            if ($statusCode < 100 || $statusCode > 599) {
                throw new InvalidArgumentException('API expected status codes must be between 100 and 599.');
            }
        }

        return $statusCodes;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
