<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Notifications\Application\Services\IncidentNotificationMessageFactory;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendIncidentResolvedNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private IncidentNotificationMessageFactory $messages,
    ) {}

    public function handle(IncidentResolved $event): void
    {
        $incident = Incident::query()->find($event->incidentId);

        if ($incident === null) {
            return;
        }

        $this->dispatcher->dispatch($this->messages->resolved($incident));
    }
}
