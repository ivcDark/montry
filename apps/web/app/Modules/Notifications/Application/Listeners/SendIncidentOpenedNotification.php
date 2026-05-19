<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendIncidentOpenedNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(IncidentOpened $event): void
    {
        $incident = Incident::query()->with('monitor')->find($event->incidentId);

        if ($incident === null) {
            return;
        }

        $this->dispatcher->dispatch(new NotificationMessage(
            eventType: 'incident.opened',
            subject: 'Инцидент открыт: ' . $incident->title,
            body: "Инцидент открыт: {$incident->title}\n{$incident->summary}",
            payload: [
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'title' => $incident->title,
                'summary' => $incident->summary,
                'status' => $incident->status,
                'started_at' => $incident->started_at?->toIso8601String(),
            ],
            organizationId: $incident->organization_id,
            incidentId: $incident->id,
        ));
    }
}
