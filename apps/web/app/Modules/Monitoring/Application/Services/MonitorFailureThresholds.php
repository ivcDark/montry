<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final class MonitorFailureThresholds
{
    private const AUTO_INTERVAL_SECONDS = 21600;

    /**
     * @var array<string, int>
     */
    private const DEFAULTS_BY_TYPE = [
        'http' => 2,
        'sitemap_xml' => 1,
        'robots_txt' => 1,
        'api_endpoint' => 2,
        'dns' => 2,
    ];

    public function forMonitor(Monitor $monitor): int
    {
        $configuredThreshold = (int) ($monitor->failure_threshold ?? 0);

        if ($configuredThreshold > 0) {
            return $configuredThreshold;
        }

        return $this->autoThreshold($monitor);
    }

    public static function defaultForType(string $type): int
    {
        return self::DEFAULTS_BY_TYPE[$type] ?? 0;
    }

    private function autoThreshold(Monitor $monitor): int
    {
        $intervalSeconds = (int) ($monitor->interval_seconds ?? 0);

        if ($intervalSeconds > self::AUTO_INTERVAL_SECONDS) {
            return 1;
        }

        return 2;
    }
}
