<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;

final class BillingAddonCatalog
{
    public const EXTRA_SITES_PACK = 'extra_sites_pack';

    public function __construct(
        private readonly MonitorTypeCatalog $monitorTypes,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $addons = [
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
                'sort_order' => 0,
            ],
        ];

        foreach ($this->monitorTypes->paidAddonPayload() as $addon) {
            $addons[$addon['code']] = $addon;
        }

        uasort($addons, fn (array $left, array $right): int => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));

        return $addons;
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
        return $this->monitorTypes->isPaid($type);
    }

    public function isBaseMonitorType(string $type): bool
    {
        return $this->monitorTypes->isBase($type);
    }

    /**
     * @return list<string>
     */
    public function paidMonitorTypes(): array
    {
        return $this->monitorTypes->paidCodes();
    }

    /**
     * @return list<string>
     */
    public function baseMonitorTypes(): array
    {
        return array_values(array_filter(
            $this->monitorTypes->allCodes(),
            fn (string $code): bool => $this->monitorTypes->isBase($code),
        ));
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
