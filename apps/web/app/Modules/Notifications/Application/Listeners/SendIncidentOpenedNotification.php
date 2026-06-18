<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Notifications\Application\Services\IncidentNotificationMessageFactory;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendIncidentOpenedNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private IncidentNotificationMessageFactory $messages,
    ) {}

    public function handle(IncidentOpened $event): void
    {
        $incident = Incident::query()->find($event->incidentId);

        if ($incident === null) {
            return;
        }

        $this->dispatcher->dispatch($this->messages->opened($incident));
    }
}
