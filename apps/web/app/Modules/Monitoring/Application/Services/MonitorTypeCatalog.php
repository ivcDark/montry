<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\MonitorType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class MonitorTypeCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function payload(): array
    {
        return $this->rows()
            ->map(fn (array $row): array => $this->payloadRow($row))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allCodes(): array
    {
        return $this->rows()
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function defaultForSiteCodes(): array
    {
        return $this->rows()
            ->filter(fn (array $row): bool => (bool) ($row['is_default_for_site'] ?? false))
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function paidCodes(): array
    {
        return $this->rows()
            ->filter(fn (array $row): bool => (bool) ($row['is_paid'] ?? false))
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
    }

    public function isPaid(string $code): bool
    {
        $row = $this->find($code);

        return $row !== null && (bool) ($row['is_paid'] ?? false);
    }

    public function isBase(string $code): bool
    {
        $row = $this->find($code);

        return $row !== null && ! (bool) ($row['is_paid'] ?? false);
    }

    public function priceCents(string $code): int
    {
        $row = $this->find($code);

        return (int) ($row['unit_price_cents'] ?? 0);
    }

    public function sortOrder(string $code): int
    {
        $row = $this->find($code);

        return (int) ($row['sort_order'] ?? 9999);
    }

    public function label(string $code): string
    {
        $row = $this->find($code);

        return (string) ($row['short_label'] ?? $row['name'] ?? $code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paidAddonPayload(): array
    {
        return $this->rows()
            ->filter(fn (array $row): bool => (bool) ($row['is_paid'] ?? false))
            ->map(fn (array $row): array => [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'unit' => 'проверка',
                'unit_label' => (string) ($row['unit_label'] ?? '1 проверка'),
                'unit_price_cents' => (int) ($row['unit_price_cents'] ?? 0),
                'currency' => (string) ($row['currency'] ?? 'RUB'),
                'kind' => 'paid_check',
                'monitor_type' => (string) $row['code'],
                'is_recurring' => true,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{url: string, host: string, port: int|null}  $site
     * @return array<string, mixed>
     */
    public function defaultMonitorPayload(string $code, array $site, ?int $minimumIntervalSeconds = null): array
    {
        $row = $this->find($code);

        if ($row === null) {
            throw new \InvalidArgumentException("Unknown monitor type [{$code}].");
        }

        $defaultInterval = (int) ($row['default_interval_seconds'] ?? 300);
        $minimumInterval = $minimumIntervalSeconds ?? 300;

        return [
            'type' => (string) $row['code'],
            'name' => (string) $row['name'],
            'is_enabled' => (bool) ($row['default_enabled'] ?? false),
            'interval_seconds' => max($defaultInterval, $minimumInterval),
            'timeout_ms' => (int) ($row['default_timeout_ms'] ?? 10000),
            'settings' => $this->resolveTemplate((array) ($row['default_settings'] ?? []), $site),
            'expected' => $this->resolveTemplate((array) ($row['default_expected'] ?? []), $site),
        ];
    }

    private function find(string $code): ?array
    {
        return $this->rows()
            ->first(fn (array $row): bool => (string) $row['code'] === $code);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        try {
            if (Schema::hasTable('monitor_types')) {
                $rows = MonitorType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (MonitorType $type): array => $type->toArray());

                if ($rows->isNotEmpty()) {
                    return $rows->values();
                }
            }
        } catch (Throwable) {
            // Fallback keeps tests and first install stable before the seeder has populated the table.
        }

        return collect($this->fallbackRows())
            ->sortBy(fn (array $row): int => (int) ($row['sort_order'] ?? 0))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function payloadRow(array $row): array
    {
        return [
            'value' => (string) $row['code'],
            'code' => (string) $row['code'],
            'label' => (string) ($row['short_label'] ?? $row['name']),
            'name' => (string) $row['name'],
            'short_label' => (string) ($row['short_label'] ?? $row['name']),
            'description' => (string) ($row['description'] ?? ''),
            'category' => $row['category'] ?? null,
            'is_default_for_site' => (bool) ($row['is_default_for_site'] ?? false),
            'default_enabled' => (bool) ($row['default_enabled'] ?? false),
            'is_paid' => (bool) ($row['is_paid'] ?? false),
            'unit_price_cents' => (int) ($row['unit_price_cents'] ?? 0),
            'currency' => (string) ($row['currency'] ?? 'RUB'),
            'unit_label' => $row['unit_label'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'default_interval_seconds' => (int) ($row['default_interval_seconds'] ?? 300),
            'default_timeout_ms' => (int) ($row['default_timeout_ms'] ?? 10000),
            'default_settings' => $row['default_settings'] ?? [],
            'default_expected' => $row['default_expected'] ?? [],
            'ui_meta' => $row['ui_meta'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function resolveTemplate(mixed $value, array $site): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->resolveTemplate($item, $site))
                ->all();
        }

        if (! is_string($value)) {
            return $value;
        }

        $rootUrl = $this->siteRootUrl($site);
        $replacements = [
            '{{site.url}}' => (string) ($site['url'] ?? ''),
            '{{site.host}}' => (string) ($site['host'] ?? ''),
            '{{site.root_url}}' => $rootUrl,
            '{{site.port}}' => $site['port'],
            '{{site.port_or_443}}' => $site['port'] ?? 443,
        ];

        if (array_key_exists($value, $replacements)) {
            return $replacements[$value];
        }

        $stringReplacements = [];

        foreach ($replacements as $placeholder => $replacement) {
            $stringReplacements[$placeholder] = (string) $replacement;
        }

        return strtr($value, $stringReplacements);
    }

    /**
     * @param  array{url?: string, host?: string, port?: int|null}  $site
     */
    private function siteRootUrl(array $site): string
    {
        $parts = parse_url((string) ($site['url'] ?? ''));
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? (string) ($site['host'] ?? '');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackRows(): array
    {
        return [
            [
                'code' => 'http', 'name' => 'HTTP availability', 'short_label' => 'HTTP',
                'description' => 'Код ответа, редиректы и время ответа главной страницы.', 'category' => 'availability',
                'is_default_for_site' => true, 'default_enabled' => true, 'is_paid' => false,
                'unit_price_cents' => 0, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 10,
                'default_interval_seconds' => 300, 'default_timeout_ms' => 10000,
                'default_settings' => ['method' => 'GET', 'url' => '{{site.url}}', 'follow_redirects' => true, 'verify_ssl' => true],
                'default_expected' => ['status_codes' => [200], 'max_response_time_ms' => 5000], 'ui_meta' => ['title' => 'Доступность сайта'],
            ],
            [
                'code' => 'ssl', 'name' => 'SSL certificate', 'short_label' => 'SSL',
                'description' => 'Валидность сертификата и предупреждения до истечения.', 'category' => 'security',
                'is_default_for_site' => true, 'default_enabled' => false, 'is_paid' => false,
                'unit_price_cents' => 0, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 20,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['domain' => '{{site.host}}', 'port' => '{{site.port_or_443}}', 'warning_days' => [30, 14, 7, 3, 1]],
                'default_expected' => ['valid' => true], 'ui_meta' => ['title' => 'SSL-сертификат'],
            ],
            [
                'code' => 'domain', 'name' => 'Domain expiration', 'short_label' => 'Domain',
                'description' => 'WHOIS-проверка регистрации и даты окончания домена.', 'category' => 'infrastructure',
                'is_default_for_site' => true, 'default_enabled' => false, 'is_paid' => false,
                'unit_price_cents' => 0, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 30,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['domain' => '{{site.host}}', 'warning_days' => [30, 14, 7, 3, 1]],
                'default_expected' => ['registered' => true], 'ui_meta' => ['title' => 'Срок домена'],
            ],
            [
                'code' => 'dns', 'name' => 'DNS records', 'short_label' => 'DNS',
                'description' => 'Проверка резолва домена и базовых DNS-записей.', 'category' => 'infrastructure',
                'is_default_for_site' => true, 'default_enabled' => false, 'is_paid' => false,
                'unit_price_cents' => 0, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 40,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['domain' => '{{site.host}}', 'record_types' => ['A', 'AAAA'], 'nameservers' => [], 'warn_on_change' => false],
                'default_expected' => ['resolves' => true, 'min_records' => 1], 'ui_meta' => ['title' => 'DNS-записи'],
            ],
            [
                'code' => 'robots_txt', 'name' => 'Robots.txt', 'short_label' => 'Robots.txt',
                'description' => 'Наличие robots.txt и корректный HTTP-ответ.', 'category' => 'seo',
                'is_default_for_site' => true, 'default_enabled' => false, 'is_paid' => false,
                'unit_price_cents' => 0, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 50,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['url' => '{{site.root_url}}/robots.txt', 'follow_redirects' => true, 'verify_ssl' => true],
                'default_expected' => ['exists' => true, 'status_codes' => [200], 'max_response_time_ms' => 5000], 'ui_meta' => ['title' => 'Robots.txt'],
            ],
            [
                'code' => 'sitemap_xml', 'name' => 'Sitemap.xml', 'short_label' => 'Sitemap.xml',
                'description' => 'Проверка наличия и валидности XML-карты сайта.', 'category' => 'seo',
                'is_default_for_site' => false, 'default_enabled' => false, 'is_paid' => true,
                'unit_price_cents' => 2000, 'currency' => 'RUB', 'unit_label' => '1 проверка', 'sort_order' => 60,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['url' => '{{site.root_url}}/sitemap.xml', 'follow_redirects' => true, 'verify_ssl' => true],
                'default_expected' => ['exists' => true, 'valid_xml' => true, 'status_codes' => [200], 'max_response_time_ms' => 5000], 'ui_meta' => ['title' => 'Sitemap.xml'],
            ],
            [
                'code' => 'api_endpoint', 'name' => 'API endpoint', 'short_label' => 'API',
                'description' => 'Проверка healthcheck, webhook или любого API URL.', 'category' => 'api',
                'is_default_for_site' => false, 'default_enabled' => false, 'is_paid' => true,
                'unit_price_cents' => 3000, 'currency' => 'RUB', 'unit_label' => '1 endpoint', 'sort_order' => 70,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['method' => 'GET', 'url' => '{{site.url}}', 'headers' => [], 'body' => null, 'follow_redirects' => true, 'verify_ssl' => true],
                'default_expected' => ['status_codes' => [200], 'max_response_time_ms' => 5000, 'response_contains' => null], 'ui_meta' => ['title' => 'API endpoint'],
            ],
            [
                'code' => 'tcp_port', 'name' => 'TCP port', 'short_label' => 'TCP',
                'description' => 'Проверка открытого TCP-порта.', 'category' => 'network',
                'is_default_for_site' => false, 'default_enabled' => false, 'is_paid' => true,
                'unit_price_cents' => 2000, 'currency' => 'RUB', 'unit_label' => '1 порт', 'sort_order' => 80,
                'default_interval_seconds' => 86400, 'default_timeout_ms' => 10000,
                'default_settings' => ['host' => '{{site.host}}', 'port' => '{{site.port_or_443}}'],
                'default_expected' => ['open' => true, 'max_response_time_ms' => 5000], 'ui_meta' => ['title' => 'TCP-порт'],
            ],
        ];
    }
}
