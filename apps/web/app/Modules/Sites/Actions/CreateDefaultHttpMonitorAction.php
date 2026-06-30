<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Handlers\CreateMonitorHandler;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class CreateDefaultHttpMonitorAction
{
    public function __construct(
        private CreateMonitorHandler $createMonitor,
    ) {}

    public function execute(MonitoredResource $site): Monitor
    {
        return $this->createMonitor->handle(new CreateMonitorCommand(
            organizationId: $site->organization_id,
            projectId: $site->project_id,
            monitoredResourceId: $site->id,
            type: 'http',
            name: 'HTTP check',
            enabled: true,
            intervalSeconds: 60,
            timeoutMs: 10000,
            failureThreshold: 2,
            settings: [
                'method' => 'GET',
                'url' => $site->target,
                'follow_redirects' => true,
                'verify_ssl' => true,
            ],
            expected: [
                'status_codes' => [200],
                'max_response_time_ms' => 5000,
            ],
        ));
    }
}
