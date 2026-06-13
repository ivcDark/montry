<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Monitoring\Application\Commands\PauseMonitorCommand;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class PauseMonitorHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
        private BusinessEventRecorder $events,
    ) {
    }

    public function handle(PauseMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $monitor->enabled = false;

        $monitor = $this->monitors->save($monitor);

        $this->events->record(new RecordBusinessEventData(
            eventType: 'monitor.disabled',
            organizationId: $monitor->organization_id,
            subjectType: 'monitor',
            subjectId: (string) $monitor->id,
            status: 'disabled',
            source: 'web',
            payload: [
                'type' => $monitor->type,
            ],
        ));

        return $monitor;
    }
}
