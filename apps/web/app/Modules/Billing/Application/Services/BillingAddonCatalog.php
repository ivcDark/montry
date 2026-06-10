<?php

namespace App\Modules\Billing\Application\Services;

final class BillingAddonCatalog
{
    public const EXTRA_SITES_PACK = 'extra_sites_pack';
    public const SITEMAP_XML = 'sitemap_xml';
    public const API_ENDPOINT = 'api_endpoint';
    public const TCP_PORT = 'tcp_port';

    public const BASE_MONITOR_TYPES = [
        'http',
        'ssl',
        'domain',
        'dns',
        'robots_txt',
    ];

    public const PAID_MONITOR_TYPES = [
        self::SITEMAP_XML,
        self::API_ENDPOINT,
        self::TCP_PORT,
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            self::EXTRA_SITES_PACK => [
                'code' => self::EXTRA_SITES_PACK,
                'name' => 'Дополнительные сайты',
                'description' => '+5 сайтов к лимиту тарифа.',
                'unit' => 'пакет',
                'unit_label' => '+5 сайтов',
                'unit_price_cents' => 15000,
                'currency' => 'RUB',
                'kind' => 'site_pack',
                'sites_per_unit' => 5,
                'is_recurring' => true,
            ],
            self::SITEMAP_XML => [
                'code' => self::SITEMAP_XML,
                'name' => 'Sitemap.xml',
                'description' => 'Проверка наличия и валидности XML-карты сайта.',
                'unit' => 'проверка',
                'unit_label' => '1 проверка',
                'unit_price_cents' => 2000,
                'currency' => 'RUB',
                'kind' => 'paid_check',
                'monitor_type' => self::SITEMAP_XML,
                'is_recurring' => true,
            ],
            self::API_ENDPOINT => [
                'code' => self::API_ENDPOINT,
                'name' => 'API endpoint',
                'description' => 'Проверка healthcheck, webhook или любого API URL.',
                'unit' => 'endpoint',
                'unit_label' => '1 endpoint',
                'unit_price_cents' => 3000,
                'currency' => 'RUB',
                'kind' => 'paid_check',
                'monitor_type' => self::API_ENDPOINT,
                'is_recurring' => true,
            ],
            self::TCP_PORT => [
                'code' => self::TCP_PORT,
                'name' => 'TCP-порт',
                'description' => 'Проверка открытого TCP-порта.',
                'unit' => 'порт',
                'unit_label' => '1 порт',
                'unit_price_cents' => 2000,
                'currency' => 'RUB',
                'kind' => 'paid_check',
                'monitor_type' => self::TCP_PORT,
                'is_recurring' => true,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payload(): array
    {
        return array_values($this->all());
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    public function unitPriceCents(string $code): int
    {
        $addon = $this->all()[$code] ?? null;

        return (int) ($addon['unit_price_cents'] ?? 0);
    }

    public function isPaidMonitorType(string $type): bool
    {
        return in_array($type, self::PAID_MONITOR_TYPES, true);
    }

    public function isBaseMonitorType(string $type): bool
    {
        return in_array($type, self::BASE_MONITOR_TYPES, true);
    }

    /**
     * @param array<string, mixed>|null $input
     * @return array<string, int>
     */
    public function normalizeQuantities(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $quantities = [];

        foreach ($this->all() as $code => $addon) {
            $rawQuantity = $input[$code] ?? 0;
            $quantity = is_numeric($rawQuantity) ? (int) $rawQuantity : 0;

            if ($quantity > 0) {
                $quantities[$code] = min($quantity, 1000);
            }
        }

        return $quantities;
    }

    /**
     * @param array<string, int> $quantities
     */
    public function totalCents(array $quantities): int
    {
        $total = 0;

        foreach ($quantities as $code => $quantity) {
            if (! $this->has($code)) {
                continue;
            }

            $total += $this->unitPriceCents($code) * max(0, $quantity);
        }

        return $total;
    }
}
