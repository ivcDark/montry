<?php

namespace App\Modules\MonitoredResources\Presentation\Http\Requests;

use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreMonitoredResourceRequest extends FormRequest
{
    private const BASE_MONITOR_TYPES = ['http', 'ssl', 'domain', 'dns', 'robots_txt'];
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = array_keys(app(CheckTypeRegistry::class)->all());

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'monitors' => ['sometimes', 'array'],
            'monitors.*.type' => ['required_with:monitors', 'string', Rule::in($types)],
            'monitors.*.name' => ['required_with:monitors', 'string', 'max:255'],
            'monitors.*.is_enabled' => ['required_with:monitors', 'boolean'],
            'monitors.*.interval_seconds' => ['required_with:monitors', 'integer', 'min:300', 'max:86400', 'multiple_of:60'],
            'monitors.*.timeout_ms' => ['required_with:monitors', 'integer', 'min:1000', 'max:60000'],
            'monitors.*.settings' => ['required_with:monitors', 'array'],
            'monitors.*.expected' => ['sometimes', 'array'],
        ];
    }

    public function toData(int $organizationId, Project $project): CreateSiteData
    {
        $parsedUrl = $this->normalizeUrl(
            $this->string('url')->trim()->toString(),
        );

        return new CreateSiteData(
            organizationId: (string) $organizationId,
            folderId: (string) $project->id,
            createdUserId: (string) $this->user()->id,
            name: $this->siteName($parsedUrl['host']),
            url: $parsedUrl['url'],
            scheme: $parsedUrl['scheme'],
            host: $parsedUrl['host'],
            path: $parsedUrl['path'],
            status: SiteStatus::Unknown->value,
            notes: $this->string('note')->trim()->toString(),
            port: $parsedUrl['port'],
        );
    }

    /**
     * @param  list<string>|null  $allowedTypes Deprecated: paid checks must be validated by LimitChecker, not silently filtered here.
     */
    public function monitorPayloads(array $site, ?array $allowedTypes = null, ?int $minimumIntervalSeconds = null): array
    {
        $submitted = collect($this->validated('monitors', []))
            ->keyBy('type');

        $types = collect(self::BASE_MONITOR_TYPES)
            ->merge($submitted->keys())
            ->unique()
            ->values();

        return $types
            ->map(fn (string $type): array => $this->mergeMonitorPayload(
                $this->defaultMonitorPayload($type, $site, $minimumIntervalSeconds),
                $submitted->get($type, []),
            ))
            ->values()
            ->all();
    }

    private function mergeMonitorPayload(array $defaults, array $submitted): array
    {
        return array_replace($defaults, $submitted, [
            'settings' => array_replace($defaults['settings'], $submitted['settings'] ?? []),
            'expected' => array_replace($defaults['expected'], $submitted['expected'] ?? []),
        ]);
    }

    /**
     * @param  array{url: string, host: string, port: int|null}  $site
     */
    private function defaultMonitorPayload(string $type, array $site, ?int $minimumIntervalSeconds): array
    {
        $httpInterval = max(300, $minimumIntervalSeconds ?? 300);

        if ($type === 'http') {
            return [
                'type' => 'http',
                'name' => 'HTTP availability',
                'is_enabled' => true,
                'interval_seconds' => $httpInterval,
                'timeout_ms' => 10000,
                'settings' => [
                    'method' => 'GET',
                    'url' => $site['url'],
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'expected' => [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
            ];
        }

        if ($type === 'ssl') {
            return [
                'type' => 'ssl',
                'name' => 'SSL certificate',
                'is_enabled' => false,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'domain' => $site['host'],
                    'port' => $site['port'] ?? 443,
                    'warning_days' => [30, 14, 7, 3, 1],
                ],
                'expected' => [
                    'valid' => true,
                ],
            ];
        }

        if ($type === 'domain') {
            return [
                'type' => 'domain',
                'name' => 'Domain expiration',
                'is_enabled' => false,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'domain' => $site['host'],
                    'warning_days' => [30, 14, 7, 3, 1],
                ],
                'expected' => [
                    'registered' => true,
                ],
            ];
        }

        if ($type === 'dns') {
            return [
                'type' => 'dns',
                'name' => 'DNS records',
                'is_enabled' => false,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'domain' => $site['host'],
                    'record_types' => ['A', 'AAAA'],
                    'nameservers' => [],
                ],
                'expected' => [
                    'resolves' => true,
                    'min_records' => 1,
                ],
            ];
        }

        if ($type === 'robots_txt') {
            return [
                'type' => 'robots_txt',
                'name' => 'Robots.txt',
                'is_enabled' => false,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'url' => $this->siteRootUrl($site).'/robots.txt',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'expected' => [
                    'exists' => true,
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
            ];
        }

        if ($type === 'sitemap_xml') {
            return [
                'type' => 'sitemap_xml',
                'name' => 'Sitemap.xml',
                'is_enabled' => false,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'url' => $this->siteRootUrl($site).'/sitemap.xml',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'expected' => [
                    'exists' => true,
                    'valid_xml' => true,
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
            ];
        }

        if ($type === 'api_endpoint') {
            return [
                'type' => 'api_endpoint',
                'name' => 'API endpoint',
                'is_enabled' => false,
                'interval_seconds' => $httpInterval,
                'timeout_ms' => 10000,
                'settings' => [
                    'method' => 'GET',
                    'url' => $site['url'],
                    'headers' => [],
                    'body' => null,
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'expected' => [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                    'response_contains' => null,
                ],
            ];
        }

        if ($type === 'tcp_port') {
            return [
                'type' => 'tcp_port',
                'name' => 'TCP port',
                'is_enabled' => false,
                'interval_seconds' => $httpInterval,
                'timeout_ms' => 10000,
                'settings' => [
                    'host' => $site['host'],
                    'port' => $site['port'] ?? 443,
                ],
                'expected' => [
                    'open' => true,
                    'max_response_time_ms' => 5000,
                ],
            ];
        }

        throw ValidationException::withMessages([
            'monitors' => "Unsupported monitor type [{$type}].",
        ]);
    }


    /**
     * @param  array{url: string, host: string, port: int|null}  $site
     */
    private function siteRootUrl(array $site): string
    {
        $parts = parse_url($site['url']);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $site['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    private function siteName(string $host): string
    {
        $name = $this->string('name')->trim()->toString();

        return $name !== '' ? $name : $host;
    }

    /**
     * @return array{url: string, scheme: string, host: string, port: int|null, path: string}
     */
    private function normalizeUrl(string $url): array
    {
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw ValidationException::withMessages([
                'url' => 'Please enter a valid URL.',
            ]);
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'url' => 'Only HTTP and HTTPS URLs are supported.',
            ]);
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        $normalizedUrl = $scheme.'://'.$host;

        if ($port !== null) {
            $normalizedUrl .= ':'.$port;
        }

        return [
            'url' => $normalizedUrl.$path,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => $path,
        ];
    }
}
