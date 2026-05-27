<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class DeleteMonitorAction
{
    public function __construct(
        private BusinessEventRecorder $events,
    ) {
    }

    public function handle(Monitor $siteMonitor): void
    {
        $this->events->record(new RecordBusinessEventData(
            eventType: 'monitor.deleted',
            organizationId: $siteMonitor->organization_id,
            subjectType: 'monitor',
            subjectId: (string) $siteMonitor->id,
            status: 'deleted',
            source: 'web',
            payload: [
                'project_id' => $siteMonitor->project_id,
                'monitored_resource_id' => $siteMonitor->monitored_resource_id,
                'type' => $siteMonitor->type,
                'enabled' => $siteMonitor->enabled,
            ],
        ));

        $siteMonitor->delete();
    }
}
