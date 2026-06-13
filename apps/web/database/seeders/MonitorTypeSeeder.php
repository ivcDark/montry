<?php

namespace Database\Seeders;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\MonitorType;
use Illuminate\Database\Seeder;

final class MonitorTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $type) {
            MonitorType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function types(): array
    {
        return [
            [
                'code' => 'http',
                'name' => 'HTTP availability',
                'short_label' => 'HTTP',
                'description' => 'Код ответа, редиректы и время ответа главной страницы.',
                'category' => 'availability',
                'is_active' => true,
                'is_default_for_site' => true,
                'default_enabled' => true,
                'is_paid' => false,
                'unit_price_cents' => 0,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 10,
                'default_interval_seconds' => 300,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'method' => 'GET',
                    'url' => '{{site.url}}',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'default_expected' => [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
                'ui_meta' => [
                    'title' => 'Доступность сайта',
                    'summary_template' => 'HTTP-проверка главной страницы',
                ],
            ],
            [
                'code' => 'ssl',
                'name' => 'SSL certificate',
                'short_label' => 'SSL',
                'description' => 'Валидность сертификата и предупреждения до истечения.',
                'category' => 'security',
                'is_active' => true,
                'is_default_for_site' => true,
                'default_enabled' => false,
                'is_paid' => false,
                'unit_price_cents' => 0,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 20,
                'default_interval_seconds' => 86400,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'domain' => '{{site.host}}',
                    'port' => '{{site.port_or_443}}',
                    'warning_days' => [30, 14, 7, 3, 1],
                ],
                'default_expected' => [
                    'valid' => true,
                ],
                'ui_meta' => [
                    'title' => 'SSL-сертификат',
                ],
            ],
            [
                'code' => 'domain',
                'name' => 'Domain expiration',
                'short_label' => 'Domain',
                'description' => 'WHOIS-проверка регистрации и даты окончания домена.',
                'category' => 'infrastructure',
                'is_active' => true,
                'is_default_for_site' => true,
                'default_enabled' => false,
                'is_paid' => false,
                'unit_price_cents' => 0,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 30,
                'default_interval_seconds' => 86400,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'domain' => '{{site.host}}',
                    'warning_days' => [30, 14, 7, 3, 1],
                ],
                'default_expected' => [
                    'registered' => true,
                ],
                'ui_meta' => [
                    'title' => 'Срок домена',
                ],
            ],
            [
                'code' => 'dns',
                'name' => 'DNS records',
                'short_label' => 'DNS',
                'description' => 'Проверка резолва домена и базовых DNS-записей.',
                'category' => 'infrastructure',
                'is_active' => true,
                'is_default_for_site' => true,
                'default_enabled' => false,
                'is_paid' => false,
                'unit_price_cents' => 0,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 40,
                'default_interval_seconds' => 86400,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'domain' => '{{site.host}}',
                    'record_types' => ['A', 'AAAA'],
                    'nameservers' => [],
                ],
                'default_expected' => [
                    'resolves' => true,
                    'min_records' => 1,
                ],
                'ui_meta' => [
                    'title' => 'DNS-записи',
                ],
            ],
            [
                'code' => 'robots_txt',
                'name' => 'Robots.txt',
                'short_label' => 'Robots.txt',
                'description' => 'Наличие robots.txt и корректный HTTP-ответ.',
                'category' => 'seo',
                'is_active' => true,
                'is_default_for_site' => true,
                'default_enabled' => false,
                'is_paid' => false,
                'unit_price_cents' => 0,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 50,
                'default_interval_seconds' => 86400,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'url' => '{{site.root_url}}/robots.txt',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'default_expected' => [
                    'exists' => true,
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
                'ui_meta' => [
                    'title' => 'Robots.txt',
                ],
            ],
            [
                'code' => 'sitemap_xml',
                'name' => 'Sitemap.xml',
                'short_label' => 'Sitemap.xml',
                'description' => 'Проверка наличия и валидности XML-карты сайта.',
                'category' => 'seo',
                'is_active' => true,
                'is_default_for_site' => false,
                'default_enabled' => false,
                'is_paid' => true,
                'unit_price_cents' => 2000,
                'currency' => 'RUB',
                'unit_label' => '1 проверка',
                'sort_order' => 60,
                'default_interval_seconds' => 86400,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'url' => '{{site.root_url}}/sitemap.xml',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'default_expected' => [
                    'exists' => true,
                    'valid_xml' => true,
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
                'ui_meta' => [
                    'title' => 'Sitemap.xml',
                ],
            ],
            [
                'code' => 'api_endpoint',
                'name' => 'API endpoint',
                'short_label' => 'API',
                'description' => 'Проверка healthcheck, webhook или любого API URL.',
                'category' => 'api',
                'is_active' => true,
                'is_default_for_site' => false,
                'default_enabled' => false,
                'is_paid' => true,
                'unit_price_cents' => 3000,
                'currency' => 'RUB',
                'unit_label' => '1 endpoint',
                'sort_order' => 70,
                'default_interval_seconds' => 300,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'method' => 'GET',
                    'url' => '{{site.url}}',
                    'headers' => [],
                    'body' => null,
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'default_expected' => [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                    'response_contains' => null,
                ],
                'ui_meta' => [
                    'title' => 'API endpoint',
                ],
            ],
            [
                'code' => 'tcp_port',
                'name' => 'TCP port',
                'short_label' => 'TCP',
                'description' => 'Проверка открытого TCP-порта.',
                'category' => 'network',
                'is_active' => true,
                'is_default_for_site' => false,
                'default_enabled' => false,
                'is_paid' => true,
                'unit_price_cents' => 2000,
                'currency' => 'RUB',
                'unit_label' => '1 порт',
                'sort_order' => 80,
                'default_interval_seconds' => 300,
                'default_timeout_ms' => 10000,
                'default_settings' => [
                    'host' => '{{site.host}}',
                    'port' => '{{site.port_or_443}}',
                ],
                'default_expected' => [
                    'open' => true,
                    'max_response_time_ms' => 5000,
                ],
                'ui_meta' => [
                    'title' => 'TCP-порт',
                ],
            ],
        ];
    }
}
