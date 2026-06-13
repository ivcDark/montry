<?php

namespace App\Modules\MonitoredResources\Presentation\Http\Requests;

use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreMonitoredResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = app(MonitorTypeCatalog::class)->allCodes();

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
     * @param  list<string>|null  $allowedTypes
     */
    public function monitorPayloads(array $site, ?array $allowedTypes = null, ?int $minimumIntervalSeconds = null): array
    {
        $catalog = app(MonitorTypeCatalog::class);
        $submitted = collect($this->validated('monitors', []))
            ->keyBy('type');

        $types = collect($catalog->defaultForSiteCodes())
            ->merge($submitted->keys())
            ->unique()
            ->values();

        return $types
            ->when($allowedTypes !== null, fn ($types) => $types->filter(
                fn (string $type): bool => in_array($type, $allowedTypes, true),
            ))
            ->map(fn (string $type): array => $this->mergeMonitorPayload(
                $catalog->defaultMonitorPayload($type, $site, $minimumIntervalSeconds),
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
