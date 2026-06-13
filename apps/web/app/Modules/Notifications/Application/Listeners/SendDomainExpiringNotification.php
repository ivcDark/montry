<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Monitoring\Domain\Events\DomainExpiring;
use App\Modules\Notifications\Application\DTO\NotificationMessage;
use App\Modules\Notifications\Application\Services\NotificationDispatcher;

final readonly class SendDomainExpiringNotification
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(DomainExpiring $event): void
    {
        $this->dispatcher->dispatch(new NotificationMessage(
            eventType: 'domain.expiring',
            subject: 'Domain expiring: ' . $event->domain,
            body: "Domain {$event->domain} expires in {$event->daysUntilExpiration} days.",
            payload: [
                'monitor_id' => $event->monitorId,
                'domain' => $event->domain,
                'days_until_expiration' => $event->daysUntilExpiration,
                'expires_at' => $event->expiresAt?->format(DATE_ATOM),
            ],
            organizationId: $event->organizationId,
        ));
    }
}
