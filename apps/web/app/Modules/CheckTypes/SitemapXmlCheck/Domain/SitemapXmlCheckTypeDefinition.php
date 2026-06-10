<?php

namespace App\Modules\CheckTypes\SitemapXmlCheck\Domain;

use App\Modules\Monitoring\Domain\Contracts\CheckTypeDefinitionInterface;
use InvalidArgumentException;

final class SitemapXmlCheckTypeDefinition implements CheckTypeDefinitionInterface
{
    public function type(): string
    {
        return 'sitemap_xml';
    }

    public function label(): string
    {
        return 'Sitemap.xml';
    }

    public function defaultSettings(): array
    {
        return [
            'url' => null,
            'follow_redirects' => true,
            'verify_ssl' => true,
        ];
    }

    public function defaultExpected(): array
    {
        return [
            'exists' => true,
            'valid_xml' => true,
            'status_codes' => [200],
            'max_response_time_ms' => 5000,
        ];
    }

    public function validateSettings(array $settings): array
    {
        $settings = array_replace($this->defaultSettings(), $settings);
        $url = trim((string) $settings['url']);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Sitemap.xml url must be a valid URL.');
        }

        return [
            'url' => $url,
            'follow_redirects' => (bool) $settings['follow_redirects'],
            'verify_ssl' => (bool) $settings['verify_ssl'],
        ];
    }

    public function validateExpected(array $expected): array
    {
        $expected = array_replace($this->defaultExpected(), $expected);

        return [
            'exists' => (bool) $expected['exists'],
            'valid_xml' => (bool) $expected['valid_xml'],
            'status_codes' => $this->normalizeStatusCodes($expected['status_codes']),
            'max_response_time_ms' => $this->normalizeMaxResponseTime($expected['max_response_time_ms']),
        ];
    }

    public function normalizeSettings(array $settings): array
    {
        return $this->validateSettings($settings);
    }

    public function normalizeWorkerResult(array $result): array
    {
        return [
            'exists' => (bool) ($result['exists'] ?? false),
            'valid_xml' => (bool) ($result['valid_xml'] ?? false),
            'status_code' => isset($result['status_code']) ? (int) $result['status_code'] : null,
            'response_time_ms' => isset($result['response_time_ms'])
                ? (int) $result['response_time_ms']
                : (isset($result['duration_ms']) ? (int) $result['duration_ms'] : null),
            'url_count' => isset($result['url_count']) ? (int) $result['url_count'] : null,
            'sitemap_count' => isset($result['sitemap_count']) ? (int) $result['sitemap_count'] : null,
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

        if ($expected['exists'] && ! ($normalizedResult['exists'] ?? false)) {
            return 'failure';
        }

        if ($expected['valid_xml'] && ! ($normalizedResult['valid_xml'] ?? false)) {
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

    private function normalizeStatusCodes(mixed $statusCodes): array
    {
        if (! is_array($statusCodes) || $statusCodes === []) {
            throw new InvalidArgumentException('Sitemap.xml expected status_codes must be a non-empty array.');
        }

        $statusCodes = array_values(array_unique(array_map('intval', $statusCodes)));

        foreach ($statusCodes as $statusCode) {
            if ($statusCode < 100 || $statusCode > 599) {
                throw new InvalidArgumentException('Sitemap.xml expected status codes must be between 100 and 599.');
            }
        }

        return $statusCodes;
    }

    private function normalizeMaxResponseTime(mixed $maxResponseTimeMs): int
    {
        $maxResponseTimeMs = (int) $maxResponseTimeMs;

        if ($maxResponseTimeMs < 1) {
            throw new InvalidArgumentException('Sitemap.xml max_response_time_ms must be greater than zero.');
        }

        return $maxResponseTimeMs;
    }
}
