<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendIncidentResolvedNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(IncidentResolved $event): void
    {
        $incident = Incident::query()->with('monitor')->find($event->incidentId);

        if ($incident === null) {
            return;
        }

        $this->dispatcher->dispatch(new NotificationMessage(
            eventType: 'incident.resolved',
            subject: 'Incident resolved: ' . $incident->title,
            body: "Incident resolved: {$incident->title}\nDowntime: {$incident->duration_seconds} seconds.",
            payload: [
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'status' => $incident->status,
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'duration_seconds' => $incident->duration_seconds,
            ],
            organizationId: $incident->organization_id,
            incidentId: $incident->id,
        ));
    }
}
