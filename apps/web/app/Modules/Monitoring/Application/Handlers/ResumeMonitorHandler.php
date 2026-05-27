<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\ResumeMonitorCommand;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class ResumeMonitorHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
        private LimitChecker $limits,
        private BusinessEventRecorder $events,
    ) {}

    public function handle(ResumeMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $this->limits->assertCanUseInterval($monitor->organization_id, (int) $monitor->interval_seconds);
        $monitor->enabled = true;

        $monitor = $this->monitors->save($monitor);

        $this->events->record(new RecordBusinessEventData(
            eventType: 'monitor.enabled',
            organizationId: $monitor->organization_id,
            subjectType: 'monitor',
            subjectId: (string) $monitor->id,
            status: 'enabled',
            source: 'web',
            payload: [
                'type' => $monitor->type,
            ],
        ));

        return $monitor;
    }
}
