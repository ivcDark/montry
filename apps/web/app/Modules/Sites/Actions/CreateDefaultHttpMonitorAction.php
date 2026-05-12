<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\DTO\CreateMonitorData;
use App\Modules\Sites\Enums\MonitorType;

final readonly class CreateDefaultHttpMonitorAction
{
    public function __construct(
        private CreateMonitorAction $createMonitor,
    ) {
    }

    public function execute(MonitoredResource $site): Monitor
    {
        return $this->createMonitor->handle(
            site: $site,
            data: new CreateMonitorData(
                name: 'HTTP check',
                type: MonitorType::Http,
                isEnabled: true,
                intervalSeconds: 60,
                timeoutMs: 10000,
                settings: [
                    'method' => 'GET',
                    'path' => $site->path,
                    'expected_status_min' => 200,
                    'expected_status_max' => 399,
                    'follow_redirects' => true,
                ],
                expected: [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
            ),
        );
    }
}
