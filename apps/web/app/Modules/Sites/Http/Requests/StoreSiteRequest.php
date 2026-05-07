<?php

namespace App\Modules\Sites\Http\Requests;

use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function toData(string $organizationId, Folder $folder): CreateSiteData
    {
        $parsedUrl = $this->normalizeUrl(
            $this->string('url')->trim()->toString()
        );

        return new CreateSiteData(
            organizationId: $organizationId,
            folderId: $folder->id,
            createdUserId: auth()->id(),
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

    private function siteName(string $host): string
    {
        $name = $this->string('name')->trim()->toString();

        if ($name !== '') {
            return $name;
        }

        return $host;
    }

    /**
     * @return array{
     *     url: string,
     *     scheme: string,
     *     host: string,
     *     port: int|null,
     *     path: string
     * }
     */
    private function normalizeUrl(string $url): array
    {
        if (! str_contains($url, '://')) {
            $url = 'https://' . $url;
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
            $path .= '?' . $parts['query'];
        }

        $normalizedUrl = $scheme . '://' . $host;

        if ($port !== null) {
            $normalizedUrl .= ':' . $port;
        }

        $normalizedUrl .= $path;

        return [
            'url' => $normalizedUrl,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => $path,
        ];
    }
}
